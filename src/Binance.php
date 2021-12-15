<?php declare(strict_types=1);

namespace Lavrenov\ExchangeAPI;

use Exception;
use JsonException;
use Lavrenov\ExchangeAPI\Base\Exchange;
use function Ratchet\Client\connect;

class Binance extends Exchange
{
	public $name = 'Binance';

	protected const SPOT_URL = 'https://api.binance.com/api/v3/';
	protected const FUTURE_URL = 'https://fapi.binance.com/fapi/v1/';
	protected const SOCKET_URL = 'wss://stream.binance.com:9443/ws/';

	public static $apiKey;
	public static $secret;

	private $subscriptions = [];

	private $intervalLetter = [
		'SECOND' => 'S',
		'MINUTE' => 'M',
		'HOUR' => 'H',
		'DAY' => 'D'
	];

	/**
	 * @return int
	 */
	public function getLimitUsedWeight(): int
	{
		$lastResponse = $this->getLastResponse();
		$result = 0;
		if ($lastResponse) {
			$result = (int)($lastResponse->getHeader('X-MBX-USED-WEIGHT-1M')[0] ?? 0);
		}

		return $result;
	}

	/**
	 * @return int
	 */
	public function getLimitOrderCount(): int
	{
		$lastResponse = $this->getLastResponse();
		$result = 0;
		if ($lastResponse) {
			$result = (int)($lastResponse->getHeader('X-MBX-ORDER-COUNT-10S')[0] ?? 0);
		}

		return $result;
	}

	public function setToken($apiKey, $secret): void
	{
		self::$apiKey = $apiKey;
		self::$secret = $secret;
	}

	/**
	 * @throws Exception
	 */
	private function parseResult($result)
	{
		$lastResponse = $this->getLastResponse();
		if ($lastResponse === null) {
			throw new Exception('Not Implemented', 501);
		}

		$code = $lastResponse->getStatusCode();

		if ($code === 404) {
			throw new Exception('Not Found', 404);
		}

		try {
			$result = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new Exception($e->getMessage(), $e->getCode());
		}

		if ($code >= 400) {
			throw new Exception($result['msg'], $result['code']);
		}

		return $result;
	}

	private function prepareLimits($rateLimits): array
	{
		foreach ($rateLimits as $key => $rateLimit) {
			if (!in_array($rateLimit['rateLimitType'], ['REQUEST_WEIGHT', 'ORDERS'])) {
				unset($rateLimits[$key]);
				continue;
			}
			if ($rateLimit['rateLimitType'] === 'REQUEST_WEIGHT') {
				$rateLimit['rateLimitType'] = 'USED-WEIGHT';
			}
			if ($rateLimit['rateLimitType'] === 'ORDERS') {
				$rateLimit['rateLimitType'] = 'ORDER-COUNT';
			}
			$rateLimits[$rateLimit['rateLimitType'] . '-' . $rateLimit['intervalNum'] . $this->intervalLetter[$rateLimit['interval']]] = $rateLimit['limit'];
			unset($rateLimits[$key]);
		}
		ksort($rateLimits);

		return $rateLimits;
	}

	private function prepareSymbols($symbols): array
	{
		foreach ($symbols as $symbolId => $symbol) {
			foreach ($symbol['filters'] as $filterId => $filter) {
				$symbol['filters'][$filter['filterType']] = $filter;
				unset($symbol['filters'][$filterId]);
			}

			$pricePrecision = explode('.', rtrim($symbol['filters']['PRICE_FILTER']['tickSize'], '0'));
			$pricePrecision = strlen(end($pricePrecision));

			$amountPrecision = explode('.', rtrim($symbol['filters']['LOT_SIZE']['stepSize'], '0'));
			$amountPrecision = strlen(end($amountPrecision));

			$symbols[$symbol['symbol']] = [
				'id' => $symbol['symbol'],
				'name' => $symbol['baseAsset'] . '/' . $symbol['quoteAsset'],
				'precision' => [
					'amount' => $amountPrecision,
					'price' => $pricePrecision,
				],
				'limits' => [
					'amount' => [
						'min' => $symbol['filters']['LOT_SIZE']['minQty'] ?? 0,
						'max' => $symbol['filters']['LOT_SIZE']['maxQty'] ?? 0,
					],
					'cost' => [
						'min' => $symbol['filters']['MIN_NOTIONAL']['minNotional'] ?? $symbol['filters']['MIN_NOTIONAL']['notional'] ?? 0,
					],
				],
				'active' => $symbol['status'] === 'TRADING',
			];
			unset($symbols[$symbolId]);
		}
		ksort($symbols);

		return $symbols;
	}

