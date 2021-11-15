<?php

use GuzzleHttp\Exception\GuzzleException;
use Lavrenov\ExchangeAPI\Binance\API;
use PHPUnit\Framework\TestCase;

class BinanceTest extends TestCase
{
    /**
     * @throws GuzzleException
     */
    public function testExchangeInfo(): void
    {
        $binance = API::getInstance();
        $exchangeInfo = $binance->getExchangeInfo();
        self::assertIsArray($exchangeInfo);
        self::assertArrayHasKey('timezone', $exchangeInfo);
        self::assertEquals(date('d.m.Y H:i'), date('d.m.Y H:i', $exchangeInfo['serverTime'] / 1000));
    }
}