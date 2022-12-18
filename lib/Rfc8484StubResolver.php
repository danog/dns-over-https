<?php declare(strict_types=1);

namespace Amp\DoH;

use Amp\Cache\Cache;
use Amp\Cancellation;
use Amp\CompositeException;
use Amp\Dns\ConfigException;
use Amp\Dns\DnsConfig;
use Amp\Dns\DnsConfigLoader;
use Amp\Dns\DnsException;
use Amp\Dns\NoRecordException;
use Amp\Dns\Record;
use Amp\Dns\Resolver;
use Amp\Dns\Rfc1035StubResolver;
use Amp\Dns\TimeoutException;
use Amp\Future;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Request;
use Amp\NullCancellation;
use danog\LibDNSJson\JsonDecoder;
use danog\LibDNSJson\JsonDecoderFactory;
use danog\LibDNSJson\QueryEncoder;
use danog\LibDNSJson\QueryEncoderFactory;
use LibDNS\Decoder\Decoder;
use LibDNS\Decoder\DecoderFactory;
use LibDNS\Encoder\Encoder;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Messages\Message;
use LibDNS\Messages\MessageFactory;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\Question;
use LibDNS\Records\QuestionFactory;

use function Amp\async;
use function Amp\Dns\normalizeName;

final class Rfc8484StubResolver implements Resolver
{
    const CACHE_PREFIX = "amphp.doh.";

    private DnsConfigLoader $configLoader;
    private QuestionFactory $questionFactory;
    private ?DnsConfig $config = null;

    private ?Future $pendingConfig = null;

    private Cache $cache;

    /** @var Future[] */
    private array $pendingQueries = [];

    private Rfc1035StubResolver $subResolver;
    private Encoder $encoder;
    private Decoder $decoder;
    private QueryEncoder $encoderJson;
    private JsonDecoder $decoderJson;
    private MessageFactory $messageFactory;
    private DelegateHttpClient $httpClient;

    public function __construct(private DoHConfig $dohConfig)
    {
        $this->cache = $dohConfig->getCache();
        $this->configLoader = $dohConfig->getConfigLoader();
        $this->subResolver = $dohConfig->getSubResolver();
        $this->questionFactory = new QuestionFactory;
        $this->encoder = (new EncoderFactory)->create();
        $this->decoder = (new DecoderFactory)->create();
        $this->encoderJson = (new QueryEncoderFactory)->create();
        $this->decoderJson = (new JsonDecoderFactory)->create();
        $this->httpClient = $dohConfig->getHttpClient();
        $this->messageFactory = new MessageFactory;
    }

