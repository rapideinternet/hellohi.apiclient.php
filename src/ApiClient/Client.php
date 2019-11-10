<?php namespace HelloHi\ApiClient;

use Exception;
use GuzzleHttp;
use GuzzleHttp\HandlerStack;
use kamermans\OAuth2\GrantType\ClientCredentials;
use kamermans\OAuth2\GrantType\PasswordCredentials;
use kamermans\OAuth2\OAuth2Middleware;

class Client
{
    private $baseUrl;
    private $client;
    private $uploadClient;
    private $oAuth;
    private $headers;
    private $tenantId;
    private $token;
    private static $instance = null;

    public $exceptions = false;

    public $lastError = "";

    private function __construct()
    {
        $this->headers = [];

        $this->addHeader('Content-Type', 'application/json');
        $this->addHeader('Accept', 'application/json');

        // use a different client for file uploads
        $this->uploadClient = new GuzzleHttp\Client(['debug' => false, 'exceptions' => false]);
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    private function initFromBearerTokenInternal($baseUrl, $accessToken)
    {
        $this->token = $accessToken;
        $this->baseUrl = $baseUrl;

        $this->addHeader('Authorization', 'Bearer ' . $this->getToken());

        // use a different client for file uploads
        $this->client = new GuzzleHttp\Client(['debug' => false, 'exceptions' => false]);
    }

    public static function initFromBearerToken($baseUrl, $accessToken)
    {
        $instance = self::getInstance();
        $instance->initFromBearerTokenInternal($baseUrl, $accessToken);

        return self::$instance;
    }

    private function initFromCredentialsInternal(
        $auhUrl,
        $baseUrl,
        $clientId,
        $clientSecret,
        $username = null,
        $password = null,
        $tenantId
    ) {
        $this->baseUrl = $baseUrl;
        $this->tenantId = $tenantId;

        $this->client = $this->getClient($auhUrl, $clientId, $clientSecret, $username, $password);

        $this->setTenantId($tenantId);
    }

    public static function initFromCredentials(
        $auhUrl,
        $baseUrl,
        $clientId,
        $clientSecret,
        $username = null,
        $password = null,
        $tenantId
    ) {
        $instance = self::getInstance();
        $instance->initFromCredentialsInternal($auhUrl, $baseUrl, $clientId, $clientSecret, $username, $password,
            $tenantId);
        return $instance;
    }

    /*
     * Keep regular init for backwards compatibility
     */
    public static function init(
        $auhUrl,
        $baseUrl,
        $clientId,
        $clientSecret,
        $username = null,
        $password = null,
        $tenantId
    ) {
        return self::initFromCredentials($auhUrl, $baseUrl, $clientId, $clientSecret, $username, $password, $tenantId);
    }

    public static function clearInstance()
    {
        self::$instance = null;
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function getHeaders()
    {
        return $this->headers;
    }

    private function addHeader($header, $value)
    {
        $this->headers[$header] = [$value];
    }

    private function getClient($auhUrl, $clientId, $clientSecret, $username = null, $password = null)
    {
        $reauth_client = new GuzzleHttp\Client([
            'base_uri' => $auhUrl,
        ]);

        if ($username !== null && $password !== null) {
            $grant_type = new PasswordCredentials($reauth_client, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'username' => $username,
                'password' => $password,
            ]);
        } else {
            $grant_type = new ClientCredentials($reauth_client, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret
            ]);
        }

        $this->oAuth = new OAuth2Middleware($grant_type);

        $stack = HandlerStack::create();
        $stack->push($this->oAuth);

        return new GuzzleHttp\Client([
            'handler' => $stack,
            'auth' => 'oauth',
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }

    private function prepareUrl($endpoint, $includes, $perPage = 15, $currentPage = 1)
    {
        $url = $this->baseUrl . "/" . $endpoint;

        if (isset(parse_url($url)['query'])) {
            $sign = '&';
        } else {
            $sign = '?';
        }

        if (count($includes)) {
            $url .= $sign . "include=" . implode(",", $includes);
            $url .= "&limit=" . $perPage . "&page=" . $currentPage;
        } else {
            $url .= $sign . "limit=" . $perPage . "&page=" . $currentPage;
        }

        return $url;
    }

    public function getToken()
    {
        return $this->token ?? $this->oAuth->getAccessToken();
    }

    /**
     * @param $dossierItemId
     * @return null|$response
     * @throws ApiException
     * @throws Exception
     */
    public function downloadDossierItem($dossierItemId)
    {
        $endpoint = sprintf("dossier_items/%s/download", $dossierItemId);
        $url = $this->prepareUrl($endpoint, []);

        try {
            $response = $this->client->request('GET', $url, [
                'headers' => $this->getHeaders(),
                'stream' => true
            ]);

        } catch (Exception $e) {
            // api exception ?
            if ($e instanceof GuzzleHttp\Exception\ClientException) {
                $contents = $e->getResponse()->getBody(true)->getContents();
                $message = $this->parseErrors($contents);

                if (isset($contents['status_code'])) {
                    if ($this->exceptions) {
                        throw new ApiException($endpoint . ": " . $message, $contents['status_code']);
                    }
                }
            } else {
                if ($e instanceof GuzzleHttp\Exception\RequestException) {
                    $contents = $e->getResponse()->getBody(true)->getContents();
                    $message = $this->parseErrors($contents);
                }
            }

            // general exception
            if ($this->exceptions) {
                throw new Exception($endpoint . ": " . $e->getMessage());
            }

            return null;
        }

        return $response;
    }

    public function uploadDossierItem(
        $customerId,
        $directoryId,
        $resource,
        $name,
        $status,
        $year = null,
        $period = null,
        $createdAtDate = null,
        $originalFilename = null
    ) {

        $url = $this->prepareUrl("dossier_items", []);

        $parts = [
            ['name' => 'dossier_directory_id', 'contents' => $directoryId],
            ['name' => 'customer_id', 'contents' => $customerId],
            ['name' => 'name', 'contents' => $name],
            ['name' => 'original_filename', 'contents' => $originalFilename],
            ['name' => 'year', 'contents' => $year],
            ['name' => 'period', 'contents' => $period],
            ['name' => 'created_at', 'contents' => $createdAtDate],
            ['name' => 'status', 'contents' => $status],
            ['name' => 'resource', 'contents' => $resource] // fopen("foo.txt", "r")
        ];

        $response = $this->uploadClient->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
                'X-Tenant' => $this->tenantId,
                'Accept' => 'application/json'
            ],
            'multipart' => $parts
        ]);

