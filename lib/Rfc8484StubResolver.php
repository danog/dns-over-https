<?php

namespace Amp\DoH;

use Amp\Cache\ArrayCache;
use Amp\Cache\Cache;
use Amp\Dns\DnsException;
use Amp\Dns\NoRecordException;
use Amp\DoH\Internal\Socket;
use Amp\DoH\Internal\TcpSocket;
use Amp\DoH\Internal\UdpSocket;
use Amp\Loop;
use Amp\MultiReasonException;
use Amp\Promise;
use Amp\Success;
use function Amp\call;
use LibDNS\Messages\Message;
use LibDNS\Records\Question;
use LibDNS\Records\QuestionFactory;
use Amp\Dns\TimeoutException;
use Amp\Dns\Record;
use Amp\Dns\Resolver;
use Amp\DoH\Internal\HttpsSocket;
use Amp\Dns\ConfigLoader;

final class Rfc8484StubResolver implements Resolver
{
    const CACHE_PREFIX = "amphp.doh.";

    /** @var \Amp\Dns\ConfigLoader */
    private $configLoader;

    /** @var \LibDNS\Records\QuestionFactory */
    private $questionFactory;

    /** @var \Amp\Dns\Config|null */
    private $config;

    /** @var Promise|null */
    private $pendingConfig;

    /** @var \Amp\DoH\DoHConfig */
    private $dohConfig;

    /** @var Cache */
    private $cache;

    /** @var Promise[] */
    private $pendingQueries = [];

    public function __construct(DoHConfig $config, Cache $cache = null, ConfigLoader $configLoader = null)
    {
        $this->cache = $cache ?? new ArrayCache(5000/* default gc interval */, 256/* size */);
        $this->configLoader = $configLoader ?? (\stripos(PHP_OS, "win") === 0
            ? new WindowsConfigLoader()
            : new UnixConfigLoader);
        $this->dohConfig = $config;

        $this->questionFactory = new QuestionFactory;
    }

    /** @inheritdoc */
    public function resolve(string $name, int $typeRestriction = null): Promise
    {
        if ($typeRestriction !== null && $typeRestriction !== Record::A && $typeRestriction !== Record::AAAA) {
            throw new \Error("Invalid value for parameter 2: null|Record::A|Record::AAAA expected");
        }

        return call(function () use ($name, $typeRestriction) {
            if (!$this->config) {
                yield $this->reloadConfig();
            }

            switch ($typeRestriction) {
                case Record::A:
                    if (\filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        return [new Record($name, Record::A, null)];
                    } elseif (\filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        throw new DnsException("Got an IPv6 address, but type is restricted to IPv4");
                    }
                    break;
                case Record::AAAA:
                    if (\filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        return [new Record($name, Record::AAAA, null)];
                    } elseif (\filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        throw new DnsException("Got an IPv4 address, but type is restricted to IPv6");
                    }
                    break;
                default:
                    if (\filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        return [new Record($name, Record::A, null)];
                    } elseif (\filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        return [new Record($name, Record::AAAA, null)];
                    }
                    break;
            }

            $name = normalizeName($name);

            if ($records = $this->queryHosts($name, $typeRestriction)) {
                return $records;
            }

            // Follow RFC 6761 and never send queries for localhost to the caching DNS server
            // Usually, these queries are already resolved via queryHosts()
            if ($name === 'localhost') {
                return $typeRestriction === Record::AAAA
                ? [new Record('::1', Record::AAAA, null)]
                : [new Record('127.0.0.1', Record::A, null)];
            }

            for ($redirects = 0; $redirects < 5; $redirects++) {
                try {
                    if ($typeRestriction) {
                        $records = yield $this->query($name, $typeRestriction);
                    } else {
                        try {
                            list(, $records) = yield Promise\some([
                                $this->query($name, Record::A),
                                $this->query($name, Record::AAAA),
                            ]);

                            $records = \array_merge(...$records);

                            break; // Break redirect loop, otherwise we query the same records 5 times
                        } catch (MultiReasonException $e) {
                            $errors = [];

                            foreach ($e->getReasons() as $reason) {
                                if ($reason instanceof NoRecordException) {
                                    throw $reason;
                                }

                                $errors[] = $reason->getMessage();
                            }

                            throw new DnsException("All query attempts failed for {$name}: ".\implode(", ", $errors), 0, $e);
                        }
                    }
                } catch (NoRecordException $e) {
                    try {
                        /** @var Record[] $cnameRecords */
                        $cnameRecords = yield $this->query($name, Record::CNAME);
                        $name = $cnameRecords[0]->getValue();
                        continue;
                    } catch (NoRecordException $e) {
                        /** @var Record[] $dnameRecords */
                        $dnameRecords = yield $this->query($name, Record::DNAME);
                        $name = $dnameRecords[0]->getValue();
                        continue;
                    }
                }
            }

            return $records;
        });
    }

    /**
     * Reloads the configuration in the background.
     *
     * Once it's finished, the configuration will be used for new requests.
     *
     * @return Promise
     */
    public function reloadConfig(): Promise
    {
        if ($this->pendingConfig) {
            return $this->pendingConfig;
        }

        $promise = call(function () {
            $this->config = yield $this->configLoader->loadConfig();
        });

        $this->pendingConfig = $promise;

        $promise->onResolve(function () {
            $this->pendingConfig = null;
        });

        return $promise;
    }

