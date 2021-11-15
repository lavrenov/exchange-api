<?php declare(strict_types=1);

namespace Lavrenov\ExchangeAPI\Base;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface;

class Exchange extends Singleton
{
    protected const API_URL = '';

    /* @var Client */
    private $client;

    /* @var ResponseInterface */
    private $lastResponse;

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
    protected function request(string $relativeUri, array $params = []): string
    {
        try {
            $uri = $relativeUri;

            $options = [
                'query' => $params
            ];

            $this->lastResponse = $this->doRequest($uri, $options);
        } catch (TransferException $e) {
            $message = 'Exchange is not available. ' . $e->getMessage();
            throw new Exception($message, $e->getCode(), $e);
        } catch (GuzzleException $e) {
            $message = $e->getMessage();
            throw new Exception($message, $e->getCode(), $e);
        }

        return $this->lastResponse->getBody()->getContents();
    }

    /**
     * @param $uri
     * @param array $options
     * @return ResponseInterface
     * @throws GuzzleException
     */
    protected function doRequest($uri, array $options): ResponseInterface
    {
        return $this->client->get($uri, $options);
    }

    /**
     * @return ResponseInterface
     */
    public function getLastResponse(): ResponseInterface
    {
        return $this->lastResponse;
    }
}