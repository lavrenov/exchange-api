<?php

use Lavrenov\ExchangeAPI\Base\Exchange;
use Lavrenov\ExchangeAPI\Binance;
use PHPUnit\Framework\TestCase;

class BinanceTest extends TestCase
{
    private $binance;

    protected function setUp(): void
    {
        $this->binance = Binance::getInstance();
        $apiKey = getenv('API_KEY');
        if (!$apiKey) {
            $apiKey = '';
        }
        $secret = getenv('SECRET');
        if (!$secret) {
            $secret = '';
        }
        $this->binance->setToken($apiKey, $secret);
    }

    /**
     * @throws Exception
     */
    public function testExchangeInfo(): void
    {
        $exchangeInfo = $this->binance->getExchangeInfo(['api' => true]);
        self::assertIsArray($exchangeInfo);
        self::assertArrayHasKey('timezone', $exchangeInfo);
        self::assertArrayHasKey('serverTime', $exchangeInfo);

        $exchangeInfo = $this->binance->getExchangeInfo(['fapi' => true]);
        self::assertIsArray($exchangeInfo);
        self::assertArrayHasKey('timezone', $exchangeInfo);
        self::assertArrayHasKey('serverTime', $exchangeInfo);
    }

    /**
     * @throws Exception
     */
    public function testCandles(): void
    {
        $candles = $this->binance->getCandles('BTCUSDT', Exchange::TIMEFRAME_1h, 500, ['api' => true]);
        self::assertIsArray($candles);
        self::assertCount(500, $candles);

        $candles = $this->binance->getCandles('BTCUSDT', Exchange::TIMEFRAME_1h, 500, ['fapi' => true]);
        self::assertIsArray($candles);
        self::assertCount(500, $candles);
    }

    /**
     * @throws Exception
     */
    public function testAccount(): void
    {
        $account = $this->binance->getAccount(['api' => true]);
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

    public function testWithoutApiKey(): void
    {
        $this->binance->setToken('', '');
        $this->expectException(Exception::class);
        $this->binance->getAccount();
    }

	public function testExchanges(): void
	{
		foreach (Exchange::$exchanges as $exchangeId) {
			$exchangeClass = "\\Lavrenov\\ExchangeAPI\\$exchangeId";
			$exchange = $exchangeClass::getInstance();

			self::assertEquals($exchange::getClass(), $exchangeId);
		}
	}
}