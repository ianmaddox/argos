<?php

/**
 * scoop.content table model
 *
 * @package framework
 * @subpackage db_session
 */
class db_session_data extends core_row {
	const DB = 'session';
	const TABLE = 'data';
	const PK = 'id';
	private $cache;

	/**
	 * Destroy the session
	 */
	public function __destruct() {
		if($this->cache) {
			$cache = new core_cache(CACHE_MEMORY);
			$cache->set(self::getCacheKey($this->getPK), $this);
		}
	}

	/**
	 * Fetch an object instance based on a search
	 *
	 * @param string $id
	 * @return db_session_data
	 */
	public static function getInstance($id, $cache = false) {
		if($cache) {
			$cache = new core_cache(CACHE_MEMORY);
			$obj = $cache->get(self::getCacheKey($id));
			if(get_class($obj) == __CLASS__ && !empty($obj)) {
				return $obj;
			}
		}

		return new self($id, $cache);
	}

	/**
	 * Set the expiration time
	 *
	 * @param string $id
	 * @param string $expiry
	 */
	public static function setExpiration($id, $expiry) {
		$db = core_db::getDB();
		$sql = 'UPDATE ' . self::DB . '.' . self::TABLE . '
			LOW_PRIORITY
			SET expires = ' . (int)$expiry . '
			WHERE
				expires > UNIX_TIMESTAMP()
				AND ' . self::TABLE . '.' . self::PK . ' = "' . $db->escapeVal($id) . '"
		';
		$db->query($sql);
	}

	/**
	 * Get the cache key
	 *
	 * @param string $id
	 * @return string
	 */
	private static function getCacheKey($id) {
		return __CLASS__ . '-' . $id;
	}
}
