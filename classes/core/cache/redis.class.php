<?php

/**
 * @package Framework
 */
class core_cache_redis implements interface_cache {

	/**
	 * @var Redis
	 */
	private $conn;

	public function __construct() {
		$this->conn = new Redis();
//		if(!function_exists('igbinary_serialize')) {
//			trigger_error('igbinary package is not installed.  Cannot continue!', E_USER_ERROR);
//		}
//		$this->conn->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);
		$this->conn->connect(cfg::get('redis_server'), cfg::get('redis_port'), 2.5);
	}

	public function get($userKey) {
		$val = $this->conn->get($userKey);
		// If we got a false back.  Since we're serializing, false is never a valid return so it must not exist.
		if($val === false) {
			return null;
		}
		return unserialize($val);
	}

	public function set($userKey, $val, $ttl = CACHE_DEFAULT_TTL) {
		$ttl = (int) $ttl;
		$ret = $this->conn->setex($userKey, $ttl, serialize($val));
		return $ret;
	}

	public function delete($userKey) {
		$ret = $this->conn->delete($userKey);
		return $ret > 0;
	}

}
