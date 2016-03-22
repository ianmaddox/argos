<?php

/**
 * @package framework
 * @subpackage cache
 */
class core_cache_none implements interface_cache {

	/**
	 * Constructor
	 */
	public function __construct() {
		
	}

	/**
	 * Does nothing
	 *
	 * @param string $key
	 * @return null
	 */
	public function get($key) {
		return null;
	}

	/**
	 * Does nothing
	 *
	 * @param string $key
	 * @param mixed $val
	 * @param int $ttl
	 * @return boolean
	 */
	public function set($key, $val, $ttl = CACHE_DEFAULT_TTL) {
		return true;
	}

	/**
	 * Does nothing
	 *
	 * @param string $key
	 * @return boolean
	 */
	public function delete($key) {
		return true;
	}

}
