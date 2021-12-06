<?php declare(strict_types=1);

namespace Lavrenov\ExchangeAPI;

use Exception;
use JsonException;
use Lavrenov\ExchangeAPI\Base\Exchange;
use function Ratchet\Client\connect;

class Binance extends Exchange
{
	public $name = 'Binance';

	protected const API_URL = 'https://api.binance.com/api/v3/';
	protected const FAPI_URL = 'https://fapi.binance.com/fapi/v1/';
	protected const STREAM_API_URL = 'wss://stream.binance.com:9443/ws/';

	public static $apiKey;
	public static $secret;

	public $intervalLetter = [
		'SECOND' => 'S',
		'MINUTE' => 'M',
		'HOUR' => 'H',
		'DAY' => 'D'
	];

	public $subscriptions = [];

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

			$pricePrecision = explode('.', $symbol['filters']['PRICE_FILTER']['tickSize']);
			$pricePrecision = strlen(end($pricePrecision));

			$amountPrecision = explode('.', $symbol['filters']['LOT_SIZE']['stepSize']);
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
	 * @param $timeFrame
	 * @param $bars
	 * @param callable $callback
	 * @codeCoverageIgnore
	 */
	public function candle($symbol, $timeFrame, $bars, callable $callback): void
	{
		$uri = strtolower($symbol) . '@kline_' . $timeFrame;

		$this->subscriptions[$uri] = true;
		connect(self::STREAM_API_URL . $uri)->then(function ($ws) use ($callback, $symbol, $bars, $uri) {
			$ws->on('message', function ($data) use ($ws, $callback, $symbol, $bars, $uri) {
				if ($this->subscriptions[$uri] === false) {
					$ws->close();
					return;
				}
				$data = json_decode((string)$data, true, 512, JSON_THROW_ON_ERROR);
				if ($callback) {
					$callback($symbol, $bars, $data);
				}
			});
		});
	}
}