        $data = $response->getBody()->getContents();

        return $this->decodeResponseData($data);
    }

    public function uploadDossierItemsForThread($customerId, $threadId, $message, array $dossierItems)
    {
        $url = $this->prepareUrl("tasks", []);

        $parts = [
            ['name' => 'customer_id', 'contents' => $customerId],
            ['name' => 'thread_id', 'contents' => $threadId],
            ['name' => 'message', 'contents' => $message],
        ];

        foreach ($dossierItems as $i => $dossierItem) {
            $parts[] = [
                'name' => 'dossier_items[' . $i . '][name]',
                'contents' => $dossierItem['name'],
            ];
            $parts[] = [
                'name' => 'dossier_items[' . $i . '][resource]',
                'contents' => $dossierItem['handle'],
            ];
            $parts[] = [
                'name' => 'dossier_items[' . $i . '][status]',
                'contents' => $dossierItem['status'],
            ];
        }

        $response = $this->uploadClient->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
                'X-Tenant' => $this->tenantId,
                'accept' => 'application/json'
            ],
            'multipart' => $parts
        ]);

        $data = $response->getBody()->getContents();
        return $this->decodeResponseData($data);
    }

    public function createEntitiesForWebshop($customerData, $personData, $dossierItemName, $dossierItemHandle)
    {
        $url = $this->prepareUrl("webshop", []);

        foreach ($customerData as $key => $val) {
            $parts[] = ['name' => 'customer[' . $key . ']', 'contents' => $val];
        }

        foreach ($personData as $key => $val) {
            $parts[] = ['name' => 'person[' . $key . ']', 'contents' => $val];
        }

        $parts[] = [
            'name' => 'dossier_item[name]',
            'contents' => $dossierItemName,
        ];
        $parts[] = [
            'name' => 'dossier_item[resource]',
            'contents' => $dossierItemHandle,
        ];

        $response = $this->uploadClient->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
                'X-Tenant' => $this->tenantId,
                'accept' => 'application/json'
            ],
            'multipart' => $parts
        ]);

        $data = $response->getBody()->getContents();
        return $this->decodeResponseData($data);
    }

    private function parseErrors($contents)
    {
        $contents = $this->decodeResponseData($contents);

        if (isset($contents['errors'])) {
            $message = GuzzleHttp\json_encode($contents['errors']);
        } else {
            $message = $contents['message'];;
        }

        $this->lastError = $message;

        return $message;
    }

    public function call($method, $endpoint, $data = [], $includes = [], $perPage = 15, $currentPage = 1)
    {
        $url = $this->prepareUrl($endpoint, $includes, $perPage, $currentPage);

        try {
            $response = $this->client->request($method, $url, [
                'headers' => $this->getHeaders(),
                'json' => $data
            ]);

        } catch (Exception $e) {
            // api exception ?
            if ($e instanceof GuzzleHttp\Exception\ClientException) {
                $contents = $e->getResponse()->getBody(true)->getContents();
                $message = $this->parseErrors($contents);

                if (isset($contents['status_code'])) {
                    if ($this->exceptions) {
                        throw new ApiException($endpoint . ": " . $message, $contents['status_code']);
                    }
                }
            } else {
                if ($e instanceof GuzzleHttp\Exception\RequestException) {
                    $contents = $e->getResponse()->getBody(true)->getContents();
                    $message = $this->parseErrors($contents);
                }
            }

            // general exception
            if ($this->exceptions) {
                throw new Exception($endpoint . ": " . $e->getMessage());
            }

            return null;
        }

        $data = $response->getBody()->getContents();
        return $this->decodeResponseData($data);
    }

    private function decodeResponseData($data)
    {
        return json_decode($data, true);
    }

    public function get($endpoint, $includes = [], $perPage = 15, $currentPage = 1)
    {
        return $this->call('GET', $endpoint, [], $includes, $perPage, $currentPage);
    }

    public function patch($endpoint, $data = [], $includes = [])
    {
        return $this->call('PATCH', $endpoint, $data, $includes);
    }

    public function post($endpoint, $data = [], $includes = [])
    {
        return $this->call('POST', $endpoint, $data, $includes);
    }

    public function delete($endpoint)
    {
        return $this->call('DELETE', $endpoint);
    }

    public function setTenantId($id)
    {
        $this->tenantId = $id;
        $this->addHeader('X-Tenant', $id);
    }
}