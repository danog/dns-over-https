<?php

namespace Amp\Dns\Test;

use Amp\Dns\Config;
use Amp\Dns\ConfigException;
use Amp\PHPUnit\TestCase;

class ConfigTest extends TestCase
{
    /**
     * @param string[] $nameservers Valid server array.
     *
     * @dataProvider provideValidServers
     */
    public function testAcceptsValidServers(array $nameservers)
    {
        $this->assertInstanceOf(Config::class, new Config($nameservers));
    }

    public function provideValidServers()
    {
        return [
            [["127.1.1.1"]],
            [["127.1.1.1:1"]],
            [["[::1]:52"]],
            [["[::1]"]],
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
        new Config($nameservers);
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
            [["127.1.1"]],
            [["127.1.1.1.1"]],
            [["126.0.0.5", "foobar"]],
            [["42"]],
            [["::1"]],
            [["::1:53"]],
            [["[::1]:"]],
            [["[::1]:76235"]],
            [["[::1]:0"]],
            [["[::1]:-1"]],
            [["[::1:51"]],
            [["[::1]:abc"]],
        ];
    }

    public function testInvalidTimeout()
    {
        $this->expectException(ConfigException::class);
        new Config(["127.0.0.1"], [], -1);
    }

    public function testInvalidAttempts()
    {
        $this->expectException(ConfigException::class);
        new Config(["127.0.0.1"], [], 500, 0);
    }
}
