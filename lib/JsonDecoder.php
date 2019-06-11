<?php
namespace Amp\DoH;

use LibDNS\Decoder\DecodingContextFactory;
use LibDNS\Messages\Message;
use LibDNS\Messages\MessageFactory;
use LibDNS\Packets\Packet;
use LibDNS\Packets\PacketFactory;
use LibDNS\Records\Question;
use LibDNS\Records\QuestionFactory;
use LibDNS\Records\Resource;
use LibDNS\Records\ResourceBuilder;
use LibDNS\Records\Types\Anything;
use LibDNS\Records\Types\BitMap;
use LibDNS\Records\Types\Char;
use LibDNS\Records\Types\CharacterString;
use LibDNS\Records\Types\DomainName;
use LibDNS\Records\Types\IPv4Address;
use LibDNS\Records\Types\IPv6Address;
use LibDNS\Records\Types\Long;
use LibDNS\Records\Types\Short;
use LibDNS\Records\Types\Type;
use LibDNS\Records\Types\TypeBuilder;
use LibDNS\Records\Types\Types;
use LibDNS\Messages\MessageTypes;
use Amp\Dns\DnsException;

/**
 * Decodes JSON DNS strings to Message objects
 *
 * @author Daniil Gentili <https://daniil.it>,  Chris Wright <https://github.com/DaveRandom>
 */
class JsonDecoder
{
    /**
     * @var \LibDNS\Packets\PacketFactory
     */
    private $packetFactory;

    /**
     * @var \LibDNS\Messages\MessageFactory
     */
    private $messageFactory;

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

    /**
     * @var \LibDNS\Decoder\DecodingContextFactory
     */
    private $decodingContextFactory;

    /**
     * Constructor
     *
     * @param \LibDNS\Packets\PacketFactory $packetFactory
     * @param \LibDNS\Messages\MessageFactory $messageFactory
     * @param \LibDNS\Records\QuestionFactory $questionFactory
     * @param \LibDNS\Records\ResourceBuilder $resourceBuilder
     * @param \LibDNS\Records\Types\TypeBuilder $typeBuilder
     * @param \LibDNS\Decoder\DecodingContextFactory $decodingContextFactory
     * @param bool $allowTrailingData
     */
    public function __construct(
        PacketFactory $packetFactory,
        MessageFactory $messageFactory,
        QuestionFactory $questionFactory,
        ResourceBuilder $resourceBuilder,
        TypeBuilder $typeBuilder,
        DecodingContextFactory $decodingContextFactory
    ) {
        $this->packetFactory = $packetFactory;
        $this->messageFactory = $messageFactory;
        $this->questionFactory = $questionFactory;
        $this->resourceBuilder = $resourceBuilder;
        $this->typeBuilder = $typeBuilder;
        $this->decodingContextFactory = $decodingContextFactory;
    }
    /**
     * Decode a question record
     *
     *
     * @return \LibDNS\Records\Question
     * @throws \UnexpectedValueException When the record is invalid
     */
    private function decodeQuestionRecord(array $record): Question
    {
        /** @var \LibDNS\Records\Types\DomainName $domainName */
        $domainName = $this->typeBuilder->build(Types::DOMAIN_NAME);
        $labels = explode('.', $record['name']);
        if (!empty($last = array_pop($labels))) {
            $labels[] = $last;
        }
        $domainName->setLabels($labels);

        $question = $this->questionFactory->create($record['type']);
        $question->setName($domainName);
        //$question->setClass($meta['class']);
        return $question;
    }

    /**
     * Decode a resource record
     *
     *
     * @return \LibDNS\Records\Resource
     * @throws \UnexpectedValueException When the record is invalid
     * @throws \InvalidArgumentException When a type subtype is unknown
     */
    private function decodeResourceRecord(array $record): Resource
    {
        /** @var \LibDNS\Records\Types\DomainName $domainName */
        $domainName = $this->typeBuilder->build(Types::DOMAIN_NAME);
        $labels = explode('.', $record['name']);
        if (!empty($last = array_pop($labels))) {
            $labels[] = $last;
        }
        $domainName->setLabels($labels);
        $resource = $this->resourceBuilder->build($record['type']);
        $resource->setName($domainName);
        //$resource->setClass($meta['class']);
        $resource->setTTL($record['TTL']);

        $data = $resource->getData();

        $fieldDef = $index = null;
        foreach ($resource->getData()->getTypeDefinition() as $index => $fieldDef) {
            $field = $this->typeBuilder->build($fieldDef->getType());
            $this->decodeType($field, $record['data']);
            $data->setField($index, $field);
            break; // For now parse only one field
        }

        return $resource;
    }
    /**
     * Decode a Type field
     *
     *
     * @param \LibDNS\Records\Types\Type $type The object to populate with the result
     * @throws \UnexpectedValueException When the packet data is invalid
     * @throws \InvalidArgumentException When the Type subtype is unknown
     */
    private function decodeType(Type $type, $data)
    {
        if ($type instanceof Anything) {
            $this->decodeAnything($type, $data);
        } else if ($type instanceof BitMap) {
            $this->decodeBitMap($type, $data);
        } else if ($type instanceof Char) {
            $this->decodeChar($type, $data);
        } else if ($type instanceof CharacterString) {
            $this->decodeCharacterString($type, $data);
        } else if ($type instanceof DomainName) {
            $this->decodeDomainName($type, $data);
        } else if ($type instanceof IPv4Address) {
            $this->decodeIPv4Address($type, $data);
        } else if ($type instanceof IPv6Address) {
            $this->decodeIPv6Address($type, $data);
        } else if ($type instanceof Long) {
            $this->decodeLong($type, $data);
        } else if ($type instanceof Short) {
            $this->decodeShort($type, $data);
        } else {
            throw new \InvalidArgumentException('Unknown Type '.\get_class($type));
        }
    }
    /**
     * Decode an Anything field
     *
     *
     * @param \LibDNS\Records\Types\Anything $anything The object to populate with the result
     * @param int $length
     * @return int The number of packet bytes consumed by the operation
     * @throws \UnexpectedValueException When the packet data is invalid
     */
    private function decodeAnything(Anything $anything, $data)
    {
        $anything->setValue(hex2bin($data));
    }

