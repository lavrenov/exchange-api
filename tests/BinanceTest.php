<?php

use Lavrenov\ExchangeAPI\Binance;
use PHPUnit\Framework\TestCase;

class BinanceTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testSuccessfulExchangeInfo(): void
    {
        $binance = Binance::getInstance();
        $exchangeInfo = $binance->getExchangeInfo();
        self::assertIsArray($exchangeInfo);
        self::assertArrayHasKey('timezone', $exchangeInfo);
        self::assertEquals(date('d.m.Y H:i'), date('d.m.Y H:i', $exchangeInfo['serverTime'] / 1000));
    }

    public function testRequestException(): void
    {
        $binance = Binance::getInstance();
        $this->expectException(Exception::class);
        $binance->request('wrongUrl');
    }
}