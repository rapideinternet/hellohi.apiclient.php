<?php namespace HelloHi\ApiClient;

use Illuminate\Contracts\Pagination\LengthAwarePaginator; 
use Illuminate\Http\Request;

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
			$perPage = $arguments[2] ?? 15;
			$currentPage = $arguments[3] ?? 1;
		} else { // magic method invoike
			$endPoint = $name;
			$includes = $arguments[0] ?? [];
			$perPage = $arguments[2] ?? [];
			$currentPage = $arguments[3] ?? [];
		}

		return self::__all(
			$endPoint,
			$includes,
			$perPage,
			$currentPage
		);
	}

	public static function __all($endpoint, $includes = [], $perPage = 15, $currentPage = 1) {
		$client = Client::getInstance();
		$response = $client->get($endpoint, $includes, $perPage, $currentPage);
		$data = ModelTransformer::fromData($response, $endpoint);
		if($response['meta'] != null && array_key_exists('pagination', $response['meta'])){
			$pagination = ModelTransformer::paginationData($response, $endpoint);
		}else{
			$pagination['total'] = 1;
			$pagination['per_page'] = 15;
			$pagination['current_page'] = 1;
		}
		return ModelTransformer::paginate($data, $pagination['total'], $pagination['per_page'], $pagination['current_page']);
	}

	public static function __byId($endpoint, $id, $includes = [], $perPage = 15, $currentPage = 1) {
		$client = Client::getInstance();
		$response = $client->get($endpoint."/".$id, $includes, $perPage, $currentPage);
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

    public static function search($endpoint, $data, $includes = [], $perPage = 15, $currentPage = 1) {
        $client = Client::getInstance();
        $response = $client->get("search/".$endpoint. "?" . http_build_query($data), $includes, $perPage, $currentPage);

        $data = ModelTransformer::fromData($response, $endpoint);
        if($response['meta'] != null && array_key_exists('pagination', $response['meta'])){
            $pagination = ModelTransformer::paginationData($response, $endpoint);
        }else{
            $pagination['total'] = 1;
        }
        return ModelTransformer::paginate($data, $pagination['total'], $perPage, $currentPage);
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

	public function toArray()
    {
        return $this->attributes;
    }
}