<?php

final class core_cookie {

	/**
	 */
	private function __construct() { }

	/**
	 * Get a cookie value
	 *
	 * @param string $name
	 * @param mixed $default
	 * @param bool $setDefault
	 * @return mixed
	 */
	public static function get($name, $default = null, $setDefault = false) {
		if(isset($_COOKIE[$name])) {
			return $_COOKIE[$name];
		} else {
			if($default !== null && $setDefault) {
				self::set($name, $default);
			}
			return $default;
		}
	}

	/**
	 * Check if a cookie is present
	 *
	 * @param string $name
	 * @return bool
	 */
	public static function has($name) {
		return isset($_COOKIE[$name]);
	}

	/**
	 * Set a cookie
	 *
	 * $ttl, $path, and $domain use the session cookie values as defaults.
	 *
	 * @param string $name
	 * @param mixed $value
	 * @param int $ttl
	 * @param string $path
	 * @param string $domain
	 * @param bool $secure
	 * @param bool $httponly
	 * @return bool
	 */
	public static function set($name, $value, $ttl = null, $path = null, $domain = null, $secure = false, $httponly = false) {
		if(!headers_sent()) {
			$params = session_get_cookie_params();
			is_null($ttl) && $ttl = $params['lifetime']; // 0 is okay
			$path || $path = $params['path'];
			$domain || $domain = $params['domain'];
			setcookie($name, $value, ($ttl ? time() + $ttl : 0), $path, $domain, $secure, $httponly);
			$_COOKIE[$name] = $value;
			return true;
		} else {
			trigger_error(__METHOD__ . ": too late to set cookie {$name}={$value}", E_USER_WARNING);
			return false;
		}
	}

}
