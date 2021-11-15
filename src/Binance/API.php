<?php declare(strict_types=1);

namespace Lavrenov\ExchangeAPI\Binance;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Lavrenov\ExchangeAPI\Base\Exchange;

class API extends Exchange
{
    protected const API_URL = 'https://api.binance.com/api/v3/';
    protected static $instance;

    /**
     * @return array
     * @throws GuzzleException
     */
    public function getExchangeInfo(): array
    {
        $uri = 'exchangeInfo';
        $result = $this->request($uri);
        try {
            $result = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return [];
        }

        return $result;
    }
}