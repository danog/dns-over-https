<?php
/**
 * Creates Decoder objects
 *
 * @author Chris Wright <https://github.com/DaveRandom>
 * @copyright Copyright (c) Chris Wright <https://github.com/DaveRandom>,
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 */
namespace Amp\DoH;

use \LibDNS\Packets\PacketFactory;
use \LibDNS\Messages\MessageFactory;
use \LibDNS\Records\RecordCollectionFactory;
use \LibDNS\Records\QuestionFactory;
use \LibDNS\Records\ResourceBuilder;
use \LibDNS\Records\ResourceFactory;
use \LibDNS\Records\RDataBuilder;
use \LibDNS\Records\RDataFactory;
use \LibDNS\Records\Types\TypeBuilder;
use \LibDNS\Records\Types\TypeFactory;
use \LibDNS\Records\TypeDefinitions\TypeDefinitionManager;
use \LibDNS\Records\TypeDefinitions\TypeDefinitionFactory;
use \LibDNS\Records\TypeDefinitions\FieldDefinitionFactory;
use LibDNS\Decoder\DecodingContextFactory;

/**
 * Creates JsonDecoder objects
 *
 * @author Chris Wright <https://github.com/DaveRandom>
 */
class JsonDecoderFactory
{
    /**
     * Create a new JsonDecoder object
     *
     * @param \LibDNS\Records\TypeDefinitions\TypeDefinitionManager $typeDefinitionManager
     * @return JsonDecoder
     */
    public function create(TypeDefinitionManager $typeDefinitionManager = null): JsonDecoder
    {
        $typeBuilder = new TypeBuilder(new TypeFactory);

        return new JsonDecoder(
            new PacketFactory,
            new MessageFactory(new RecordCollectionFactory),
            new QuestionFactory,
            new ResourceBuilder(
                new ResourceFactory,
                new RDataBuilder(
                    new RDataFactory,
                    $typeBuilder
                ),
                $typeDefinitionManager ?: new TypeDefinitionManager(
                    new TypeDefinitionFactory,
                    new FieldDefinitionFactory
                )
            ),
            $typeBuilder,
            new DecodingContextFactory
        );
    }
}
