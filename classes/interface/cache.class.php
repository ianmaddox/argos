<?php
/**
 *
 * @package framework
 * @subpackage interface
 */
interface interface_cache
{
	/**
	 * Get the value from the cache
	 *
	 * @param string $key
	 */
	public function get($key);

	/**
	 * Set the value in the cache
	 *
	 * @param string $key
	 * @param mixed $val
	 * @param int $ttl
	 */
	public function set($key,$val,$ttl = CACHE_DEFAULT_TTL);

	/**
	 * Delete an object from the cache
	 *
	 * @param string $key
	 */
	public function delete($key);
}
