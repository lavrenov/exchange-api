<?php declare(strict_types=1);

namespace Lavrenov\ExchangeAPI;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Lavrenov\ExchangeAPI\Base\Exchange;
use function Ratchet\Client\connect;

class Binance extends Exchange
{
    protected const API_URL = 'https://api.binance.com/api/v3/';
    protected const FAPI_URL = 'https://fapi.binance.com/fapi/v1/';
    protected const STREAM_API_URL = 'wss://stream.binance.com:9443/ws/';

    public const TIMEFRAME_1m = '1m';
    public const TIMEFRAME_3m = '3m';
    public const TIMEFRAME_5m = '5m';
    public const TIMEFRAME_15m = '15m';
    public const TIMEFRAME_30m = '30m';
    public const TIMEFRAME_1h = '1h';
    public const TIMEFRAME_2h = '2h';
    public const TIMEFRAME_4h = '4h';
    public const TIMEFRAME_6h = '6h';
    public const TIMEFRAME_8h = '8h';
    public const TIMEFRAME_12h = '12h';
    public const TIMEFRAME_1d = '1d';
    public const TIMEFRAME_3d = '3d';
    public const TIMEFRAME_1w = '1w';
    public const TIMEFRAME_1M = '1M';

    public static $apiKey;
    public static $secret;

    public $subscriptions = [];

    /**
     * @return int
     */
    public function getLimitUsedWeight(): int
    {
        return (int)($this->getLastResponse()->getHeader('X-MBX-USED-WEIGHT-1M')[0] ?? '0');
    }

    /**
     * @return int
     */
    public function getLimitOrderCount(): int
    {
        return (int)($this->getLastResponse()->getHeader('X-MBX-ORDER-COUNT-10S')[0] ?? '0');
    }

    public function setToken($apiKey, $secret): void
    {
        self::$apiKey = $apiKey;
        self::$secret = $secret;
    }

    /**
     * @throws JsonException|Exception
     */
    private function parseResult($result)
    {
        $code = $this->getLastResponse()->getStatusCode();

        if ($code === 404) {
            //@codeCoverageIgnoreStart
            throw new Exception('Not Found', 404);
            //@codeCoverageIgnoreEnd
        }

        $result = json_decode($result, true, 512, JSON_THROW_ON_ERROR);

        if ($code >= 400) {
            throw new Exception($result['msg'], $result['code']);
        }

        return $result;
    }

    /**
     * @return array
     * @throws GuzzleException|JsonException
     */
    public function getExchangeInfo(): array
    {
        $uri = 'exchangeInfo';
        $result = $this->request('GET', $uri);

        return $this->parseResult($result);
    }

    /**
     * @param $symbol
     * @param $timeFrame
     * @param int $limit
     * @return array
     * @throws GuzzleException|JsonException
     */
    public function getCandles($symbol, $timeFrame, int $limit = 500): array
    {
        $uri = 'klines';

        $result = $this->request('GET', $uri, [
            'symbol' => $symbol,
            'interval' => $timeFrame,
            'limit' => $limit,
        ]);

        return $this->parseResult($result);
    }

    /**
     * @param array $params
     * @return array
     * @throws GuzzleException
     * @throws JsonException
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