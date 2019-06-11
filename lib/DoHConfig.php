<?php

namespace Amp\DoH;

use Amp\Artax\Client;
use Amp\Artax\DefaultClient;
use Amp\Cache\ArrayCache;
use Amp\Cache\Cache;
use Amp\Dns\ConfigLoader;
use Amp\Dns\Resolver;
use Amp\Dns\UnixConfigLoader;
use Amp\Dns\WindowsConfigLoader;
use Amp\Dns\Rfc1035StubResolver;
use Amp\Dns\ConfigException;

final class DoHConfig
{
    private $nameservers;
    private $artax;
    private $subResolver;
    private $configLoader;
    private $cache;

    public function __construct(array $nameservers, Client $artax = null, Resolver $resolver = null, ConfigLoader $configLoader = null, Cache $cache = null)
    {
        if (\count($nameservers) < 1) {
            throw new ConfigException("At least one nameserver is required for a valid config");
        }

        foreach ($nameservers as $nameserver) {
            $this->validateNameserver($nameserver);
        }

        $this->nameservers = $nameservers;
        $this->artax = $artax ?? new DefaultClient();
        $this->cache = $cache ?? new ArrayCache(5000/* default gc interval */, 256/* size */);
        $this->configLoader = $configLoader ?? (\stripos(PHP_OS, "win") === 0
            ? new WindowsConfigLoader
            : new UnixConfigLoader);
        $this->subResolver = $resolver ?? new Rfc1035StubResolver(null, $this->configLoader);

    }

    private function validateNameserver($nameserver)
    {
        if (!($nameserver instanceof Nameserver)) {
            throw new ConfigException("Invalid nameserver: {$nameserver}");
        }
    }

    public function getNameservers(): array
    {
        return $this->nameservers;
    }
    public function isNameserver($string): bool
    {
        foreach ($this->nameservers as $nameserver) {
            if ($nameserver->getHost() === $string) {
                return true;
            }

        }
        return false;
    }

    public function getArtax(): Client
    {
        return $this->artax;
    }

    public function getCache(): Cache
    {
        return $this->cache;
    }
    public function getConfigLoader(): ConfigLoader
    {
        return $this->configLoader;
    }
    public function getSubResolver(): Resolver
    {
        return $this->subResolver;
    }
}
