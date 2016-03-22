<?php

/**
 * The global/local configuration class
 *
 * @package framework
 * @subpackage cfg
 */
class cfg {

	private static $cfg;
	private static $sites = array();

	/**
	 * This is a utility class, and should not be constructed
	 */
	public function __construct() {
		trigger_error("Cannot instantiate static class");
	}

	/**
	 * Initialize the configuration.  Called by the global_prepend
	 *
	 * @param string $site
	 * @param string $file
	 */
	public static function loadConfig($site = false, $file = false)
	{
		// If site is not set, fall back to a possible environment setting
		if(empty($site)) {
			$site = !empty($_SERVER['sitename']) ? $_SERVER['sitename'] : '';
		}
		$vhost = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
		$cacheKey = "core_cfg|site=$site|file=$file|vhost={$vhost}";
		$cache = new core_cache(CACHE_MEMORY);
		$hostConf = $siteConf = $defaultDbConf = array();
		if(false == (self::$cfg = $cache->get($cacheKey))) {
			// Load config items from vhost
			$envVarPrefix = 'site_';
			$envVarPrefixLen = strlen($envVarPrefix);
			foreach($_SERVER as $key => $val)
			{
				if(strpos($key, $envVarPrefix) === 0) {
					$hostConf[substr($key, $envVarPrefixLen)] = $val;
				}
			}

			// Load config from prefs.xml
			$file = empty($file) ? ARGOS_HOME . '/etc/prefs.xml' : $file;
			$xml = simplexml_load_file($file);
			$data = self::sxml2array($xml);

			$globalConf = $data['global'];
			$dbConf = array('databases' => $data['databases']);

			// Load the site-specific portion of the config file
			if(!empty($site) && isset($data[$site]['env']) && is_array($data[$site]['env'])) {
				$siteConf = $data[$site]['env'];
				if(isset($siteConf['db'])) {
					$defaultDbConf = array('db' => $data['databases'][$siteConf['db']]);
					if(isset($data['databases'][$siteConf['db'] . '-read'])) {
						$defaultDbConf['db-read'] = $data['databases'][$siteConf['db'] . '-read'];
					}
				}
			}

			// Add in some overrides for CLI mode
			$cliConf = array();
			if(util_cli::isCliMode()) {
				$cliConf['dirHome'] = ARGOS_HOME . util_cli::getSiteDir();
			}

			// Grab the AWS instance ID.  If we are not in AWS (as evidenced by the hostname 'instance-data' not resolving), use NA
			$awsServerID = gethostbynamel('instance-data') === false ? 'NA' : file_get_contents('http://instance-data/latest/meta-data/instance-id');
			$globalConf['serverID'] = $awsServerID;
			$globalConf['site'] = $site;

			// Merge our config arrays together.  Latter entries override earlier.
			self::$cfg = array_merge($cliConf, $globalConf, $siteConf, $hostConf, $dbConf, $defaultDbConf);
			$cache->set($cacheKey, self::$cfg, 60 * 60 * 24 * 365);
		}
	}

	/**
	 * Fetch a config value
	 *
	 * @param string $key
	 * @return mixed array or string
	 */
	public static function get($key)
	{
		if(empty(self::$cfg)) {
			trigger_error('Config file not loaded.', E_USER_WARNING);
		}
		if(empty($key)) {
			return self::$cfg;
		}
		if(!isset(self::$cfg[$key])) {
			return false;
		}

		return self::$cfg[$key];
	}

	/**
	 * Load information about available sites
	 *
	 * @param string $file
	 * @return string[]
	 */
	public static function getSites($file = false) {
		if(empty(self::$sites)) {
			// Load config from prefs.xml
			$file = empty($file) ? ARGOS_HOME . '/etc/prefs.xml' : $file;
			$xml = simplexml_load_file($file);
			self::$sites = self::sxml2array($xml->global->sites->site);
		}
		return self::$sites;
	}

	/**
	 * Convert a SimpleXMLElement into an array.
	 *
	 * @param SimpleXMLElement $xml
	 * @return array
	 */
	public static function sxml2array($xml)
	{
		$crypt = new util_crypt();
		if(is_object($xml) && get_class($xml) == 'SimpleXMLElement') {
			$attributes = $xml->attributes();
			foreach($attributes as $key => $val) {
				if($key == 'encrypted' && $val == 'true') {
					$xml[0] = $crypt->decrypt($xml[0]);
				}
			}
			$xmlStr = $xml;
			$temp = array();
			$keys = array();
			foreach($xml as $key => $val)
			{
				if(in_array($key, $keys)) {
					if(!is_array($temp[$key])) {
						$temp[$key] = array($temp[$key]);
					}
					$temp[$key][] = $val;
				} else {
					$temp[$key] = $val;
				}
				$keys[] = $key;
			}
			$xml = $temp;
		}

		if(is_array($xml)) {
			if(count($xml) == 0) {
				return (string) $xmlStr; // for CDATA
			}
			foreach($xml as $key => $value) {
				if($key === 'comment') {
					continue;
				}
				$ret[$key] = self::sxml2array($value);
				if(!is_array($ret[$key])) {
					$ret[$key] = $ret[$key];
				}
			}
			return $ret;
		}
		return (string) $xml;
	}

	/**
	 * Get the user's IP address
	 *
	 * @return string
	 */
	public static function remoteAddr() {
		if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			return $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else if(isset($_SERVER['REMOTE_ADDR'])) {
			return $_SERVER['REMOTE_ADDR'];
		} else {
			trigger_error('Cannot find user IP address, choose from: ' . implode(', ', array_keys($_SERVER)), E_USER_WARNING);
			return '';
		}
	}

}