	/**
	 * @param array $params
	 * @return array
	 * @throws Exception
	 */
	public function getExchangeInfo(array $params = []): array
	{
		$uri = 'exchangeInfo';
		$result = $this->request('GET', $uri, $params);
		$result = $this->parseResult($result);
		$result['rateLimits'] = $this->prepareLimits($result['rateLimits']);
		$result['symbols'] = $this->prepareSymbols($result['symbols']);

		return $result;
	}

	/**
	 * @param $symbol
	 * @param $timeFrame
	 * @param int $limit
	 * @param array $params
	 * @return array
	 * @throws Exception
	 */
	public function getCandles($symbol, $timeFrame, int $limit = 500, array $params = []): array
	{
		$uri = 'klines';
		$params = array_merge([
			'symbol' => $symbol,
			'interval' => $timeFrame,
			'limit' => $limit,
		], $params);
		$result = $this->request('GET', $uri, $params);

		return $this->parseResult($result);
	}

	/**
	 * @param array $params
	 * @return array
	 * @throws Exception
	 */
	public function getAccount(array $params = []): array
	{
		$uri = 'account';
		$result = $this->request('GET', $uri, $params, true);

		return $this->parseResult($result);
	}

	/**
	 * @param $symbol
	 * @param $orderId
	 * @param array $params
	 * @return array
	 * @throws Exception
	 */
	public function getOrder($symbol, $orderId, array $params = []): array
	{
		$uri = 'order';
		$params = array_merge([
			'symbol' => $symbol,
			'orderId' => $orderId,
		], $params);
		$result = $this->request('GET', $uri, $params, true);
		$result = $this->parseResult($result);

		$id = $result['orderId'];
		$datetime = $this->iso8601($result['time'] ?? $result['transactTime'] ?? $result['updateTime'] ?? null);
		$symbol = $result['symbol'];
		$type = strtolower($result['type']);
		$side = strtolower($result['side']);
		$price = $result['price'] ?? 0;
		$amount = $result['executedQty'] ?? $result['origQty'] ?? 0;
		$cost = $result['cumBase'] ?? $result['cummulativeQuoteQty'] ?? $result['cumQuote'] ?? 0;
		$status = strtolower($result['status']);
		$price = $price > 0 ? $price : $cost / $amount;

		return [
			'id' => $id,
			'datetime' => $datetime,
			'symbol' => $symbol,
			'type' => $type,
			'side' => $side,
			'price' => $price,
			'amount' => $amount,
			'cost' => $cost,
			'status' => $status === 'filled' ? 'closed' : $status,
		];
	}

	/**
	 * @param $symbol
	 * @param $timeFrame
	 * @param callable $callback
	 * @return void
	 * @codeCoverageIgnore
	 */
	public function subscribeCandle($symbol, $timeFrame, callable $callback): void
	{
		$uri = strtolower($symbol) . '@kline_' . $timeFrame;
		$this->subscriptions[$uri] = true;
		$this->socketConnect($uri, $callback);
	}

	/**
	 * @param $symbol
	 * @param $timeFrame
	 * @return void
	 * @codeCoverageIgnore
	 */
	public function unsubscribeCandle($symbol, $timeFrame): void
	{
		$uri = strtolower($symbol) . '@kline_' . $timeFrame;
		$this->subscriptions[$uri] = false;
	}

	/**
	 * @param $uri
	 * @param $callback
	 * @return void
	 * @codeCoverageIgnore
	 */
	private function socketConnect($uri, $callback): void
	{
		connect(self::SOCKET_URL . $uri)->then(function ($ws) use ($callback, $uri) {
			$ws->on('message', function ($data) use ($ws, $callback, $uri) {
				if ($this->subscriptions[$uri] === false) {
					$ws->close();
					return;
				}
				$data = json_decode((string)$data, true, 512, JSON_THROW_ON_ERROR);
				if ($callback) {
					$callback($data);
				}
			});
		});
	}
}