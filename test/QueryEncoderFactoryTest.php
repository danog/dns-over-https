<?php

namespace Amp\DoH\Test;

use Amp\DoH\QueryEncoder;
use Amp\DoH\QueryEncoderFactory;
use Amp\PHPUnit\TestCase;

class QueryEncoderFactoryTest extends TestCase
{
    public function testQueryEncoderFactoryWorks()
    {
        $this->assertInstanceOf(QueryEncoder::class, (new QueryEncoderFactory)->create());
    }
}
