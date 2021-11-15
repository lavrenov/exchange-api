<?php declare(strict_types=1);

namespace Lavrenov\ExchangeAPI;

use Exception;
use JsonException;
use Lavrenov\ExchangeAPI\Base\Exchange;

class Binance extends Exchange
{
    protected const API_URL = 'https://api.binance.com/api/v3/';

    /**
     * @return array
     * @throws JsonException
     * @throws Exception
     */
    public function getExchangeInfo(): array
    {
        $uri = 'exchangeInfo';
        $result = $this->request($uri);

        return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
    }
}