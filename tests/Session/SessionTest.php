<?php

namespace Lune\Tests\Session;

use Lune\Session\Session;
use Lune\Session\SessionStorage;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    private function createMockSessionStorage(&$mockStorage)
    {
        $mock = $this->getMockBuilder(SessionStorage::class)->getMock();

        $mock->method("id")->willReturn("id");

        $mock->method("has")->willReturnCallback(function ($key) use (&$mockStorage) {
            return isset($mockStorage[$key]);
        });

        $mock->method("get")->willReturnCallback(function ($key) use (&$mockStorage) {
            return $mockStorage[$key] ?? null;
        });

        $mock->method("set")->willReturnCallback(function ($key, $value) use (&$mockStorage) {
            $mockStorage[$key] = $value;
        });

        $mock->method("remove")->willReturnCallback(function ($key) use (&$mockStorage) {
            unset($mockStorage[$key]);
        });

        return $mock;
    }

    public function testAgeFlashData()
    {
        $mockStorage = [];
        $mock = $this->createMockSessionStorage($mockStorage);

        $s1 = new Session($mock);
        $s1->set("test", "hello");
        $this->assertTrue(isset($mockStorage["test"]));

        $this->assertEquals(["old" => [], "new" => []], $mockStorage[$s1::FLASH_KEY]);
        $s1->flash("alert", "some alert");
        $this->assertEquals(["old" => [], "new" => ["alert"]], $mockStorage[$s1::FLASH_KEY]);

        $s1->__destruct();
        $this->assertTrue(isset($mockStorage["alert"]));
        $this->assertEquals(["old" => ["alert"], "new" => []], $mockStorage[$s1::FLASH_KEY]);

        $s2 = new Session($mock);
        $this->assertEquals(["old" => ["alert"], "new" => []], $mockStorage[$s2::FLASH_KEY]);
        $this->assertTrue(isset($mockStorage["alert"]));

        $s2->__destruct();
        $this->assertEquals(["old" => [], "new" => []], $mockStorage[$s2::FLASH_KEY]);
        $this->assertFalse(isset($mockStorage["alert"]));
    }
}
