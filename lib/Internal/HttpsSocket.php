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
use LibDNS\Decoder\DecodingContextFactory;
use LibDNS\Encoder\EncoderFactory;
use LibDNS\Messages\Message;
use LibDNS\Messages\MessageFactory;
use LibDNS\Packets\PacketFactory;
use LibDNS\Records\Types\Types;
use LibDNS\Decoder\DecodingContext;
use LibDNS\Records\QuestionFactory;
use LibDNS\Records\ResourceBuilderFactory;
use LibDNS\Records\Types\TypeBuilder;
use LibDNS\Records\Question;
use LibDNS\Records\Resource;

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

    /** @var \LibDNS\Messages\MessageFactory */
    private $messageFactory;

    /** @var \LibDNS\Decoder\DecodingContextFactory */
    private $decodingContextFactory;

    /**
     * @var \LibDNS\Packets\PacketFactory
     */
    private $packetFactory;

    /**
     * @var \LibDNS\Records\QuestionFactory
     */
    private $questionFactory;

    /**
     * @var \LibDNS\Records\ResourceBuilder
     */
    private $resourceBuilder;

    /**
     * @var \LibDNS\Records\Types\TypeBuilder
     */
    private $typeBuilder;

    /** @var \Amp\Artax\Response[] */
    private $queue;

    /** @var \Amp\Deferred */
    private $responseDeferred;

    public static function connect(Client $artax, Nameserver $nameserver): self
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
            $this->messageFactory = new MessageFactory;
            $this->decodingContextFactory = new DecodingContextFactory;
            $this->packetFactory = new PacketFactory;
            $this->questionFactory = new QuestionFactory;
            $this->resourceBuilder = (new ResourceBuilderFactory)->create();
            $this->typeBuilder = new TypeBuilder;
        }

        parent::__construct();
    }

    protected function send(Message $message): Promise
    {
        $id = $message->getID();
        $data = $this->encoder->encode($message);

        switch ($this->nameserver->getType()) {
            case Nameserver::RFC8484_GET:
                $request = (new Request($this->nameserver->getUri().'?'.http_build_query(['dns' => $data]), "GET"))
                    ->withHeader('accept', 'application/dns-message')
                    ->withHeaders($this->nameserver->getHeaders());
                break;
            case Nameserver::RFC8484_POST:
                $request = (new Request($this->nameserver->getUri(), "POST"))
                    ->withBody($data)
                    ->withHeader('content-type', 'application/dns-message')
                    ->withHeader('accept', 'application/dns-message')
                    ->withHeaders($this->nameserver->getHeaders());
                break;
            case Nameserver::GOOGLE_JSON:
                $param = [
                    'cd' => 0, // Do not disable result validation
                    'do' => 0, // Do not send me DNSSEC data
                    'type' => $message->getType(), // Record type being requested
                    'name' => $message->getQuestionRecords()->getRecordByIndex(0),
                ];
                $request = (new Request($this->nameserver->getUri().'?'.http_build_query($param), "GET"))
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
            if ($this->queue->isEmpty()) {
                if (!$this->responseDeferred) {
                    $this->responseDeferred = new Deferred;
                }
                yield $this->responseDeferred;
                $this->responseDeferred = new Deferred;

                list($result, $requestId) = $this->queue->shift();
            }

            list($result, $requestId) = $this->queue->shift();

            switch ($this->nameserver->getType()) {
                case Nameserver::RFC8484_GET:
                case Nameserver::RFC8484_POST:
                    return $this->decoder->decode(yield $result->getBody());
                case Nameserver::GOOGLE_JSON:
                    $result = json_decode($result, true);

                    $message = $this->messageFactory->create();
                    $decodingContext = $this->decodingContextFactory->create($this->packetFactory->create());

                    //$message->isAuthoritative(true);
                    $message->setID($requestId);
                    $message->setResponseCode($result['Status']);
                    $message->isTruncated($result['TC']);
                    $message->isRecursionDesired($result['RD']);
                    $message->isRecursionAvailable($result['RA']);

                    $decodingContext->setExpectedQuestionRecords(isset($result['Question']) ? count($result['Question']) : 0);
                    $decodingContext->setExpectedAnswerRecords(isset($result['Answer']) ? count($result['Answer']) : 0);
                    $decodingContext->setExpectedAuthorityRecords(0);
                    $decodingContext->setExpectedAdditionalRecords(isset($result['Additional']) ? count($result['Additional']) : 0);

                    $questionRecords = $message->getQuestionRecords();
                    $expected = $decodingContext->getExpectedQuestionRecords();
                    for ($i = 0; $i < $expected; $i++) {
                        $questionRecords->add($this->decodeQuestionRecord($decodingContext, $result['Question'][$i]));
                    }

                    $answerRecords = $message->getAnswerRecords();
                    $expected = $decodingContext->getExpectedAnswerRecords();
                    for ($i = 0; $i < $expected; $i++) {
                        $answerRecords->add($this->decodeResourceRecord($decodingContext, $result['Answer'][$i]));
                    }

                    $authorityRecords = $message->getAuthorityRecords();
                    $expected = $decodingContext->getExpectedAuthorityRecords();
                    for ($i = 0; $i < $expected; $i++) {
                        $authorityRecords->add($this->decodeResourceRecord($decodingContext, $result['Authority'][$i]));
                    }

                    $additionalRecords = $message->getAdditionalRecords();
                    $expected = $decodingContext->getExpectedAdditionalRecords();
                    for ($i = 0; $i < $expected; $i++) {
                        $additionalRecords->add($this->decodeResourceRecord($decodingContext, $result['Additional'][$i]));
                    }

                    return $message;
            }
        });
    }


    /**
     * Decode a question record
     *
     * @param \LibDNS\Decoder\DecodingContext $decodingContext
     * @return \LibDNS\Records\Question
     * @throws \UnexpectedValueException When the record is invalid
     */
    private function decodeQuestionRecord(DecodingContext $decodingContext, array $record): Question
    {
        /** @var \LibDNS\Records\Types\DomainName $domainName */
        $domainName = $this->typeBuilder->build(Types::DOMAIN_NAME);
        $domainName->setLabels(explode('.', $record['name']));

        $question = $this->questionFactory->create($record['type']);
        $question->setName($domainName);
        //$question->setClass($meta['class']);

        return $question;
    }

    /**
     * Decode a resource record
     *
     * @param \LibDNS\Decoder\DecodingContext $decodingContext
     * @return \LibDNS\Records\Resource
     * @throws \UnexpectedValueException When the record is invalid
     * @throws \InvalidArgumentException When a type subtype is unknown
     */
    private function decodeResourceRecord(DecodingContext $decodingContext, array $record): Resource
    {
        /** @var \LibDNS\Records\Types\DomainName $domainName */
        $domainName = $this->typeBuilder->build(Types::DOMAIN_NAME);
        $domainName->setLabels(explode('.', $record['name']));

        $resource = $this->resourceBuilder->build($record['type']);
        $resource->setName($domainName);
        //$resource->setClass($meta['class']);
        $resource->setTTL($record['ttl']);

        $data = $resource->getData();

        $fieldDef = $index = null;
        foreach ($resource->getData()->getTypeDefinition() as $index => $fieldDef) {
            $field = $this->typeBuilder->build($fieldDef->getType());
            $remainingLength -= $this->decodeType($decodingContext, $field, $remainingLength);
            $data->setField($index, $field);
        }

        if ($fieldDef->allowsMultiple()) {
            while ($remainingLength) {
                $field = $this->typeBuilder->build($fieldDef->getType());
                $remainingLength -= $this->decodeType($decodingContext, $field, $remainingLength);
                $data->setField(++$index, $field);
            }
        }

        if ($remainingLength !== 0) {
            throw new \UnexpectedValueException('Decode error: Invalid length for record data section');
        }

        return $resource;
    }
}