    /** @inheritdoc */
    public function resolve(string $name, int $typeRestriction = null, ?Cancellation $cancellation = null): array
    {
        if ($typeRestriction !== null && $typeRestriction !== Record::A && $typeRestriction !== Record::AAAA) {
            throw new \Error("Invalid value for parameter 2: null|Record::A|Record::AAAA expected");
        }

        if (!$this->config) {
            try {
                $this->reloadConfig();
            } catch (ConfigException $e) {
                $this->config = new DnsConfig(['0.0.0.0'], []);
            }
        }

        switch ($typeRestriction) {
            case Record::A:
                if (\filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return [new Record($name, Record::A, null)];
                }

                if (\filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    throw new DnsException("Got an IPv6 address, but type is restricted to IPv4");
                }
                break;
            case Record::AAAA:
                if (\filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    return [new Record($name, Record::AAAA, null)];
                }

                if (\filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    throw new DnsException("Got an IPv4 address, but type is restricted to IPv6");
                }
                break;
            default:
                if (\filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return [new Record($name, Record::A, null)];
                }

                if (\filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    return [new Record($name, Record::AAAA, null)];
                }
                break;
        }

        $dots = \substr_count($name, ".");
        $trailingDot = $name[-1] === ".";
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

        if ($this->dohConfig->isNameserver($name)) {
            return $this->subResolver->resolve($name, $typeRestriction, $cancellation);
        }
        \assert($this->config !== null);

        $searchList = [null];
        if (!$trailingDot && $dots < $this->config->getNdots()) {
            $searchList = \array_merge($this->config->getSearchList(), $searchList);
        }

        foreach ($searchList as $searchIndex => $search) {
            for ($redirects = 0; $redirects < 5; $redirects++) {
                $searchName = $name;

                if ($search !== null) {
                    $searchName = $name . "." . $search;
                }

                try {
                    if ($typeRestriction) {
                        return $this->query($searchName, $typeRestriction, $cancellation);
                    }

                    [$exceptions, $records] = Future\awaitAll([
                        async(fn () => $this->query($searchName, Record::A, $cancellation)),
                        async(fn () => $this->query($searchName, Record::AAAA, $cancellation)),
                    ]);

                    if (\count($exceptions) === 2) {
                        $errors = [];

                        foreach ($exceptions as $reason) {
                            if ($reason instanceof NoRecordException) {
                                throw $reason;
                            }

                            if ($searchIndex < \count($searchList) - 1 && \in_array($reason->getCode(), [2, 3], true)) {
                                continue 2;
                            }

                            $errors[] = $reason->getMessage();
                        }

                        throw new DnsException(
                            "All query attempts failed for {$searchName}: " . \implode(", ", $errors),
                            0,
                            new CompositeException($exceptions)
                        );
                    }

                    return \array_merge(...$records);
                } catch (NoRecordException) {
                    try {
                        $cnameRecords = $this->query($searchName, Record::CNAME, $cancellation);
                        $name = $cnameRecords[0]->getValue();
                        continue;
                    } catch (NoRecordException) {
                        $dnameRecords = $this->query($searchName, Record::DNAME, $cancellation);
                        $name = $dnameRecords[0]->getValue();
                        continue;
                    }
                } catch (DnsException $e) {
                    if ($searchIndex < \count($searchList) - 1 && \in_array($e->getCode(), [2, 3], true)) {
                        continue 2;
                    }

                    throw $e;
                }
            }
        }

        \assert(isset($searchName));

        throw new DnsException("Giving up resolution of '{$searchName}', too many redirects");
    }

    /**
     * Reloads the configuration in the background.
     *
     * Once it's finished, the configuration will be used for new requests.
     */
    public function reloadConfig(): void
    {
        if (!$this->pendingConfig) {
            $promise = async(function () {
                try {
                    $this->subResolver->reloadConfig();
                    $this->config = $this->configLoader->loadConfig();
                } finally {
                    $this->pendingConfig = null;
                }
            });
            $this->pendingConfig = $promise;
        }

        $this->pendingConfig->await();
    }

