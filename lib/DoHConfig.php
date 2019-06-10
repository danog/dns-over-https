<?php

namespace Amp\DoH;

use Amp\Artax\Client;
use Amp\Artax\DefaultClient;

final class DoHConfig
{
    private $nameservers;
    private $artax;

    public function __construct(array $nameservers, Client $artax = null)
    {
        if (\count($nameservers) < 1) {
            throw new ConfigException("At least one nameserver is required for a valid config");
        }

        foreach ($nameservers as $nameserver) {
            $this->validateNameserver($nameserver);
        }

        if ($artax === null) {
            $artax = new DefaultClient();
        }
        $this->artax = $artax;
        $this->nameservers = $nameservers;
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
            if ($nameserver->getHost() === $string) return true;
        }
        return false;
    }

    public function getArtax(): Client
    {
        return $this->artax;
    }
}
