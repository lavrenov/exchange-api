<?php declare(strict_types=1);

namespace Lavrenov\ExchangeAPI\Base;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface;

class Exchange extends Singleton
{
    protected const API_URL = '';
    private $client;

    public function init(): void
    {
        parent::init();

        $options = [
            'base_uri' => static::API_URL
        ];

        $this->client = new Client($options);
    }

    /**
     * @param string $relativeUri
     * @param array $params
     * @return string
     * @throws Exception
     */
    public function request(string $relativeUri, array $params = []): string
    {
        try {
            $uri = $relativeUri;

            $options = [
                'query' => $params
            ];

            $response = $this->doRequest($uri, $options);
        } catch (TransferException $e) {
            $message = 'Binance is not available. ' . $e->getMessage();
            throw new Exception($message, $e->getCode(), $e);
        }

        return $response->getBody()->getContents();
    }

    /**
     * @param $uri
     * @param array $options
     * @return ResponseInterface
     */
    protected function doRequest($uri, array $options): ResponseInterface
    {
        return $this->client->get($uri, $options);
    }
}