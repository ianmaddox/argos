<?php
/**
 *
 * @package framework
 * @subpackage interface
 */
interface interface_db {
	/* Connection management */

	/**
	 * Connect to a database and store the resource
	 *
	 * @param string $host
	 * @param string $user
	 * @param string $pass
	 * @param string $database
	 * @param int $port
	 */
	public function __construct($host, $user, $pass, $database = false, $port = 3306);

	/**
	 * Connect to a database and store the resource
	 *
	 * @param string $host
	 * @param string $user
	 * @param string $pass
	 * @param string $database
	 * @param int $port
	 */
	public function initReadOnly($host, $user, $pass, $database = false, $port = 3306);

	/**
	 * Drop the db connection
	 */
	public function disconnect();

	/**
	 * Set the database for select*() queries to the write master database
	 * @param bool $nextQueryOnly Allows you to switch all queries to master.  USE WITH CAUTION AND DO NOT FORGET TO TURN IT BACK OFF!
	 */
	public function setSelectModeMaster($nextQueryOnly = true);

	/**
	 * Set the database for select*() queries to the read replica database
	 */
	public function setSelectModeReplica();


	/* Direct query and select methods */

	/**
	 * Perform a generic SQL query.  Don't tell it to cache if you are not running a SELECT.
	 *
	 * @param string $sql
	 * @return interface_dbResult
	 */
	public function query($sql);

	/**
	 * Perform a generic SQL query.  Don't tell it to cache if you are not running a SELECT.
	 *
	 * @param string $sql
	 * @return interface_dbResult
	 */
	public function read($sql);

	/**
	 * Return an entire resultset.
	 *
	 * @param string $sql
	 * @param const cache engine
	 * @param int cache entry TTL
	 * @return array
	 */
	public function selectAll($sql, $cacheEngine = false, $ttl = false);

	/**
	 * Return the first row of a resultset.
	 *
	 * @param string $sql
	 * @param const cache engine
	 * @param int cache entry TTL
	 * @return array
	 */
	public function selectRow($sql, $cacheEngine = false, $ttl = false);

	/**
	 * Return the first column of the results as an array
	 *
	 * @param string $sql
	 * @param const cache engine
	 * @param int cache entry TTL
	 * @return array
	 */
	public function selectCol($sql, $cacheEngine = false, $ttl = false);

	/**
	 * Return the first column of the first row of a resultset.
	 *
	 * @param string $sql
	 * @param const cache engine
	 * @param int cache entry TTL
	 * @return string
	 */
	public function selectVal($sql, $cacheEngine = false, $ttl = false);

	/**
	 * Return the first column of the first row of a resultset.
	 *
	 * @param string $sql
	 * @param const cache engine
	 * @param int cache entry TTL
	 * @return string
	 */
	public function selectCount($sql, $cacheEngine = false, $ttl = false);

	/* Prepared statements family of methods */

	/**
	 * Prepare a SQL template for binding and execution.
	 *
	 * @param string $sql
	 * @param int $ttl
	 * @return interface_dbStatement
	 */
	public function prepare($sql);

	/* Utility methods */

	/**
	 * Escape input
	 *
	 * @param string $input
	 * @return string
	 */
	public function escapeVal($input);

	/**
	 * Return the auto-increment ID of the last insert
	 *
	 * @return int
	 */
	public function lastInsertID();

	/**
	 * Return the number of rows affected/returned by the last query
	 *
	 * @return int
	 */
	public function numRows();

	/* Transactional methods */

	/**
	 * Turn autocommit on or off for transactions.
	 *
	 * @param bool $bool Toggle autocommit
	 */
	public function setAutocommit($bool);

	/**
	 * Commit a transaction
	 */
	public function commit();

	/**
	 * Roll back a transaction
	 */
	public function rollback();

	/**
	 * Reset the database connections
	 */
	public function resetConnection($mode);
}
