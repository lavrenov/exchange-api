<?php declare(strict_types=1);

namespace Lavrenov\ExchangeAPI\Base;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class Exchange extends Singleton
{
    protected const API_URL = '';
    protected const FAPI_URL = '';

    /* @var Client */
    private $client;

    /* @var ResponseInterface */
    private $lastResponse;

    private $requestOptions = [];

    public function init(): void
    {
        parent::init();

        $options = [
            'http_errors' => false,
        ];

        $this->client = new Client($options);
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
     * @param string $requestType
     * @param string $relativeUri
     * @param array $params
     * @param bool $signed
     * @return string|null
     */
    protected function request(string $requestType, string $relativeUri, array $params = [], bool $signed = false): ?string
    {
        $baseUri = static::API_URL;

        if (isset($params['fapi'])) {
            unset($params['fapi']);
            $baseUri = static::FAPI_URL;
        }

        if ($signed) {
            $params['timestamp'] = time() * 1000;
            $params['signature'] = hash_hmac('sha256', http_build_query($params), static::$secret);
            $this->setRequestOption('headers', ['X-MBX-APIKEY' => static::$apiKey]);
        }

        $uri = $baseUri . $relativeUri;

        $options = [
            'query' => $params
        ];

        if (!empty($this->requestOptions)) {
            $options = array_merge($options, $this->requestOptions);
        }

        try {
            $this->lastResponse = $this->client->request($requestType, $uri, $options);
            $result = $this->lastResponse->getBody()->getContents();
            //@codeCoverageIgnoreStart
        } catch (GuzzleException $e) {
            $result = null;
        }
        //@codeCoverageIgnoreEnd

        return $result;
    }

    /**
     * @return ResponseInterface|null
     */
    public function getLastResponse(): ?ResponseInterface
    {
        return $this->lastResponse ?? null;
    }
}