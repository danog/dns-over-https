<?php declare(strict_types=1);

namespace Amp\DoH\Test;

use Amp\Cache\Cache;
use Amp\Cache\LocalCache;
use Amp\Dns\DnsConfigException;
use Amp\Dns\DnsConfigLoader;
use Amp\Dns\DnsResolver;
use Amp\Dns\Rfc1035StubDnsResolver;
use Amp\Dns\UnixDnsConfigLoader;
use Amp\Dns\WindowsDnsConfigLoader;
use Amp\DoH\DoHConfig;
use Amp\DoH\DoHNameserver;
use Amp\DoH\DoHNameserverType;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\PHPUnit\AsyncTestCase;

/** @psalm-suppress PropertyNotSetInConstructor */
class DoHConfigTest extends AsyncTestCase
{
    /**
     * @param non-empty-list<DoHNameserver> $nameservers Valid server array.
     *
     * @dataProvider provideValidServers
     */
    public function testAcceptsValidServers(array $nameservers): void
    {
        $this->assertInstanceOf(DoHConfig::class, new DoHConfig($nameservers));
    }

    /**
     * @return list<non-empty-list<list{0: DoHNameserver, 1?: DoHNameserverType}>>
     */
    public function provideValidServers(): array
    {
        return [
            [[new DoHNameserver('https://cloudflare-dns.com/dns-query')]],
            [[new DoHNameserver('https://cloudflare-dns.com/dns-query', DoHNameserverType::RFC8484_POST)]],
            [[new DoHNameserver('https://cloudflare-dns.com/dns-query', DoHNameserverType::RFC8484_GET)]],
            [[new DoHNameserver('https://cloudflare-dns.com/dns-query', DoHNameserverType::GOOGLE_JSON)]],
            [[new DoHNameserver('https://dns.google/resolve', DoHNameserverType::GOOGLE_JSON)]],
            [[new DoHNameserver('https://cloudflare-dns.com/dns-query', DoHNameserverType::GOOGLE_JSON), new DoHNameserver('https://dns.google/resolve', DoHNameserverType::GOOGLE_JSON)]],
        ];
    }

    /**
     * @param list<mixed> $nameservers Invalid server array.
     *
     * @dataProvider provideInvalidServers
     */
    public function testRejectsInvalidServers(array $nameservers): void
    {
        $this->expectException(DnsConfigException::class);
        new DoHConfig($nameservers);
    }

    /**
     * @return list<list{mixed}>
     */
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
     * @dataProvider provideValidHttpClient
     */
    public function testAcceptsValidHttpClient(HttpClient $client): void
    {
        $this->assertInstanceOf(DoHConfig::class, new DoHConfig([new DoHNameserver('https://cloudflare-dns.com/dns-query')], $client));
    }

    /**
     * @return list<list{HttpClient}>
     */
    public function provideValidHttpClient(): array
    {
        return [
            [HttpClientBuilder::buildDefault()],
        ];
    }
    /**
     * @dataProvider provideValidResolver
     */
    public function testAcceptsValidResolver(DnsResolver $resolver): void
    {
        $this->assertInstanceOf(DoHConfig::class, new DoHConfig([new DoHNameserver('https://cloudflare-dns.com/dns-query')], null, $resolver));
    }

    /**
     * @return list<list{DnsResolver}>
     */
    public function provideValidResolver()
    {
        return [
            [new Rfc1035StubDnsResolver()],
        ];
    }
    /**
     * @dataProvider provideValidConfigLoader
     */
    public function testAcceptsValidConfigLoader(DnsConfigLoader $configLoader): void
    {
        $this->assertInstanceOf(DoHConfig::class, new DoHConfig([new DoHNameserver('https://cloudflare-dns.com/dns-query')], null, null, $configLoader));
    }

    /**
     * @return list<list{DnsConfigLoader}>
     */
    public function provideValidConfigLoader()
    {
        return [
            [\stripos(PHP_OS, "win") === 0
                ? new WindowsDnsConfigLoader()
                : new UnixDnsConfigLoader()],
        ];
    }
    /**
     * @dataProvider provideValidCache
     */
    public function testAcceptsValidCache(Cache $cache): void
    {
        $this->assertInstanceOf(DoHConfig::class, new DoHConfig([new DoHNameserver('https://cloudflare-dns.com/dns-query')], null, null, null, $cache));
    }

    /**
     * @return list<list{Cache}>
     */
    public function provideValidCache()
    {
        return [
            [new LocalCache()],
        ];
    }
}
