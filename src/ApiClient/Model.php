<?php namespace HelloHi\ApiClient;

class Model
{
	private $endpoint;
	private $attributes;

	public function __construct($attributes, $endpoint) {
		$this->endpoint = $endpoint;

		$this->fill($attributes);
	}

	public function fill($attributes) {
		$this->attributes = $attributes;
	}

	public function __get($name) {
		if(array_key_exists($name, $this->attributes)) {
			return $this->attributes[$name];
		}
		return $this->$name();
	}

	public function __call($name, $arguments) {
		if($name == "byId") {
			return self::__byId(
				$this->endpoint."/".$this->id."/".$arguments[0],
				$arguments[1],
				$arguments[2] ?? []);
		} elseif($name == "all") {
			$subEndpoint = $arguments[0];
		} else { // magic method invoike
			$subEndpoint = $name;
		}

		return self::__all(
			$this->endpoint."/".$this->id."/".$subEndpoint,
			$arguments[1] ?? []
		);
	}

	public static function __callStatic($name, $arguments) {
		if($name == "byId") {
			return self::__byId($arguments[0], $arguments[1], $arguments[2] ?? []);
		} elseif($name == "all") {
			$endPoint = $arguments[0];
			$includes = $arguments[1];
		} else { // magic method invoike
			$endPoint = $name;
			$includes = $arguments[0] ?? [];
		}

		return self::__all(
			$endPoint,
			$includes
		);
	}

	public static function __all($endpoint, $includes = []) {
		$client = Client::getInstance();
		$response = $client->get($endpoint, $includes);
		return ModelTransformer::fromData($response, $endpoint);
	}

	public static function __byId($endpoint, $id, $includes = []) {
		$client = Client::getInstance();
		$response = $client->get($endpoint."/".$id, $includes);
		return ModelTransformer::fromData($response, $endpoint);
	}

	public function update($data = [], $includes = []) {
		$client = Client::getInstance();
		$response = $client->patch($this->endpoint."/".$this->id, $data, $includes);

		// update attributes
		$this->attributes = ModelTransformer::unwrapDataEnvelopes($response);

		return ModelTransformer::fromData($response, $this->endpoint);
	}

	public static function create($endpoint, $data = [], $includes = []) {
		$client = Client::getInstance();
		$response = $client->post($endpoint, $data, $includes);

		if(!$response) {
			return null;
		}

		return ModelTransformer::fromData($response, $endpoint);
	}

	public function delete() {
		$client = Client::getInstance();
		return $client->delete($this->endpoint."/".$this->id);
	}

	// used to override endpoint for polymorphic entities
	public function setEndpoint($endpoint) {
		$segments = explode("/", $this->endpoint);
		$segments[count($segments)-1] = $endpoint;
		$this->endpoint = implode("/", $segments);
		return $this;
	}
}