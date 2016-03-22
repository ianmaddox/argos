<?php

/**
 * @package framework
 * @subpackage cache
 */
class core_cache_apc implements interface_cache {

	public function __construct() {
		
	}

	/**
	 * Fetch a value from cache.  If the value returned is null, it is safe to assume there was no
	 * match found in cache.
	 *
	 * @param string $userKey
	 * @return mixed
	 */
	public function get($userKey) {
		$val = apc_fetch($userKey, $success);
		if(!$success) {
			return null;
		}

		return $val;
	}

	/**
	 * Store an item in cache.  Storing null values should be avoided because that signals to any
	 * getter code that no match was found in cache.
	 *
	 * Limitations: Does not support objects.  Key names cannot be integers.
	 *
	 * @param string $userKey
	 * @param mixed $val
	 * @param int $ttl
	 * @return mixed
	 */
	public function set($userKey, $val, $ttl = CACHE_DEFAULT_TTL) {
		$ret = apc_store($userKey, $val, $ttl);

		return $ret;
	}

	/**
	 * Delete an item from cache
	 *
	 * @param mixed $userKey
	 * @return success
	 */
	public function delete($userKey) {
		$ret = apc_delete($userKey);

		return $ret;
	}

}