    private function queryHosts(string $name, int $typeRestriction = null): array
    {
        \assert($this->config !== null);
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
    public function query(string $name, int $type, ?Cancellation $cancellation = null): array
    {
        $cancellation ??= new NullCancellation;
        $pendingQueryKey = $type." ".$name;

        if (isset($this->pendingQueries[$pendingQueryKey])) {
            return $this->pendingQueries[$pendingQueryKey]->await($cancellation);
        }

        $promise = async(function () use ($name, $type, $cancellation, $pendingQueryKey) {
            try {
                if (!$this->config) {
                    try {
                        $this->reloadConfig();
                    } catch (ConfigException $e) {
                        $this->config = new DnsConfig(['0.0.0.0'], []);
                    }
                }

                \assert($this->config !== null);

                $name = $this->normalizeName($name, $type);
                $question = $this->createQuestion($name, $type);

                if (null !== $cachedValue = $this->cache->get($this->getCacheKey($name, $type))) {
                    return $this->decodeCachedResult($name, $type, $cachedValue);
                }

                $nameservers = $this->dohConfig->getNameservers();
                $attempts = $this->config->getAttempts() * \count($nameservers);
                $attempt = 0;

                $nameserver = $nameservers[0];

                $attemptDescription = [];

                while ($attempt < $attempts) {
                    try {
                        $attemptDescription[] = $nameserver;

                        $response = $this->ask($nameserver, $question, $cancellation);
                        $this->assertAcceptableResponse($response);

                        if ($response->isTruncated()) {
                            throw new DnsException("Server returned a truncated response for '{$name}' (".Record::getName($type).")");
                        }

                        $answers = $response->getAnswerRecords();
                        $result = [];
                        $ttls = [];

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
                    } catch (TimeoutException) {
                        $i = ++$attempt % \count($nameservers);
                        $nameserver = $nameservers[$i];
                    }
                }

                throw new TimeoutException(\sprintf(
                    "No response for '%s' (%s) from any nameserver within %d ms after %d attempts, tried %s",
                    $name,
                    Record::getName($type),
                    $this->config->getTimeout(),
                    $attempts,
                    \implode(", ", $attemptDescription)
                ));
            } finally {
                unset($this->pendingQueries[$pendingQueryKey]);
            }
        });

        $this->pendingQueries[$pendingQueryKey] = $promise;

        return $promise->await($cancellation);
    }

    private function ask(Nameserver $nameserver, Question $question, Cancellation $cancellation): Message
    {
        $message = $this->createMessage($question, \random_int(0, 0xffff));
        $request = null;
        switch ($nameserver->getType()) {
            case NameserverType::RFC8484_GET:
                $data = $this->encoder->encode($message);
                $request = new Request($nameserver->getUri().'?'.\http_build_query(['dns' => \base64_encode($data), 'ct' => 'application/dns-message']), "GET");
                $request->setHeader('accept', 'application/dns-message');
                $request->setHeaders($nameserver->getHeaders());
                break;
            case NameserverType::RFC8484_POST:
                $data = $this->encoder->encode($message);
                $request = new Request($nameserver->getUri(), "POST");
                $request->setBody($data);
                $request->setHeader('content-type', 'application/dns-message');
                $request->setHeader('accept', 'application/dns-message');
                $request->setHeader('content-length', (string) \strlen($data));
                $request->setHeaders($nameserver->getHeaders());
                break;
            case NameserverType::GOOGLE_JSON:
                $data = $this->encoderJson->encode($message);
                $request = new Request($nameserver->getUri().'?'.$data, "GET");
                $request->setHeader('accept', 'application/dns-json');
                $request->setHeaders($nameserver->getHeaders());
                break;
        }
        \assert($request !== null);

        $response = $this->httpClient->request($request, $cancellation);
        if ($response->getStatus() !== 200) {
            throw new DoHException("HTTP result !== 200: ".$response->getStatus()." ".$response->getReason(), $response->getStatus());
        }
        $response = $response->getBody()->buffer();

        switch ($nameserver->getType()) {
            case NameserverType::RFC8484_GET:
            case NameserverType::RFC8484_POST:
                return $this->decoder->decode($response);
            case NameserverType::GOOGLE_JSON:
                return $this->decoderJson->decode($response, $message->getID());
        }
    }

    private function normalizeName(string $name, int $type): string
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

    private function createMessage(Question $question, int $id): Message
    {
        $request = $this->messageFactory->create(MessageTypes::QUERY);
        $request->getQuestionRecords()->add($question);
        $request->isRecursionDesired(true);
        $request->setID($id);
        return $request;
    }

    private function getCacheKey(string $name, int $type): string
    {
        return self::CACHE_PREFIX.$name."#".$type;
    }

    /**
     * @return list<Record>
     */
    private function decodeCachedResult(string $name, int $type, string $encoded): array
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

    private function assertAcceptableResponse(Message $response): void
    {
        if ($response->getResponseCode() !== 0) {
            throw new DnsException(\sprintf("Server returned error code: %d", $response->getResponseCode()));
        }
    }
}
