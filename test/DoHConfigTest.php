<?php

namespace Amp\DoH\Test;

use Amp\Cache\LocalCache;
use Amp\Dns\ConfigException;
use Amp\Dns\Rfc1035StubResolver;
use Amp\Dns\UnixConfigLoader;
use Amp\Dns\WindowsConfigLoader;
use Amp\DoH\DoHConfig;
use Amp\DoH\Nameserver;
use Amp\DoH\NameserverType;
use Amp\Http\Client\HttpClientBuilder;
use Amp\PHPUnit\AsyncTestCase;

class DoHConfigTest extends AsyncTestCase
{
    /**
     * @param string[] $nameservers Valid server array.
     *
     * @dataProvider provideValidServers
     */
    public function testAcceptsValidServers(array $nameservers)
    {
        $this->assertInstanceOf(DoHConfig::class, new DoHConfig($nameservers));
    }

    public function provideValidServers()
    {
        return [
            [[new Nameserver('https://cloudflare-dns.com/dns-query')]],
            [[new Nameserver('https://cloudflare-dns.com/dns-query', NameserverType::RFC8484_POST)]],
            [[new Nameserver('https://cloudflare-dns.com/dns-query', NameserverType::RFC8484_GET)]],
            [[new Nameserver('https://cloudflare-dns.com/dns-query', NameserverType::GOOGLE_JSON)]],
            [[new Nameserver('https://dns.google/resolve', NameserverType::GOOGLE_JSON)]],
            [[new Nameserver('https://cloudflare-dns.com/dns-query', NameserverType::GOOGLE_JSON), new Nameserver('https://dns.google/resolve', NameserverType::GOOGLE_JSON)]],
        ];
    }

    /**
     * @param string[] $nameservers Invalid server array.
     *
     * @dataProvider provideInvalidServers
     */
    public function testRejectsInvalidServers(array $nameservers)
    {
        $this->expectException(ConfigException::class);
        new DoHConfig($nameservers);
    }

    public function provideInvalidServers()
    {
        return [
            [[]],
            [[42]],
            [[null]],
            [[true]],
            [["foobar"]],
            [["foobar.com"]],
            [["127.1.1:53"]],
            [["127.1.1"]],
            [["127.1.1.1.1"]],
            [["126.0.0.5", "foobar"]],
            [["42"]],
            [["::1"]],
            [["::1:53"]],
            [["[::1]:53"]],
            [["[::1]:"]],
            [["[::1]:76235"]],
            [["[::1]:0"]],
            [["[::1]:-1"]],
            [["[::1:51"]],
            [["[::1]:abc"]],
        ];
    }

    /**
     * @param \Amp\Http\Client\DelegateHttpClient $client Valid HttpClient instance
     *
     * @dataProvider provideValidHttpClient
     */
    public function testAcceptsValidHttpClient($client)
    {
        $this->assertInstanceOf(DoHConfig::class, new DoHConfig([new Nameserver('https://cloudflare-dns.com/dns-query')], $client));
    }

    public function provideValidHttpClient()
    {
        return [
            [HttpClientBuilder::buildDefault()],
        ];
    }
    /**
     * @param \Amp\Dns\Resolver $resolver Valid resolver instance
     *
     * @dataProvider provideValidResolver
     */
    public function testAcceptsValidResolver($resolver)
    {
        $this->assertInstanceOf(DoHConfig::class, new DoHConfig([new Nameserver('https://cloudflare-dns.com/dns-query')], null, $resolver));
    }

    public function provideValidResolver()
    {
        return [
            [new Rfc1035StubResolver()],
        ];
    }
    /**
     * @param $configLoader \Amp\Dns\ConfigLoader Valid ConfigLoader instance
     *
     * @dataProvider provideValidConfigLoader
     */
    public function testAcceptsValidConfigLoader($configLoader)
    {
        $this->assertInstanceOf(DoHConfig::class, new DoHConfig([new Nameserver('https://cloudflare-dns.com/dns-query')], null, null, $configLoader));
    }

    public function provideValidConfigLoader()
    {
        return [
            [\stripos(PHP_OS, "win") === 0
                ? new WindowsConfigLoader
                : new UnixConfigLoader],
        ];
    }
    /**
     * @param \Amp\Cache\Cache Valid cache instance
     *
     * @dataProvider provideValidCache
     */
    public function testAcceptsValidCache($cache)
    {
        $this->assertInstanceOf(DoHConfig::class, new DoHConfig([new Nameserver('https://cloudflare-dns.com/dns-query')], null, null, null, $cache));
    }

    public function provideValidCache()
    {
        return [
            [new LocalCache()],
        ];
    }
}
