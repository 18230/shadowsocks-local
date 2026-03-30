<?php

declare(strict_types=1);

namespace SsLocal\Tests\Unit\Runtime;

use PHPUnit\Framework\TestCase;
use SsLocal\Runtime\IpAccessList;

final class IpAccessListTest extends TestCase
{
    public function testItAllowsAllWhenTheListIsEmpty(): void
    {
        $list = IpAccessList::fromStrings([]);

        self::assertTrue($list->allows('127.0.0.1'));
        self::assertTrue($list->allows('10.0.0.1'));
    }

    public function testItMatchesExactIpsAndCidrs(): void
    {
        $list = IpAccessList::fromStrings(['127.0.0.1', '10.0.0.0/8', '::1']);

        self::assertTrue($list->allows('127.0.0.1'));
        self::assertTrue($list->allows('10.20.30.40'));
        self::assertTrue($list->allows('::1'));
        self::assertFalse($list->allows('192.168.1.1'));
        self::assertFalse($list->allows('2001:db8::1'));
    }
}
