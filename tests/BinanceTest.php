<?php

use GuzzleHttp\Exception\GuzzleException;
use Lavrenov\ExchangeAPI\Binance;
use PHPUnit\Framework\TestCase;

class BinanceTest extends TestCase
{
    private $binance;

    protected function setUp(): void
    {
        $this->binance = Binance::getInstance();
        $apiKey = getenv('API_KEY') ?? '';
        $secret = getenv('SECRET') ?? '';
        $this->binance->setToken($apiKey, $secret);
    }

    /**
     * @throws GuzzleException|JsonException
     */
    public function testExchangeInfo(): void
    {
        $exchangeInfo = $this->binance->getExchangeInfo();

        self::assertIsArray($exchangeInfo);
        self::assertArrayHasKey('timezone', $exchangeInfo);
        self::assertLessThanOrEqual(60, abs(time() - $exchangeInfo['serverTime'] / 1000));
    }

    /**
     * @throws GuzzleException|JsonException
     */
    public function testCandles(): void
    {
        $candles = $this->binance->getCandles('BTCUSDT', Binance::TIMEFRAME_1h);

        self::assertIsArray($candles);
        self::assertCount(500, $candles);
    }

    /**
     * @throws GuzzleException|JsonException
     */
    public function testAccount(): void
    {
        $account = $this->binance->getAccount();

        self::assertIsArray($account);
        self::assertArrayHasKey('makerCommission', $account);

        $account = $this->binance->getAccount(['fapi' => true]);

        self::assertIsArray($account);
        self::assertArrayHasKey('feeTier', $account);
    }

    public function testLimitUsedWeight(): void
    {
        self::assertIsInt($this->binance->getLimitUsedWeight());
    }

    public function testLimitOrderCount(): void
    {
        self::assertIsInt($this->binance->getLimitOrderCount());
    }

    /**
     * @throws GuzzleException|JsonException
     */
    public function testWithoutApiKey(): void
    {
        $this->binance->setToken('', '');
        $this->expectException(Exception::class);
        $this->binance->getAccount();
    }
}