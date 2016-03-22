<?php

/**
 * Mysqli driver.  See interface_db for documentation
 *
 * @package framework
 * @subpackage db
 */
class core_db_mysqli implements interface_db {
	const MODE_READWRITE = 'rw';
	const MODE_READ = 'r';

	/** @var mysqli $dbo */
	private $dbo;
	private $dboRead;
	private $isReadModeSet = false;
	private $lastQueryType;
	private $selectMode;

	/**
	 * @var int binary stack/tally that keeps track of the number of times the class has been instructed to read from the master DB
	 *	If the value is 0, master reads are not sticky and should be reset to the replica */
	private $queryModeMasterTally = 0;

	/** @var string $statement The SQL statement */
	private $statement;

	/** @var array $bindArr The values to replace holder with */
	private $bindArr = array();

	/** @var string $bindKey The key to replace */
	private $bindKey = '';

	/** @var int $numRows Number of rows affected/returned by the last query */
	private $numRows = 0;

	/**
	 * Constructor
	 *
	 * @param string $host DB Host
	 * @param string $user DB User
	 * @param string $pass DB User Password
	 * @param string $database The Database
	 * @param string $port The port
	 */
	public function __construct($host, $user, $pass, $database = false, $port = 3306) {
		$this->openConnection(self::MODE_READWRITE, $host, $user, $pass, $database, $port);

		// Alias the read connection to the write one in case nobody calls initReadOnly()
		$this->dboRead =& $this->dbo;
		$this->lastQueryType = self::MODE_READWRITE;
		$this->setSelectModeReplica();
	}

	/**
	 * Set the database for select*() queries to the write master database
	 * @param bool $nextQueryOnly Allows you to switch all queries to master.  USE WITH CAUTION AND DO NOT FORGET TO TURN IT BACK OFF!
	 */
	public function setSelectModeMaster($nextQueryOnly = true) {
		$this->selectMode = self::MODE_READWRITE;
		// If the master query mode is sticky, increment the tally. Otherwise, leave it as is.
		$this->queryModeMasterTally += (bool)$nextQueryOnly ? 0 : 1;
	}

	/**
	 * Set the database for select*() queries to the read replica database
	 * Update the queryModeMasterTally value.  If the stack is empty, don't decrement.  Otherwise reduce it by one.
	 */
	public function setSelectModeReplica() {
		$this->queryModeMasterTally -= $this->queryModeMasterTally < 1 ? 0 : 1;
		$this->selectMode = self::MODE_READ;
	}

	/**
	 * Open a secondary database connection
	 *
	 * @param string $host
	 * @param string $user
	 * @param string $pass
	 * @param string $database
	 * @param int $port
	 */
	public function initReadOnly($host, $user, $pass, $database = false, $port = 3306) {
		if($this->isReadModeSet) {
			trigger_error("Cannot re-init read only database connection.", E_USER_ERROR);
		}
		unset($this->dboRead);
		$this->openConnection(self::MODE_READ, $host, $user, $pass, $database, $port);
		$this->isReadModeSet = true;
	}

	/**
	 * Create the actual DB connection
	 *
	 * @param const $mode
	 * @param string $host
	 * @param string $user
	 * @param string $pass
	 * @param string $database
	 * @param int $port
	 */
	private function openConnection($mode, $host, $user, $pass, $database, $port) {
		if($mode != self::MODE_READ && $mode != self::MODE_READWRITE) {
			trigger_error('Invalid connection mode provided: "$mode"', E_USER_ERROR);
		}
		$dbo = new mysqli($host, $user, $pass, $database, $port);

		// Collect the connection error message in a backwards-compatible manner
		$connError = $dbo->connect_error;
		if(!$dbo || !empty($connError)) {
			trigger_error($connError, E_USER_ERROR);
		}

		// Disabled for now until we have no Latin1
		// $dbo->set_charset('utf8');

		if($mode == self::MODE_READWRITE) {
			$this->dbo = $dbo;
		} else {
			$this->dboRead = $dbo;
		}

	}

	/**
	 * Close the connection
	 */
	public function disconnect() {
		$this->dbo->close();
		unset($this->dbo);

		if(!empty($this->dboRead)) {
			$this->dboRead->close();
			unset($this->dboRead);
		}
	}

	/**
	 * Generate a cache key for a query
	 *
	 * @param string $sql
	 * @return string
	 */
	private function makeCacheKey($sql) {
		return md5($sql);
	}

	/**
	 * Fetch a recordset from cache
	 *
	 * @param string $sql
	 * @param const $cacheEngine
	 * @param string $method
	 * @return mixed
	 */
	private function getCache($sql, $cacheEngine, $method) {
		if($cacheEngine == CACHE_NONE) {
			return null;
		}
		$cacheKey = $method . '::' . $this->makeCacheKey($sql);
		$cache = new core_cache($cacheEngine);
		$data = $cache->get($cacheKey);
		if($data !== null) {
			return($data['d']);
		}
		return null;
	}

