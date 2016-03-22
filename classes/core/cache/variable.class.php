<?php

/**
 * This is a specialized cache driver which stores all data for the duration of a single request.
 * Uses: Unit testing, CLI, development/qa, debugging
 *
 * @package framework
 * @subpackage cache
 */
class core_cache_variable implements interface_cache {

	private $cache = array();

	public function __construct() {
		
	}

	/**
	 * Fetch a value from cache.  If the value returned is null, it is safe to assume there was no
	 * match found in cache.
	 *
	 * Limitations; Data does not persist beyond a script execution or page request
	 *
	 * @param string $userKey
	 * @return mixed
	 */
	public function get($userKey) {
		if(!isset($this->cache[$userKey])) {
			return null;
		}
		if($this->cache[$userKey]['expires'] < microtime(1)) {
			return null;
		}
		return $this->cache[$userKey]['data'];
	}

	/**
	 * Store an item in cache.  Storing null values should be avoided because that signals to any
	 * getter code that no match was found in cache.
	 *
	 * @param string $userKey
	 * @param mixed $val
	 * @param int $ttl
	 * @return mixed
	 */
	public function set($userKey, $val, $ttl = CACHE_DEFAULT_TTL) {
		$this->cache[$userKey]['expires'] = microtime(1) + $ttl;
		$this->cache[$userKey]['data'] = $val;
		return true;
	}

	/**
	 * Delete an item from cache
	 *
	 * @param mixed $userKey
	 * @return success
	 */
	public function delete($userKey) {
		unset($this->cache[$userKey]);
		return true;
	}

}
