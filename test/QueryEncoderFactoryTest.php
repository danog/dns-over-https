<?php

namespace Amp\DoH\Test;

use Amp\PHPUnit\TestCase;
use Amp\DoH\QueryEncoderFactory;
use Amp\DoH\QueryEncoder;

class QueryEncoderFactoryTest extends TestCase
{
    public function testQueryEncoderFactoryWorks()
    {
        $this->assertInstanceOf(QueryEncoder::class, (new QueryEncoderFactory)->create());
    }
}