	/**
	 * Store a recordset in cache
	 *
	 * @param mixed $data
	 * @param string $sql
	 * @param const $cacheEngine
	 * @param string $method
	 * @param int $ttl
	 */
	private function setCache($data, $sql, $cacheEngine, $method, $ttl) {
		$cacheData = array();

		// If we're in development mode, tack the SQL query onto the cached data for debug purposes
		if(cfg::get('devMode') == true) {
			$cacheData['sql'] = $sql;
		}
		if(empty($data)) {
			$data = array();
		}
		$cacheData['d'] = $data;

		$cacheKey = $method . '::' . $this->makeCacheKey($sql);
		$cache = new core_cache($cacheEngine);
		$cache->set($cacheKey, $cacheData, $ttl);
	}

	/**
	 * Run a query against the master DB
	 *
	 * @param string $sql The SQL Statement
	 * @return core_db_mysqliResult $resultObj
	 */
	public function query($sql) {
		return $this->doQuery($sql, self::MODE_READWRITE);
	}

	/**
	 * Run a select query.  If $this->selectMode has been toggled by setSelectModeMaster(), use the read/write master
	 * DB connection, otherwise use the read-only slave.  If $this->queryModeMasterTally is zero, set the
	 * selectMode back to MODE_READ.
	 *
	 * @param type $sql
	 */
	public function read($sql) {
		$resultObj = $this->doQuery($sql, $this->selectMode);
		if($this->queryModeMasterTally < 1 && $this->selectMode != self::MODE_READ) {
			$this->selectMode = self::MODE_READ;
		}
		return $resultObj;
	}

	private function doQuery($sql, $mode) {
		if($mode == self::MODE_READWRITE) {
			$dbo =& $this->dbo;
		} elseif($mode == self::MODE_READ) {
			$dbo =& $this->dboRead;
		} else {
			trigger_error("Invalid query mode: '$mode'", E_USER_ERROR);
		}
//logit($sql);$start=microtime();
		$success = $dbo->real_query($sql);
//logit(microtime() - $start, 'runtime');
		$this->lastQueryType = $mode;

		if(!$success) {
			// Collapse newlines and large whitespace
			$errSql = preg_replace("![\s]+!",' ',$sql);

			$caller = '';
			foreach(debug_backtrace(0) as $bt) {
				if(isset($bt['class']) && $bt['class'] != __CLASS__) {
					$caller = " called from {$bt['class']}::{$bt['function']} ({$bt['file']} on line {$bt['line']}),";
					break;
				} else if(!isset($bt['class']) && isset($bt['file'])) {
					$caller = " called from {$bt['file']} on line {$bt['line']},";
					break;
				}
			}
			trigger_error($dbo->error.'|query: '.$errSql.'|mode: write|' . $caller, E_USER_WARNING);
		}

		$rs = $dbo->store_result();
		$resultObj = new core_db_mysqliResult($rs, $success);
		$this->lastErr = $dbo->errno;
		$this->numRows = (is_bool($rs) ? $dbo->affected_rows : $resultObj->getCount());

		return $resultObj;
	}

	/**
	 * Return all the possible rows for the query
	 *
	 * @param string $sql The SQL Statement
	 * @param const $cacheEngine The cache engine to use
	 * @param int $ttl Time to live for the cache
	 * @return array
	 */
	public function selectAll($sql, $cacheEngine = CACHE_NONE, $ttl = false) {
		if($cacheEngine && ($data = $this->getCache($sql, $cacheEngine, __METHOD__)) !== null) {
			return($data);
		}

		$res = $this->read($sql);
		$data = array();

		// When mysqlnd becomes available, we will be able to take advantage of fetch_all()
		while($tmp = $res->getRow()) {
			$data[] = $tmp;
		}

		if($cacheEngine) {
			$this->setCache($data, $sql, $cacheEngine, __METHOD__, $ttl);
		}

		return $data;
	}

	/**
	 * Return a specific column for the query
	 *
	 * @param string $sql The SQL Statement
	 * @param const $cacheEngine The cache engine to use
	 * @param int $ttl Time to live for the cache
	 * @return array
	 */
	public function selectCol($sql, $cacheEngine = CACHE_NONE, $ttl = false) {
		if($cacheEngine && ($data = $this->getCache($sql, $cacheEngine, __METHOD__)) !== null) {
			return($data);
		}

		$res = $this->read($sql);
		$data = array();

		// When mysqlnd becomes available, we will be able to take advantage of fetch_all()
		while($tmp = $res->getVal()) {
			$data[] = $tmp;
		}

		if($cacheEngine) {
			$this->setCache($data, $sql, $cacheEngine, __METHOD__, $ttl);
		}

		return $data;
	}

