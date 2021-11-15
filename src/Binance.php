<?php declare(strict_types=1);

namespace Lavrenov\ExchangeAPI;

use Exception;
use JsonException;
use Lavrenov\ExchangeAPI\Base\Exchange;

class Binance extends Exchange
{
    protected const API_URL = 'https://api.binance.com/api/v3/';

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

    /**
     * @throws JsonException
     */
    private function parseResult($result)
    {
        return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array
     * @throws JsonException
     * @throws Exception
     */
    public function getExchangeInfo(): array
    {
        $uri = 'exchangeInfo';
        $result = $this->request($uri);

        return $this->parseResult($result);
    }

    /**
     * @param $symbol
     * @param $interval
     * @param int $limit
     * @return array
     * @throws JsonException
     * @throws Exception
     */
    public function getCandles($symbol, $interval, int $limit = 500): array
    {
        $uri = 'klines';

        $result = $this->request($uri, [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
        ]);

        return $this->parseResult($result);
    }
}