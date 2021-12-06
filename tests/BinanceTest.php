<?php

use Lavrenov\ExchangeAPI\Base\Exchange;
use Lavrenov\ExchangeAPI\Binance;
use PHPUnit\Framework\TestCase;

class BinanceTest extends TestCase
{
    private $binance;
	private $exchangeTypes = ['spot', 'future'];

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
		foreach ($this->exchangeTypes as $exchangeType) {
        	$exchangeInfo = $this->binance->getExchangeInfo(['type' => $exchangeType]);
        	self::assertIsArray($exchangeInfo);
        	self::assertArrayHasKey('timezone', $exchangeInfo);
        	self::assertArrayHasKey('serverTime', $exchangeInfo);
			self::assertArrayHasKey('rateLimits', $exchangeInfo);
			self::assertArrayHasKey('symbols', $exchangeInfo);
		}
    }

    /**
     * @throws Exception
     */
    public function testCandles(): void
    {
		foreach ($this->exchangeTypes as $exchangeType) {
			$candles = $this->binance->getCandles('BTCUSDT', Exchange::TIMEFRAME_1h, 500, ['type' => $exchangeType]);
			self::assertIsArray($candles);
			self::assertCount(500, $candles);
		}
    }

    /**
     * @throws Exception
     */
    public function testAccount(): void
    {
        $account = $this->binance->getAccount(['type' => 'spot']);
        self::assertIsArray($account);
        self::assertArrayHasKey('makerCommission', $account);

        $account = $this->binance->getAccount(['type' => 'future']);
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