<?php

namespace Amp\DoH\Internal;

use Amp\DoH\DoHException;
use Amp\DoH\Nameserver;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\Request;
use Amp\Promise;
use danog\LibDNSJson\JsonDecoderFactory;
use danog\LibDNSJson\QueryEncoderFactory;
use LibDNS\Decoder\DecoderFactory;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Messages\Message;
use function Amp\call;

/** @internal */
final class HttpsSocket extends Socket
{
    /** @var \Amp\Http\HttpClient */
    private $httpClient;

    /** @var \Amp\DoH\Nameserver */
    private $nameserver;

    /** @var \LibDNS\Encoder\Encoder */
    private $encoder;

    /** @var \LibDNS\Decoder\Decoder */
    private $decoder;

    /** @var \Amp\Deferred */
    private $responseDeferred;

    public static function connect(DelegateHttpClient $httpClient, Nameserver $nameserver): Socket
    {
        return new self($httpClient, $nameserver);
    }

    protected function __construct(DelegateHttpClient $httpClient, Nameserver $nameserver)
    {
        $this->httpClient = $httpClient;
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
                $request = new Request($this->nameserver->getUri().'?'.\http_build_query(['dns' => \base64_encode($data), 'ct' => 'application/dns-message']), "GET");
                $request->setHeader('accept', 'application/dns-message');
                $request->setHeaders($this->nameserver->getHeaders());
                break;
            case Nameserver::RFC8484_POST:
                $data = $this->encoder->encode($message);
                $request = new Request($this->nameserver->getUri(), "POST");
                $request->setBody($data);
                $request->setHeader('content-type', 'application/dns-message');
                $request->setHeader('accept', 'application/dns-message');
                $request->setHeader('content-length', \strlen($data));
                $request->setHeaders($this->nameserver->getHeaders());
                break;
            case Nameserver::GOOGLE_JSON:
                $data = $this->encoder->encode($message);
                $request = new Request($this->nameserver->getUri().'?'.$data, "GET");
                $request->setHeader('accept', 'application/dns-json');
                $request->setHeaders($this->nameserver->getHeaders());
                break;
        }
        $response = $this->httpClient->request($request);
        return call(function () use ($response, $id) {
            $response = yield $response;
            if ($response->getStatus() !== 200) {
                throw new DoHException("HTTP result !== 200: ".$response->getStatus()." ".$response->getReason(), $response->getStatus());
            }
            $response = yield $response->getBody()->buffer();

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
