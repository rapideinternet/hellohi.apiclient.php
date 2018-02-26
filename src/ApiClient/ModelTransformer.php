<?php namespace ApiClient;

class ModelTransformer
{
	public static function fromData($data, $endpoint) {
		$data = self::unwrapDataEnvelopes($data);

		// is it a list?
		if(isset($data[0]) && isset($data[0]['object'])) {
			$collection = [];
			foreach($data as $attributes) {
				$collection[] = new Model($attributes, $endpoint);
			}
			return $collection;
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