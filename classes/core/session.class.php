<?php
/**
 * A custom session handler that uses memcached for speed and db backup for reliability
 *
 * Sessions inside the DB look like the following:
 *    [data] => session data stored in native format
 *    [expires] => unix timestamp after which the session is expired
 *    [date_added] => session start date
 *    [date_modified] => last modified timestamp (may vary slightly between memcached and db)
 * Sessions in memcached are broken into two parts:
 *    session-{sessionID}-data
 *    session-{sessionID}-meta (contains expires)
 *
 * http://php.net/manual/en/function.session-set-save-handler.php
 *
 * Initially we stored sessions locally in APC as well as remotely in memcached. As users floated
 * from box to box or servers are restarted, they will pull down data from the network cache and
 * then work with it locally.
 *
 * This approach layers two cache engines expecting we won't have simultaneous wipes.
 *
 * @package framework
 * @subpackage core
 */
class core_session
{
	/**
	 * Flags to control the secondary storage location
	 */
	// Define the cache engine to use for primary storage
	const PRIMARY_CACHE = CACHE_NETWORK;

	// Define the secondary storage location.  Any CACHE_* or core_cache::ENGINE_* constants will do.
	const BACKUP_CACHE = CACHE_NONE;

	public function __construct() {
	}

	/**
	 * We must use the destruct method to handle session saving because the session_set_save_handler()
	 * write function is only called after classes and objects have been destroyed, making calls to
	 * core_db, core_cache, etc useless.
	 *
	 * Pull in the session data and send it to our true session saving method.
	 */
	public function __destruct() {
		if(isset($_SESSION)) {
			$this->write(session_id(), $_SESSION);
		}
	}

	/**
	 * Initialize the session
	 *
	 * @param string $path
	 * @param string $name
	 */
	public function open($path, $name) {
		return true;
	}

	/**
	 * Shut down the session
	 */
	public function close() {
		return true;
	}

	/**
	 * Load the session data.  First try memcached then fall back to the DB.
	 *
	 * @param string $id
	 * @return string
	 */
	public function read($id) {
		$keys = $this->getCacheKeys($id);
		// Fetch data from mem
		$cache = new core_cache(self::PRIMARY_CACHE);

		// Fetch meta data.
		$meta = $cache->get($keys['meta']);
		$data = '';

		if(isset($meta['expires'])) {
			// We have valid meta data.  Evaluate the contents.
			if($meta['expires'] > $_SERVER['REQUEST_TIME']) {
				// The meta data indicates the session expires in the future
				$data = $cache->get($keys['data']);
			} else {
				// The meta data indicates the data is expired.  Start fresh.
				$data = '';
			}
		} else {
			// Meta data is missing or invalid.  Fall through to DB.
			$backupCache = new core_cache(self::BACKUP_CACHE);

			// Fetch meta data.
			$meta = $backupCache->get($keys['meta']);
			$data = '';

			if(isset($meta['expires'])) {
				// We have valid meta data.  Evaluate the contents.
				if($meta['expires'] > $_SERVER['REQUEST_TIME']) {
					// The meta data indicates the session expires in the future
					$data = $backupCache->get($keys['data']);
				} else {
					// The meta data indicates the data is expired.  Start fresh.
					$data = '';
				}
			}
		}

		return $data;
	}

	/**
	 * This dummy write method is called by the built-in session handler after all of the classes
	 * and objects are destroyed.  It cannot make use of OOP and is useless to us.
	 * Instead, we use self::write() which is called by __destroy()
	 *
	 * @param string $id
	 * @param string $data
	 * @return bool
	 */
	public function dummyWrite($id, $data) {
		return true;
	}

	/**
	 * Serialize and save a session array.  This method is called by __destroy() and is NOT the
	 * method we told session_set_save_handler() to use.  See self::dummyWrite for more info.
	 *
	 * @param string $id
	 * @param array $data
	 * @return boolean
	 */
	private function write($id, $data) {
		$sessDataStr = $this->serializeSession($data);
		$sessionWarnBytes = 1024 * 4;
		if(($sessSize = strlen($sessDataStr)) > $sessionWarnBytes) {
			trigger_error("Session {$id} is bigger than {$sessionWarnBytes}B. Serialized size is {$sessSize}B", E_USER_WARNING);
		}
		$keys = $this->getCacheKeys($id);

		// Generate the cache engines
		$cache = new core_cache(self::PRIMARY_CACHE);
		$backupCache = new core_cache(self::BACKUP_CACHE);

		// Update the meta data
		$expiry = $_SERVER['REQUEST_TIME'] + ini_get('session.gc_maxlifetime');

		$meta = $cache->get($keys['meta']);

		if(empty($meta) || !is_array($meta)) {
			$meta = array('date_added' => time());
		}
		$meta['expires'] = $expiry;

		// Write to primary cache
		$cache->set($keys['data'], $sessDataStr, ini_get('session.gc_maxlifetime'));
		$cache->set($keys['meta'], $meta, ini_get('session.gc_maxlifetime'));

		// Write to the backup cache
		$backupCache->set($keys['data'], $sessDataStr, ini_get('session.gc_maxlifetime'));
		$backupCache->set($keys['meta'], $meta, ini_get('session.gc_maxlifetime'));

		return true;
	}

	/**
	 * Turn a session array into a string like PHP expects.
	 *
	 * @param array $array
	 * @return string
	 */
	private function serializeSession($array) {
		$str = '';
		foreach($array as $key => $val) {
			$str .= $key . '|' . serialize($val);
		}
		return $str;
	}

	/**
	 * Destroy a session on demand.
	 *
	 * @param string $id
	 * @return bool
	 */
	public function destroy($id) {
		$keys = $this->getCacheKeys($id);
		$cache = new core_cache(self::PRIMARY_CACHE);
		$cache->delete($keys['meta']);

		$backupCache = new core_cache(self::BACKUP_CACHE);
		$backupCache->delete($keys['meta']);
		$backupCache->delete($keys['data']);

		return true;
	}

	/**
	 * Garbage collection method
	 *
	 * @param int $lifetime
	 */
	public function gc($lifetime) {
		return true;
	}

	/**
	 * A standardized means of generating cache keys
	 *
	 * @param string $id
	 * @return array
	 */
	private function getCacheKeys($id) {
		return array(
			'meta' => 'session-' . $id . '-meta',
			'data' => 'session-' . $id . '-data'
			);
	}
}
