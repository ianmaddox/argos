<?php

/**
 * @package framework
 * @subpackage cache
 */
class core_cache_memcached implements interface_cache {

	/**
	 * @var Memcached $conn
	 */
	private $conn;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->conn = new Memcached('shared-cache-conn');
		$servers = $this->conn->getServerList();
		if(!count($servers)) {
			$serverList = explode(',', cfg::get('memcached_server'));
			foreach($serverList as $server) {
				$this->conn->addServer($server, cfg::get('memcached_port'));
			}
		}
	}

	/**
	 * Fetch a value from cache.  If the value returned is null, it is safe to assume there was no
	 * match found in cache.
	 *
	 * @param string $userKey
	 * @return mixed
	 */
	public function get($userKey) {
		$userKey = $this->sanitizeKey($userKey);

		$val = $this->conn->get($userKey);

		if($this->conn->getResultCode() !== Memcached::RES_SUCCESS) {
			return null;
		}

		return $val;
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
		$userKey = $this->sanitizeKey($userKey);

		// A TTL greater than 30 days is considered to be a unix timestamp.
		// To compensate, convert all TTLS to an absolute time.
		$ttl += time();
		$ret = $this->conn->set($userKey, $val, $ttl);
		if(!$ret) {
			trigger_error("Error writing to memcached: '" . $this->conn->getResultMessage() . "'", E_USER_WARNING);
		}
		return $ret;
	}

	/**
	 * Delete an item from cache
	 *
	 * @param mixed $key
	 * @return success
	 */
	public function delete($userKey) {
		$userKey = $this->sanitizeKey($userKey);
		$ret = $this->conn->delete($userKey);
		return $ret;
	}

	/**
	 * Validate whether a cache key is valid.  If not, warn and create a temporary hashed key so we don't break the site.
	 *
	 * @param string $key
	 * @return string $key
	 */
	private function sanitizeKey($key) {
		return urlencode($key);
	}

}
