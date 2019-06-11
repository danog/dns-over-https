<?php

namespace Amp\DoH\Test;

use Amp\Dns;
use Amp\DoH\Internal\Socket;
use Amp\DoH\Nameserver;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use LibDNS\Messages\Message;
use LibDNS\Messages\MessageTypes;
use LibDNS\Records\QuestionFactory;

abstract class SocketTest extends TestCase
{
    abstract protected function connect(Nameserver $nameserver): Socket;

    public function testAsk()
    {
        Loop::run(function () {
            $question = (new QuestionFactory)->create(Dns\Record::A);
            $question->setName("google.com");

            /** @var DoH\Internal\HttpsSocket $socket */
            $socket = $this->connect(new Nameserver('https://mozilla.cloudflare-dns.com/dns-query'));

            /** @var Message $result */
            $result = yield $socket->ask($question, 5000);

            $this->assertInstanceOf(Message::class, $result);
            $this->assertSame(MessageTypes::RESPONSE, $result->getType());
        });
    }
}
