<?php declare(strict_types=1);
/**
 * Encodes Message objects to raw network data
 *
 * PHP version 5.4
 *
 * @category LibDNS
 * @package Encoder
 * @author Chris Wright <https://github.com/DaveRandom>
 * @copyright Copyright (c) Chris Wright <https://github.com/DaveRandom>
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @version 2.0.0
 */
namespace Amp\DoH;

use \LibDNS\Messages\Message;

/**
 * Encodes Message objects to raw network data
 *
 * @category LibDNS
 * @package Encoder
 * @author Chris Wright <https://github.com/DaveRandom>
 */
class QueryEncoder
{
    /**
     * Encode a Message to URL payload
     *
     * @param \LibDNS\Messages\Message $message  The Message to encode
     * @return string
     */
    public function encode(Message $message): string
    {
        return http_build_query([
            'cd' => 0, // Do not disable result validation
            'do' => 0, // Do not send me DNSSEC data
            'type' => $message->getQuestionRecords()->getRecordByIndex(0)->getType(), // Record type being requested
            'name' => implode('.', $message->getQuestionRecords()->getRecordByIndex(0)->getName()->getLabels()), // Record name being requested
            'ct' => 'application/dns-json', // Content-type of request
        ]);
    }
}
