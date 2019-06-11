<?php

namespace Amp\DoH\Test;

use Amp\PHPUnit\TestCase;
use Amp\DoH\JsonDecoderFactory;
use Amp\DoH\JsonDecoder;

class JsonDecoderFactoryTest extends TestCase
{
    public function testJsonDecoderFactoryWorks()
    {
        $this->assertInstanceOf(JsonDecoder::class, (new JsonDecoderFactory)->create());
    }
}