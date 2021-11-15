<?php declare(strict_types=1);

namespace Lavrenov\ExchangeAPI\Base;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface;

class Exchange
{
    protected const API_URL = '';
    protected static $instance;
    private static $extraOptions = [];
    private $requestOptions = [];
    private $client;

    private function __construct()
    {
        $options = [
            'base_uri' => static::API_URL
        ];

        if (isset(self::$extraOptions)) {
            $options = array_merge($options, self::$extraOptions);
        }

        $this->client = new Client($options);
    }

    final protected function __clone()
    {

    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * @param $option
     * @param $value
     */
    public static function setExtraOption($option, $value): void
    {
        self::$extraOptions[$option] = $value;
    }

    /**
     * @param $option
     * @param $value
     */
    public function setRequestOption($option, $value): void
    {
        $this->requestOptions[$option] = $value;
    }

    /**
     * @param string $relativeUri
     * @param array $params
     * @return string
     * @throws GuzzleException
     * @throws Exception
     */
    protected function request(string $relativeUri, array $params = []): string
    {
        try {
            $uri = $relativeUri;

            $options = [
                'query' => $params
            ];

            if (!empty($this->requestOptions)) {
                $options = array_merge($options, $this->requestOptions);
            }

            $response = $this->doRequest($uri, $options);
        } catch (TransferException $e) {
            $message = 'API is not available. ' . $e->getMessage();
            throw new Exception($message, $e->getCode(), $e);
        }

        return $response->getBody()->getContents();
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
}