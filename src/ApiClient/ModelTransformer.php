<?php namespace HelloHi\ApiClient;

use Illuminate\Contracts\Pagination\LengthAwarePaginator; 
use Illuminate\Http\Request;

class ModelTransformer
{
	public static function fromData($data, $endpoint) {
		if(!$data) {
			return null;
		}

		$data = self::unwrapDataEnvelopes($data);

		// is it a list?
		if(isset($data[0]) && isset($data[0]['object'])) {
			$collection = [];
			foreach($data as $attributes) {
				$collection[] = new Model($attributes, $endpoint);
			}
			return collect($collection);
		} elseif(isset($data['object'])) {
			return new Model($data, $endpoint);
		}
		return null;
	}

	public static function unwrapDataEnvelopes($data) {
		if(is_array($data) && isset($data['data'])) {
			$data = $data['data'];
		}

		$unwrapped = [];

		if (is_array($data) || is_object($data)){
			foreach($data as $key => $val) {
				if(is_array($val)) {
					$unwrapped[$key] = self::unwrapDataEnvelopes($val);
				} else {
					$unwrapped[$key] = $val;
				}
			}
			return $unwrapped;
		}
	}

	public static function paginationData($data, $endpoint) {
		if($data['meta']['pagination'] != null) {
			return $data['meta']['pagination'];
		}
	}

	public static function paginate($items, $count, $limit, $page){
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator($items, $count, $limit, $page);
        return $paginator;
    }
}