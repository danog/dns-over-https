<?php declare(strict_types=1);
/**
 * Creates Encoder objects
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

use \LibDNS\Packets\PacketFactory;

/**
 * Creates Encoder objects
 *
 * @category LibDNS
 * @package Encoder
 * @author Chris Wright <https://github.com/DaveRandom>
 */
class QueryEncoderFactory
{
    /**
     * Create a new Encoder object
     *
     * @return \LibDNS\Encoder\Encoder
     */
    public function create(): QueryEncoder
    {
        return new QueryEncoder();
    }
}