    /**
     * Decode a BitMap field
     *
     *
     * @param \LibDNS\Records\Types\BitMap $bitMap The object to populate with the result
     * @param int $length
     * @return int The number of packet bytes consumed by the operation
     * @throws \UnexpectedValueException When the packet data is invalid
     */
    private function decodeBitMap(BitMap $bitMap, $data)
    {
        $bitMap->setValue(hex2bin($data));
    }

    /**
     * Decode a Char field
     *
     *
     * @param \LibDNS\Records\Types\Char $char The object to populate with the result
     * @return int The number of packet bytes consumed by the operation
     * @throws \UnexpectedValueException When the packet data is invalid
     */
    private function decodeChar(Char $char, $result)
    {
        $value = \unpack('C', $result)[1];
        $char->setValue($value);
    }

    /**
     * Decode a CharacterString field
     *
     *
     * @param \LibDNS\Records\Types\CharacterString $characterString The object to populate with the result
     * @return int The number of packet bytes consumed by the operation
     * @throws \UnexpectedValueException When the packet data is invalid
     */
    private function decodeCharacterString(CharacterString $characterString, $result)
    {
        $characterString->setValue($result);
    }

    /**
     * Decode a DomainName field
     *
     *
     * @param \LibDNS\Records\Types\DomainName $domainName The object to populate with the result
     * @return int The number of packet bytes consumed by the operation
     * @throws \UnexpectedValueException When the packet data is invalid
     */
    private function decodeDomainName(DomainName $domainName, $result)
    {
        $labels = explode('.', $result);
        if (!empty($last = array_pop($labels))) {
            $labels[] = $last;
        }

        $domainName->setLabels($labels);
    }

    /**
     * Decode an IPv4Address field
     *
     *
     * @param \LibDNS\Records\Types\IPv4Address $ipv4Address The object to populate with the result
     * @return int The number of packet bytes consumed by the operation
     * @throws \UnexpectedValueException When the packet data is invalid
     */
    private function decodeIPv4Address(IPv4Address $ipv4Address, $result)
    {
        $octets = \unpack('C4', inet_pton($result));
        $ipv4Address->setOctets($octets);
    }

    /**
     * Decode an IPv6Address field
     *
     *
     * @param \LibDNS\Records\Types\IPv6Address $ipv6Address The object to populate with the result
     * @return int The number of packet bytes consumed by the operation
     * @throws \UnexpectedValueException When the packet data is invalid
     */
    private function decodeIPv6Address(IPv6Address $ipv6Address, $result)
    {
        $shorts = \unpack('n8', inet_pton($result));
        $ipv6Address->setShorts($shorts);
    }

    /**
     * Decode a Long field
     *
     *
     * @param \LibDNS\Records\Types\Long $long The object to populate with the result
     * @return int The number of packet bytes consumed by the operation
     * @throws \UnexpectedValueException When the packet data is invalid
     */
    private function decodeLong(Long $long, $result)
    {
        $long->setValue((int) $result);
    }

    /**
     * Decode a Short field
     *
     *
     * @param \LibDNS\Records\Types\Short $short The object to populate with the result
     * @return int The number of packet bytes consumed by the operation
     * @throws \UnexpectedValueException When the packet data is invalid
     */
    private function decodeShort(Short $short, $result)
    {
        $short->setValue((int) $result);
    }

    /**
     * Decode a Message from JSON-encoded string
     *
     * @param string $data The data string to decode
     * @param int $requestId The message ID to set
     * @return \LibDNS\Messages\Message
     * @throws \UnexpectedValueException When the packet data is invalid
     * @throws \InvalidArgumentException When a type subtype is unknown
     */
    public function decode(string $result, int $requestId): Message
    {
        $result = \json_decode($result, true);
        if ($result === false) {
            $error = \json_last_error_msg();
            throw new DnsException("Could not decode JSON DNS payload ($error)");
        }
        if (!isset($result['Status'], $result['TC'], $result['RD'], $result['RA'])) {
            throw new DnsException('Wrong reply from server, missing required fields');
        }

        $message = $this->messageFactory->create();
        $decodingContext = $this->decodingContextFactory->create($this->packetFactory->create());

        //$message->isAuthoritative(true);
        $message->setType(MessageTypes::RESPONSE);
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
            $questionRecords->add($this->decodeQuestionRecord($result['Question'][$i]));
        }

        $answerRecords = $message->getAnswerRecords();
        $expected = $decodingContext->getExpectedAnswerRecords();
        for ($i = 0; $i < $expected; $i++) {
            $answerRecords->add($this->decodeResourceRecord($result['Answer'][$i]));
        }

        $authorityRecords = $message->getAuthorityRecords();
        $expected = $decodingContext->getExpectedAuthorityRecords();
        for ($i = 0; $i < $expected; $i++) {
            $authorityRecords->add($this->decodeResourceRecord($result['Authority'][$i]));
        }

        $additionalRecords = $message->getAdditionalRecords();
        $expected = $decodingContext->getExpectedAdditionalRecords();
        for ($i = 0; $i < $expected; $i++) {
            $additionalRecords->add($this->decodeResourceRecord($result['Additional'][$i]));
        }
        return $message;
    }
}
