<?php

namespace Amp\DoH\Internal;

use Amp;
use Amp\Artax\Client;
use Amp\Artax\Request;
use Amp\Artax\Response;
use Amp\Deferred;
use Amp\DoH\Nameserver;
use Amp\Promise;
use function Amp\call;
use LibDNS\Decoder\DecoderFactory;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Messages\Message;
use Amp\DoH\JsonDecoderFactory;
use Amp\DoH\QueryEncoderFactory;

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

    /** @var \Amp\Artax\Response[] */
    private $queue;

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

        $this->queue = new \SplQueue;

        if ($nameserver->getType() !== Nameserver::GOOGLE_JSON) {
            $this->encoder = (new EncoderFactory)->create();
            $this->decoder = (new DecoderFactory)->create();
        } else {
            $this->encoder = (new QueryEncoderFactory)->create();
            $this->decoder = (new JsonDecoderFactory)->create();
        }

        parent::__construct();
    }
    
    protected function send(Message $message): Promise
    {
        $id = $message->getID();

        switch ($this->nameserver->getType()) {
            case Nameserver::RFC8484_GET:
                $data = $this->encoder->encode($message);
                $request = (new Request($this->nameserver->getUri().'?'.http_build_query(['dns' => $data]), "GET"))
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

        $deferred = new Deferred;
        $promise = $this->httpClient->request($request);
        $promise->onResolve(function (\Throwable $error = null, Response $result = null) use ($deferred, $id) {
            if ($error) {
                $deferred->fail($error);
                return;
            }
            if ($result) {
                $this->queueResponse($result, $id);
                $deferred->resolve();
            }
        });

        return $deferred->promise();
    }
    public function queueResponse(Response $result, int $id)
    {
        $this->queue->push([$result, $id]);
        if ($this->responseDeferred) {
            $this->responseDeferred->resolve();
            //Loop::defer([$this->responseDeferred, 'resolve']);
        }
    }
    protected function receive(): Promise
    {
        return call(function () {
            /** @var $result \Amp\Artax\Response */
            while ($this->queue->isEmpty()) {
                if (!$this->responseDeferred) {
                    $this->responseDeferred = new Deferred;
                }
                yield $this->responseDeferred->promise();
                $this->responseDeferred = new Deferred;

                list($result, $requestId) = $this->queue->shift();
            }

            list($result, $requestId) = $this->queue->shift();

            $result = yield $result->getBody();

            switch ($this->nameserver->getType()) {
                case Nameserver::RFC8484_GET:
                case Nameserver::RFC8484_POST:
                    return $this->decoder->decode($result);
                case Nameserver::GOOGLE_JSON:
                    return $this->decoder->decode($result, $requestId);
            }
        });
    }

}