	/**
	 * Return a row for the query
	 *
	 * @param string $sql The SQL Statement
	 * @param const $cacheEngine The cache engine to use
	 * @param int $ttl Time to live for the cache
	 * @return array
	 */
	public function selectRow($sql, $cacheEngine = CACHE_NONE, $ttl = false) {
		if($cacheEngine && ($data = $this->getCache($sql, $cacheEngine, __METHOD__)) !== null) {
			return($data);
		}

		$res = $this->read($sql);

		$data = $res->getRow();
		if($cacheEngine) {
			$this->setCache($data, $sql, $cacheEngine, __METHOD__, $ttl);
		}

		return $data;
	}

	/**
	 * Return a value for the query
	 *
	 * @param string $sql The SQL Statement
	 * @param const $cacheEngine The cache engine to use
	 * @param int $ttl Time to live for the cache
	 * @return array
	 */
	public function selectVal($sql, $cacheEngine = CACHE_NONE, $ttl = false) {
		if($cacheEngine && ($data = $this->getCache($sql, $cacheEngine, __METHOD__)) !== null) {
			if(empty($data)) {
				$data = false;
			}
			return($data);
		}

		$res = $this->read($sql);
		$data = $res->getVal();
		if(empty($data)) {
			$data = false;
		}

		if($cacheEngine) {
			$this->setCache($data, $sql, $cacheEngine, __METHOD__, $ttl);
		}

		return $data;
	}

	/**
	 * Return a count for the query
	 *
	 * @param string $sql The SQL Statement
	 * @param const $cacheEngine The cache engine to use
	 * @param int $ttl Time to live for the cache
	 * @return array
	 */
	public function selectCount($sql, $cacheEngine = CACHE_NONE, $ttl = false) {
		if($cacheEngine && ($data = $this->getCache($sql, $cacheEngine, __METHOD__)) !== null) {
			return($data);
		}

		$res = $this->read($sql);
		$data = $res->getCount();

		if($cacheEngine) {
			$this->setCache($data, $sql, $cacheEngine, __METHOD__, $ttl);
		}

		return $data;
	}

	/**
	 * Prepare a statement for the query.  Uses write master DB.
	 *
	 * @param string $sql The SQL Statement
	 * @param const MODE_READ or MODE_READWRITE
	 * @return array
	 */
	public function prepare($sql) {
		$stmt = $this->dbo->prepare($sql);
		if(!$stmt) {
			trigger_error($this->dbo->error, E_USER_WARNING);
			return false;
		}

		return new core_db_mysqliStatement($stmt);
	}

	/**
	 * Real escape the value to make it DB safe.
	 *
	 * @param string $input
	 * @return string
	 */
	public function escapeVal($input) {
		return $this->dboRead->real_escape_string($input);;
	}

	/**
	 * Return the last ID from the insert
	 * Used only for the write server
	 *
	 * @return int
	 */
	public function lastInsertID() {
		return $this->dbo->insert_id;
	}

	/**
	 * Return the number of rows affected/returned by the last query
	 *
	 * @return int
	 */
	public function numRows() {
		return $this->numRows;
	}

	/**
	 * Set the autocommit value for the transaction
	 * Used only for the write server
	 *
	 * @param boolean $bool
	 */
	public function setAutocommit($bool) {
		$this->dbo->autocommit($bool);
	}

	/**
	 * Commit the transaction
	 * Used only for the write server
	 */
	public function commit() {
		$this->dbo->commit();
	}

	/**
	 * Rollback the transaction
	 * Used only for the write server
	 */
	public function rollback() {
		$this->dbo->rollback();
	}

	/**
	 * Get the last error from the DB
	 * Returns the value from the last query run, either read or write server
	 *
	 * @return int
	 */
	public function getLastError() {
		if($this->lastQueryType == self::MODE_READWRITE) {
			return $this->dbo->errno;
		} else {
			return $this->dboRead->errno;
		}
	}

	/**
	 * Resets the database connections if need be
	 * @param const $mode Mode for the connection to reset or null for both
	 */
	public function resetConnection($mode = null) {
		if($mode == self::MODE_READWRITE) {
			$success = $this->dbo->ping();
		} else if($mode == self::MODE_READ) {
			$success = $this->dboRead->ping();
		} else {
			$success = (!$this->dbo || $this->dbo->ping());
			$successR = (!$this->dboRead || $this->dboRead->ping());
			$success = $success && $successR;
		}
		return $success;
	}

}
