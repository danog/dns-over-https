<?php

namespace Amp\DoH\Internal;

use Amp;
use Amp\Artax\Client;
use Amp\Artax\Request;
use Amp\Dns\DnsException;
use Amp\DoH\JsonDecoderFactory;
use Amp\DoH\Nameserver;
use Amp\DoH\QueryEncoderFactory;
use Amp\Promise;
use LibDNS\Decoder\DecoderFactory;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Messages\Message;
use function Amp\call;

/** @internal */
final class HttpsSocket extends Socket
{
    /** @var \Amp\Artax\Client */
    private $httpClient;

    /** @var \Amp\DoH\Nameserver */
    private $nameserver;

    /** @var \LibDNS\Encoder\Encoder */
    private $encoder;

    /** @var \LibDNS\Decoder\Decoder */
    private $decoder;

    /** @var \Amp\Deferred */
    private $responseDeferred;

    public static function connect(Client $artax, Nameserver $nameserver): Socket
    {
        return new self($artax, $nameserver);
    }

    protected function __construct(Client $artax, Nameserver $nameserver)
    {
        $this->httpClient = $artax;
        $this->nameserver = $nameserver;

        if ($nameserver->getType() !== Nameserver::GOOGLE_JSON) {
            $this->encoder = (new EncoderFactory)->create();
            $this->decoder = (new DecoderFactory)->create();
        } else {
            $this->encoder = (new QueryEncoderFactory)->create();
            $this->decoder = (new JsonDecoderFactory)->create();
        }

        parent::__construct();
    }

    protected function resolve(Message $message): Promise
    {
        $id = $message->getID();

        switch ($this->nameserver->getType()) {
            case Nameserver::RFC8484_GET:
                $data = $this->encoder->encode($message);
                $request = (new Request($this->nameserver->getUri().'?'.\http_build_query(['dns' => \base64_encode($data), 'ct' => 'application/dns-message']), "GET"))
                    ->withHeader('accept', 'application/dns-message')
                    ->withHeaders($this->nameserver->getHeaders());
                break;
            case Nameserver::RFC8484_POST:
                $data = $this->encoder->encode($message);
                $request = (new Request($this->nameserver->getUri(), "POST"))
                    ->withBody($data)
                    ->withHeader('content-type', 'application/dns-message')
                    ->withHeader('accept', 'application/dns-message')
                    ->withHeaders($this->nameserver->getHeaders());
                break;
            case Nameserver::GOOGLE_JSON:
                $data = $this->encoder->encode($message);
                $request = (new Request($this->nameserver->getUri().'?'.$data, "GET"))
                    ->withHeader('accept', 'application/dns-json')
                    ->withHeaders($this->nameserver->getHeaders());
                break;
        }
        $response = $this->httpClient->request($request);
        return call(function () use ($response, $id) {
            $response = yield $response;
            if ($response->getStatus() !== 200) {
                throw new DnsException("HTTP result !== 200: ".$response->getReason());
            }
            $response = yield $response->getBody();


            switch ($this->nameserver->getType()) {
                case Nameserver::RFC8484_GET:
                case Nameserver::RFC8484_POST:
                    return $this->decoder->decode($response);
                case Nameserver::GOOGLE_JSON:
                    return $this->decoder->decode($response, $id);
            }
        });
    }
}
