<?php

namespace Amp\DoH\Test;

use Amp\Dns;
use Amp\DoH;
use Amp\DoH\Internal\Socket;
use Amp\DoH\Nameserver;
use Amp\Http\Client\HttpClientBuilder;
use LibDNS\Records\QuestionFactory;
use function Amp\Promise\wait;

class HttpsSocketTest extends SocketTest
{
    protected function connect(Nameserver $nameserver): Socket
    {
        return DoH\Internal\HttpsSocket::connect(HttpClientBuilder::buildDefault(), $nameserver);
    }

    public function testTimeout()
    {
        $this->expectException(Dns\TimeoutException::class);
        /** @var DoH\Internal\HttpsSocket $socket */
        $socket = self::connect(new Nameserver('https://mozilla.cloudflare-dns.com/dns-query'));

        $question = (new QuestionFactory)->create(Dns\Record::A);
        $question->setName("google.com");

        wait($socket->ask($question, 0));
    }
}
