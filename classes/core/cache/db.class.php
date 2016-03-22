<?php

/**
 * @package framework
 * @subpackage cache
 */
class core_cache_db implements interface_cache {

	/**
	 * @var type $db The DB object
	 */
	private $db = false;

	/**
	 * @var int $chunklen The length of chunks
	 */
	private $chunklen = 65000;

	/**
	 * @var bool control whether data should be stored in a binary safe manner
	 */
	private $binarySafe = true;

	/**
	 * @var bool control whether data should be compressed
	 */
	private $compress = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->db = core_db::getDB();
	}

	/**
	 * Fetch a value from cache.  If the value returned is null, it is safe to assume there was no
	 * match found in cache.
	 *
	 * @param string $cacheKey
	 * @return mixed
	 */
	public function get($cacheKey) {
		list($key, $table) = $this->genKeyTablePair($cacheKey);
		// query for key, join all rows with parent of key
		// unserialize data
		$cleanKey = $this->db->escapeVal($key);
		$sql = "SELECT
					value,
					sequence,
					chunkCount
				FROM {$table}
				WHERE
					cacheKey = '{$cleanKey}'
					AND expires > NOW()
				ORDER BY sequence ASC";
		$rso = $this->db->query($sql);
		if(!$rso || !$rso->getCount()) {
			return NULL;
		}
		$valArr = array();
		$count = 0;
		while($row = $rso->getRow()) {
			$valArr[] = $row['value'];
			if($row['sequence'] != ($count + 1)) {
				return NULL;
			}
			if($count == $row['chunkCount']) {
				break;
			}
			$count++;
		}
		$val = implode('', $valArr);
		if($this->compress || $this->binarySafe) {
			$val = base64_decode($val);
		}
		if($this->compress) {
			$val = gzuncompress($val);
		}
		$val = unserialize($val);

		return $val;
	}

	/**
	 * Store an item in cache.  Storing null values should be avoided because that signals to any
	 * getter code that no match was found in cache.
	 *
	 * @param string $cacheKey
	 * @param mixed $val
	 * @param int $ttl
	 * @return mixed
	 */
	public function set($cacheKey, $val, $ttl = CACHE_DEFAULT_TTL) {
		list($key, $table) = $this->genKeyTablePair($cacheKey);
		$ttl = (int) $ttl;
		// split data
		// insert rows

		if(rand(1, 100) == 1) {
			$sql = "DELETE FROM {$table} WHERE expires < NOW()";
			$this->db->query($sql);
		}
		$val = serialize($val);
		$comp_level = floor(strlen($val) / $this->chunklen);
		$comp_level = $comp_level > 9 ? 9 : $comp_level;

		if($this->compress) {
			$val = gzcompress($val, $comp_level);
		}
		if($this->compress || $this->binarySafe) {
			$val = base64_encode($val);
		}

		// split data and insert rows
		$chunks = str_split($val, $this->chunklen);

		$cleanKey = $this->db->escapeVal($key);
		$chunkCount = count($chunks);
		$sequence = 0;
		$expires = 'DATE_ADD(NOW(), INTERVAL ' . $ttl . ' SECOND)';

		foreach($chunks as $chunk) {
			$cleanChunk = $this->db->escapeVal($chunk);
			$sequence++;
			$values = "(
				NOW(),
				'{$cleanKey}',
				'{$cleanChunk}',
				{$expires},
				'{$sequence}',
				'{$chunkCount}')";

			$sql = 'INSERT INTO ' . $table . '
				(`date_added`, `cacheKey`,`value`,`expires`,`sequence`,`chunkCount`)
				VALUES ' . $values . ' ON DUPLICATE KEY UPDATE `value` = "' . $cleanChunk . '", expires = ' . $expires;
			$ret = $this->db->query($sql);
		}
		return $ret->getSuccess();
	}

	/**
	 * Delete the cache value
	 *
	 * @param string $cacheKey
	 * @return mixed
	 */
	public function delete($cacheKey) {
		// Clear the old data
		list($key, $table) = $this->genKeyTablePair($cacheKey);
		$cleanKey = $this->db->escapeVal($key);

		$sql = "DELETE FROM {$table} WHERE `cacheKey` = '{$cleanKey}'";
		$ret = $this->db->query($sql);

		return $ret->getSuccess();
	}

	/**
	 * Figure out the table to put data into
	 *
	 * @param string $cacheKey
	 * @return array
	 */
	private function genKeyTablePair($cacheKey) {
		$key = md5($cacheKey);
		$table = 'cache.cache_' . strtoupper($key{0});
		$data = array($key, $table);

		return $data;
	}

}
