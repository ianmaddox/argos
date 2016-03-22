<?php
/**
 * @see global_prepend.php for the cache engine alias constants
 *
 *
 * @package framework
 * @subpackage core
 */
define('CACHE_DEFAULT_TTL', 60 * 60 * 24);

class core_cache implements interface_cache
{
	/**
	 * @var string $engine The cache engine to use
	 */
	private $engine = false;

	/**
	 * @var string $engineName The cache engine name
	 */
	private $engineName = false;

	/**
	 * @var string $keyPrefix The key prefix
	 */
	private $keyPrefix = '';

	/**
	 * @var array $engines The engines
	 */
	private static $engines = array();
	var $enable_timer = false;

	/**
	 * Cache engine types
	 */
	const ENGINE_NONE = 'none';
	const ENGINE_VARIABLE = 'variable';
	const ENGINE_APC = 'apc';
	const ENGINE_DB = 'db';
	const ENGINE_MEMCACHED = 'memcached';
	const ENGINE_REDIS = 'redis';
	const ENGINE_S3 = 's3';

	/**
	 * Grab a cache instance.
	 *
	 * @param string $engine can be defined here.  Use the CACHE_* constants defined in global_prepend.php
	 * @param string $keyPrefix is used to namespace all cache keys used in a single instance.  Not normally used.
	 */
	public function __construct($engine, $keyPrefix = '')
	{
		if(isset($_REQUEST['_cache']) && $_REQUEST['_cache'] == '!no!') {
			$engine = self::ENGINE_NONE;
		}

		$this->engineName = $engine;
		$this->keyPrefix = $keyPrefix;
		if($engine != self::ENGINE_S3) {
			// Don't muck with S3 cache keys.  It breaks the file location.
			$this->keyPrefix .= !empty($_SERVER['cacheNamespace']) ? '-' . $_SERVER['cacheNamespace'] . '-' : '';
		}
		if(!isset(self::$engines[$engine])) {
			$class = 'core_cache_'.$engine;
			if(!isClassValid($class)) {
				trigger_error("Invalid cache engine: '$engine' $class", E_USER_ERROR);
			}
			self::$engines[$engine] = $this->engine = new $class;
		} else {
			$this->engine = self::$engines[$engine];
		}
	}

	/**
	 * Fetch a value from cache.  If the value returned is null, it is safe to assume there was no
	 * match found in cache.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function get($key)
	{
		if(isset($_REQUEST['_cache']) && $_REQUEST['_cache'] == '!wo!' && cfg::get('devMode') == true) {
			return false;
		}

		$key = $this->keyPrefix . $key;
		$result = $this->engine->get($key);

		return $result;
	}

	/**
	 * Store an item in cache.  Storing null values should be avoided because that signals to any
	 * getter code that no match was found in cache.
	 *
	 * @param string $key
	 * @param mixed $val
	 * @param int $ttl
	 * @param bool $fudge Whether to fudge the $ttl a bit to avoid a spike later
	 * @return mixed
	 */
	public function set($key, $val, $ttl = CACHE_DEFAULT_TTL, $fudge = false)
	{
		if(empty($key)) {
			trigger_error("CANNOT WRITE TO EMPTY CACHE KEY!", E_USER_WARNING);
		}
		if(isset($_REQUEST['_cache']) && $_REQUEST['_cache'] == '!ro!' && cfg::get('devMode') == true) {
			return false;
		}

		if(empty($ttl)) {
			$ttl = CACHE_DEFAULT_TTL;
		}

		// fudging a bit can help offset the cache spike that happens when a set
		// of keys all expire at the same time. 95-105% of the original TTL is about
		// * 1 minute: +/- 3 seconds
		// * 1 day:    +/- 1 hour
		// * 1 month:  +/- 36 hours
		// * 1 year:   +/- 18 days
		if($fudge && $ttl > 0) {
			$ttl = ceil($ttl * (rand() * 0.1 + 0.95));
		}

		$key = $this->keyPrefix . $key;
		$result = $this->engine->set($key,$val,$ttl);

		return $result;
	}

	/**
	 * Delete an item from cache
	 *
	 * @param mixed $key
	 * @return bool
	 */
	public function delete($key)
	{
		$key = $this->keyPrefix . $key;
		$result = $this->engine->delete($key);

		return $result;
	}
}
