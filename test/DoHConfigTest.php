<?php

namespace Amp\DoH\Test;

use Amp\Dns\Config;
use Amp\Dns\ConfigException;
use Amp\DoH\DoHConfig;
use Amp\DoH\Nameserver;
use Amp\PHPUnit\TestCase;
use Amp\Artax\DefaultClient;
use Amp\Dns\Rfc1035StubResolver;
use Amp\DoH\Rfc8484StubResolver;
use Amp\Cache\ArrayCache;
use Amp\Dns\WindowsConfigLoader;
use Amp\Dns\UnixConfigLoader;

class DoHConfigTest extends TestCase
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
            [[new Nameserver('https://cloudflare-dns.com/dns-query', Nameserver::RFC8484_POST)]],
            [[new Nameserver('https://cloudflare-dns.com/dns-query', Nameserver::RFC8484_GET)]],
            [[new Nameserver('https://cloudflare-dns.com/dns-query', Nameserver::GOOGLE_JSON)]],
            [[new Nameserver('https://google.com/resolve', Nameserver::GOOGLE_JSON, ["host" => "dns.google.com"])]],
            [[new Nameserver('https://cloudflare-dns.com/dns-query', Nameserver::GOOGLE_JSON), new Nameserver('https://google.com/resolve', Nameserver::GOOGLE_JSON, ["host" => "dns.google.com"])]],
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
     * @param \Amp\Artax\Client $client Valid artax instance
     *
     * @dataProvider provideValidArtax
     */
    public function testAcceptsValidArtax($client)
    {
        $this->assertInstanceOf(DoHConfig::class, new DoHConfig([new Nameserver('https://cloudflare-dns.com/dns-query')], $client));
    }

    public function provideValidArtax()
    {
        return [
            [new DefaultClient()]
            [null]
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
            [new Rfc1035StubResolver()]
            [null]
        ];
    }
    /**
     * @param $resolver any invalid resolver instance
     *
     * @dataProvider provideInvalidResolver
     */
    public function testRejectsInvalidResolver($resolver)
    {
        $this->expectException(ConfigException::class);
        new DoHConfig([new Nameserver('https://cloudflare-dns.com/dns-query')], null, $resolver);
    }

    public function provideInvalidResolver()
    {
        return [
            [new Rfc8484StubResolver(new DoHConfig([new Nameserver('https://mozilla.cloudflare-dns.com/dns-query')]))]
        ];
    }
    /**
     * @param $configLoader Valid ConfigLoader instance
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
            [new WindowsConfigLoader()]
            [new UnixConfigLoader()]
            [null]
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
            [new ArrayCache(5000/* default gc interval */, 256/* size */)]
            [null]
        ];
    }
}
