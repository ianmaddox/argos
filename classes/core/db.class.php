<?php

/**
 * This is a singleton wrapper for various database drivers.
 * Call the method getDB with the connection name and an optional driver name,
 * and you will get a database instance to match.
 *
 * @author ianmaddox
 *
 * @package framework
 * @subpackage core
 */
class core_db {
	/**
	 * The MySQL driver
	 */
	const DRIVER_MYSQLI = 'core_db_mysqli';

	/**
	 * @var array $dbs Array of DBs
	 */
	private static $dbs = array();

	/**
	 * Constructor
	 */
	private function __construct() {

	}

	/**
	 * Fetch a singleton instance of a database connection, unique for each database
	 * configuration and driver.
	 *
	 * @param string $connectionID optional database ID (as tagged in prefs.xml)
	 * @param const $driver
	 * @return interface_db
	 */
	public static function getDB($connectionID = null, $driver = null) {
		$connName = empty($connectionID) ? 'DEFAULT' : $connectionID;

		/** @todo: Add validation here to allow for different drivers */
		$driver = self::DRIVER_MYSQLI;

		if(isset(self::$dbs[$driver][$connName])) {
			return self::$dbs[$driver][$connName];
		}

		if($connectionID) {
			// A specific DB connection has been specified.
			$dbConf = cfg::get('databases');
			if(!isset($dbConf[$connectionID])) {
				trigger_error("Could not find configuration for db '$connectionID'", E_USER_ERROR);
			}

			$conf = $dbConf[$connectionID];
			$readConf = $dbConf[$connectionID . '-read'];
		} else {
			// Use the site default DB connection.
			$conf = cfg::get('db');
			if(empty($conf)) {
				trigger_error('No default DB connection defined for this site and none specified in getDB call.', E_USER_ERROR);
			}

			$readConf = cfg::get('db-read');
			if(empty($conf)) {
				trigger_error('No default DB connection defined for this site and none specified in getDB call.', E_USER_ERROR);
			}

		}

		self::$dbs[$driver][$connName] = new $driver($conf['dbHost'], $conf['dbUser'], $conf['dbPW'], $conf['db']);
		if(!empty($readConf)) {
			self::$dbs[$driver][$connName]->initReadOnly($readConf['dbHost'], $readConf['dbUser'], $readConf['dbPW'], $readConf['db']);
		}
		return self::$dbs[$driver][$connName];

	}
}
