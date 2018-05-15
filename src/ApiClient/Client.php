<?php namespace HelloHi\ApiClient;

use Exception;
use GuzzleHttp;
use kamermans\OAuth2\OAuth2Middleware;
use GuzzleHttp\HandlerStack;
use kamermans\OAuth2\GrantType\PasswordCredentials;

class Client
{
	private $baseUrl;
	private $client;
	private $uploadClient;
	private $oAuth;
	private $headers;
	private $tenantId;
	private static $instance = null;

	public $exceptions = false;

	public $lastError = "";

	private function __construct($auhUrl, $baseUrl, $clientId, $clientSecret, $username, $password, $tenantId)
	{
		$this->baseUrl = $baseUrl;
		$this->tenantId = $tenantId;
		$this->headers = [];

		$this->client = $this->getClient($auhUrl, $clientId, $clientSecret, $username, $password);

		// use a different client for file uploads
		$this->uploadClient = new GuzzleHttp\Client(['debug' => false, 'exceptions' => false]);

		$this->addHeader('Content-Type', 'application/json');
		$this->addHeader('Accept', 'application/json');

		$this->setTenantId($tenantId);
	}

	public function getLastError() {
		return $this->lastError;
	}

	public static function init($auhUrl, $baseUrl, $clientId, $clientSecret, $username, $password, $tenantId) {
		if(!self::$instance) {
			self::$instance = new self($auhUrl, $baseUrl, $clientId, $clientSecret, $username, $password, $tenantId);
		}
		return self::$instance;
	}

	public static function clearInstance() {
		self::$instance = null;
	}

	public static function getInstance() {
		if(!self::$instance) {
			throw new Exception("Client not initialized, run init() first");
		}
		return self::$instance;
	}

	private function getHeaders() {
		return $this->headers;
	}

	private function addHeader($header, $value) {
		$this->headers[$header] = [$value];
	}

	private function getClient($auhUrl, $clientId, $clientSecret, $username, $password)
	{
		$reauth_client = new GuzzleHttp\Client([
			'base_uri' => $auhUrl,
		]);

		$grant_type = new PasswordCredentials($reauth_client, [
			'client_id' => $clientId,
			'client_secret' => $clientSecret,
			'username' => $username,
			'password' => $password,
		]);

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

	private function prepareUrl($endpoint, $includes) {
		$url = $this->baseUrl."/".$endpoint;

		if(count($includes)) {
			$url .= "?include=".implode(",", $includes);
		}

		return $url;
	}

	public function getToken() {
		return $this->oAuth->getAccessToken();
	}

	public function uploadDossierItem($customerId, $directoryId, $groupName, array $dossierItems) {

		$url = $this->prepareUrl("dossier_item_groups", []);

		$parts = [
			['name' => 'dossier_directory_id', 'contents' => $directoryId],
			['name' => 'customer_id', 'contents' => $customerId],
			['name' => 'name', 'contents' => $groupName],
			['name' => 'status', 'contents' => 'open'],
			['name' => 'is_public', 'contents' =>  0]
		];

		foreach($dossierItems as $i => $dossierItem) {
			$parts[] = [
				'dossier_items['.$i.'][resource]' => $dossierItem['handle'],
				'dossier_items['.$i.'][name]' => $dossierItem['name'],
			];
		}

		$response = $this->uploadClient->post($url, [
			'headers' => [
				'Authorization' => 'Bearer '.$this->getToken(),
				'X-Tenant' => $this->tenantId,
				'accept' => 'application/json'
			],
			'multipart' => $parts
		]);

		$data = $response->getBody()->getContents();
		return $this->decodeResponseData($data);
	}

    public function uploadDossierItemForThread($threadId, $message,  array $dossierItems) {

        $url = $this->prepareUrl("threads/".$threadId."/items", []);

        $parts = [
            ['name' => 'message', 'contents' => $message],
        ];

        foreach($dossierItems as $i => $dossierItem) {
            $parts[] = [
                'name' => 'dossier_items['.$i.'][name]',
                'contents' => $dossierItem['name'],
            ];
            $parts[] = [
                'name' => 'dossier_items['.$i.'][resource]',
                'contents' => $dossierItem['handle'],
            ];
            $parts[] = [
                'name' => 'dossier_items['.$i.'][status]',
                'contents' => $dossierItem['status'],
            ];
        }

        $response = $this->uploadClient->post($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->getToken(),
                'X-Tenant' => $this->tenantId,
                'accept' => 'application/json'
            ],
            'multipart' => $parts
        ]);

        $data = $response->getBody()->getContents();
        return $this->decodeResponseData($data);
    }

	private function parseErrors($contents) {
		$contents = $this->decodeResponseData($contents);

		if (isset($contents['errors'])) {
			$message = GuzzleHttp\json_encode($contents['errors']);
		} else {
			$message = $contents['message'];;
		}

		$this->lastError = $message;

		return $message;
	}

	public function call($method, $endpoint, $data = [], $includes = [])
	{
		$url = $this->prepareUrl($endpoint, $includes);

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

				if(isset($contents['status_code'])) {
					if ($this->exceptions) {
						throw new ApiException($endpoint . ": " . $message, $contents['status_code']);
					}
				}
			}

			// general exception
			if($this->exceptions) {
				throw new Exception($endpoint . ": " . $e->getMessage());
			}

			return null;
		}

		$data = $response->getBody()->getContents();
		return $this->decodeResponseData($data);
	}

	private function decodeResponseData($data) {
		return json_decode($data, true);
	}

	public function get($endpoint, $includes = []) {
		return $this->call('GET', $endpoint, [], $includes);
	}

	public function patch($endpoint, $data = [], $includes = []) {
		return $this->call('PATCH', $endpoint, $data, $includes);
	}

	public function post($endpoint, $data = [], $includes = []) {
		return $this->call('POST', $endpoint, $data, $includes);
	}

	public function delete($endpoint) {
		return $this->call('DELETE', $endpoint);
	}

	public function setTenantId($id) {
		$this->addHeader('X-Tenant', $id);
	}
}