<?php

use Lavrenov\ExchangeAPI\Binance;
use PHPUnit\Framework\TestCase;

class BinanceTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testExchangeInfo(): void
    {
        $binance = Binance::getInstance();
        $exchangeInfo = $binance->getExchangeInfo();

        self::assertIsArray($exchangeInfo);
        self::assertArrayHasKey('timezone', $exchangeInfo);
        self::assertEquals(date('d.m.Y H:i'), date('d.m.Y H:i', $exchangeInfo['serverTime'] / 1000));
    }

    /**
     * @throws ReflectionException
     */
    public function testRequestException(): void
    {
        $binance = Binance::getInstance();

        $class = new ReflectionClass(Binance::class);
        $method = $class->getMethod('request');
        $method->setAccessible(true);
        $this->expectException(Exception::class);
        $method->invokeArgs($binance, ['relativeUri' => 'wrongUrl']);
    }

    /**
     * @throws JsonException
     */
    public function testCandles(): void
    {
        $binance = Binance::getInstance();
        $candles = $binance->getCandles('BTCUSDT', Binance::TIMEFRAME_1h);

        self::assertIsArray($candles);
        self::assertCount(500, $candles);
    }
}