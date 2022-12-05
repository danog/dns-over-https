<?php

namespace Amp\DoH;

use Amp\Cache\Cache;
use Amp\Cache\LocalCache;
use Amp\Dns\ConfigException;
use Amp\Dns\ConfigLoader;
use Amp\Dns\DnsConfigLoader;
use Amp\Dns\Rfc1035StubResolver;
use Amp\Dns\UnixConfigLoader;
use Amp\Dns\UnixDnsConfigLoader;
use Amp\Dns\WindowsConfigLoader;
use Amp\Dns\WindowsDnsConfigLoader;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClientBuilder;

final class DoHConfig
{
    /**
     * @var non-empty-array<Nameserver> $nameservers
     */
    private readonly array $nameservers;
    private readonly DelegateHttpClient $httpClient;
    private readonly Rfc1035StubResolver $subResolver;
    private readonly DnsConfigLoader $configLoader;
    private readonly Cache $cache;

    /**
     * @param non-empty-array<Nameserver> $nameservers
     */
    public function __construct(array $nameservers, ?DelegateHttpClient $httpClient = null, ?Rfc1035StubResolver $resolver = null, ?DnsConfigLoader $configLoader = null, ?Cache $cache = null)
    {
        /** @psalm-suppress TypeDoesNotContainType */
        if (\count($nameservers) < 1) {
            throw new ConfigException("At least one nameserver is required for a valid config");
        }

        foreach ($nameservers as $nameserver) {
            /** @psalm-suppress DocblockContradiction */
            if (!($nameserver instanceof Nameserver)) {
                throw new ConfigException("Invalid nameserver: {$nameserver}");
            }
        }

        $this->nameservers = $nameservers;
        $this->httpClient = $httpClient ?? HttpClientBuilder::buildDefault();
        $this->cache = $cache ?? new LocalCache(256, 5.0);
        $this->configLoader = $configLoader ?? (\stripos(PHP_OS, "win") === 0
            ? new WindowsDnsConfigLoader()
            : new UnixDnsConfigLoader());
        $this->subResolver = $resolver ?? new Rfc1035StubResolver(null, $this->configLoader);
    }

    /**
     * @return non-empty-array<Nameserver>
     */
    public function getNameservers(): array
    {
        return $this->nameservers;
    }

    public function isNameserver(string $string): bool
    {
        foreach ($this->nameservers as $nameserver) {
            if ($nameserver->getHost() === $string) {
                return true;
            }
        }
        return false;
    }

    public function getHttpClient(): DelegateHttpClient
    {
        return $this->httpClient;
    }

    public function getCache(): Cache
    {
        return $this->cache;
    }
    public function getConfigLoader(): DnsConfigLoader
    {
        return $this->configLoader;
    }
    public function getSubResolver(): Rfc1035StubResolver
    {
        return $this->subResolver;
    }
}
