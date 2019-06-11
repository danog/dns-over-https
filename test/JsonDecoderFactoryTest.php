<?php

namespace Amp\DoH\Test;

use Amp\DoH\JsonDecoder;
use Amp\DoH\JsonDecoderFactory;
use Amp\PHPUnit\TestCase;

class JsonDecoderFactoryTest extends TestCase
{
    public function testJsonDecoderFactoryWorks()
    {
        $this->assertInstanceOf(JsonDecoder::class, (new JsonDecoderFactory)->create());
    }
}