    private function queryHosts(string $name, int $typeRestriction = null): array
    {
        $hosts = $this->config->getKnownHosts();
        $records = [];

        $returnIPv4 = $typeRestriction === null || $typeRestriction === Record::A;
        $returnIPv6 = $typeRestriction === null || $typeRestriction === Record::AAAA;

        if ($returnIPv4 && isset($hosts[Record::A][$name])) {
            $records[] = new Record($hosts[Record::A][$name], Record::A, null);
        }

        if ($returnIPv6 && isset($hosts[Record::AAAA][$name])) {
            $records[] = new Record($hosts[Record::AAAA][$name], Record::AAAA, null);
        }

        return $records;
    }

    /** @inheritdoc */
    public function query(string $name, int $type): Promise
    {
        $pendingQueryKey = $type." ".$name;

        if (isset($this->pendingQueries[$pendingQueryKey])) {
            return $this->pendingQueries[$pendingQueryKey];
        }

        $promise = call(function () use ($name, $type) {
            if (!$this->config) {
                yield $this->reloadConfig();
            }

            $name = $this->normalizeName($name, $type);
            $question = $this->createQuestion($name, $type);

            if (null !== $cachedValue = yield $this->cache->get($this->getCacheKey($name, $type))) {
                return $this->decodeCachedResult($name, $type, $cachedValue);
            }

            /** @var Nameserver[] $nameservers */
            $nameservers = $this->dohConfig->getNameservers();
            $attempts = $this->config->getAttempts();
            $attempt = 0;

            /** @var Socket $socket */
            $nameserver = $nameservers[0];
            $socket = $this->getSocket($nameserver);

            $attemptDescription = [];

            while ($attempt < $attempts) {
                try {
                    $attemptDescription[] = $nameserver;

                    /** @var Message $response */
                    $response = yield $socket->ask($question, $this->config->getTimeout());
                    $this->assertAcceptableResponse($response);

                    if ($response->isTruncated()) {
                        throw new DnsException("Server returned a truncated response for '{$name}' (".Record::getName($type).")");
                    }

                    $answers = $response->getAnswerRecords();
                    $result = [];
                    $ttls = [];

                    /** @var \LibDNS\Records\Resource $record */
                    foreach ($answers as $record) {
                        $recordType = $record->getType();
                        $result[$recordType][] = (string) $record->getData();

                        // Cache for max one day
                        $ttls[$recordType] = \min($ttls[$recordType] ?? 86400, $record->getTTL());
                    }

                    foreach ($result as $recordType => $records) {
                        // We don't care here whether storing in the cache fails
                        $this->cache->set($this->getCacheKey($name, $recordType), \json_encode($records), $ttls[$recordType]);
                    }

                    if (!isset($result[$type])) {
                        // "it MUST NOT cache it for longer than five (5) minutes" per RFC 2308 section 7.1
                        $this->cache->set($this->getCacheKey($name, $type), \json_encode([]), 300);
                        throw new NoRecordException("No records returned for '{$name}' (".Record::getName($type).")");
                    }

                    return \array_map(function ($data) use ($type, $ttls) {
                        return new Record($data, $type, $ttls[$type]);
                    }, $result[$type]);
                } catch (TimeoutException $e) {
                    // Defer call, because it might interfere with the unreference() call in Internal\Socket otherwise

                    $i = ++$attempt % \count($nameservers);
                    $nameserver = $nameservers[$i];
                    $socket = yield $this->getSocket($nameserver);
                    continue;
                }
            }

            throw new TimeoutException(\sprintf(
                "No response for '%s' (%s) from any nameserver after %d attempts, tried %s",
                $name,
                Record::getName($type),
                $attempts,
                \implode(", ", $attemptDescription)
            ));
        });

        $this->pendingQueries[$type." ".$name] = $promise;
        $promise->onResolve(function () use ($name, $type) {
            unset($this->pendingQueries[$type." ".$name]);
        });

        return $promise;
    }

    private function normalizeName(string $name, int $type)
    {
        if ($type === Record::PTR) {
            if (($packedIp = @\inet_pton($name)) !== false) {
                if (isset($packedIp[4])) { // IPv6
                    $name = \wordwrap(\strrev(\bin2hex($packedIp)), 1, ".", true).".ip6.arpa";
                } else { // IPv4
                    $name = \inet_ntop(\strrev($packedIp)).".in-addr.arpa";
                }
            }
        } elseif (\in_array($type, [Record::A, Record::AAAA])) {
            $name = normalizeName($name);
        }

        return $name;
    }

    /**
     * @param string $name
     * @param int    $type
     *
     * @return \LibDNS\Records\Question
     */
    private function createQuestion(string $name, int $type): Question
    {
        if (0 > $type || 0xffff < $type) {
            $message = \sprintf('%d does not correspond to a valid record type (must be between 0 and 65535).', $type);
            throw new \Error($message);
        }

        $question = $this->questionFactory->create($type);
        $question->setName($name);

        return $question;
    }

    private function getCacheKey(string $name, int $type): string
    {
        return self::CACHE_PREFIX.$name."#".$type;
    }

    private function decodeCachedResult(string $name, int $type, string $encoded)
    {
        $decoded = \json_decode($encoded, true);

        if (!$decoded) {
            throw new NoRecordException("No records returned for {$name} (cached result)");
        }

        $result = [];

        foreach ($decoded as $data) {
            $result[] = new Record($data, $type);
        }

        return $result;
    }

    private function getSocket(Nameserver $nameserver)
    {
        $uri = $nameserver->getUri();
        if (isset($this->sockets[$uri])) {
            return new Success($this->sockets[$uri]);
        }


        $this->sockets[$uri] = HttpsSocket::connect($this->dohConfig->getArtax(), $nameserver);

        return $this->sockets[$uri];
    }

    private function assertAcceptableResponse(Message $response)
    {
        if ($response->getResponseCode() !== 0) {
            throw new DnsException(\sprintf("Server returned error code: %d", $response->getResponseCode()));
        }
    }
}
