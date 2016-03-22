<?php

/**
 * core_row creates a common method for creating CRUD classes for tables
 * This abstract class requires three methods to be defined within any child class.
 *
 * Note that this class assumes that columns date_added and date_modified exist in the table.
 *
 * Class Usage:
 * Create a class that extends this one and implements the required abstract methods.
 * The primary approach to instantiate the class is through getInstance() methods.
 * getInstanceBy*() methods are useful when a table has more than one UNIQUE column or
 * combination of columns.
 *
 * The vanilla __construct should be used whenever a new row is to be created and inserted.
 * A table class must implement the constant const::PK if the primary key in the table is
 * not named 'id'.  It may also implement either of the following to override defaults.
 *	const::DB
 * 	const::TABLE
 *
 * The child __construct() method can call setFieldPermissions() to declare specific columns
 * in the table read only or unavailable.  This is a blacklist, so all columns are assumed to
 * be full read/write unless otherwise indicated.
 *
 *
 * Object Usage:
 * Any class that extends core_table gets some handy CRUD functionality:
 *    load() is useful for loading a single row based on any primary key value.  The second argument
 *    is optional when the primary key column is queried.
 *
 * jumpStart($data) is useful when load() isn't flexible enough.  Write your own getInstanceBy*()
 *    method and fetch the data any way you want.  Then load the row into the object with a call to
 *    jumpStart().
 *
 * get($col) returns any value not declared NO_ACCESS
 * set($col, $val) sets any value not declared NO_ACCESS or READ_ONLY
 *
 * get*() is an alias for get($col).  Example getUrldir() is an alias for get('urldir').
 * set*($val) is an alias for set($col).  Example setUrldir($val) is an alias for set('urldir', $val).
 *    For both of the above magic methods, the first character of the column is changed to lower case.
 *
 * save() writes the object contents to the database.  It automatically inserts or updates based on
 *    the presence of a primary key value.  On update, this only saves the columns that were changed.
 *
 * Calls to set() are not immediately saved to DB and must be explicitly written with a call to save()
 *
 * core_row implements Countable, Iterator, and ArrayAccess.  The data is associative based on the
 * primary key value.  Please do not expect to do direct element access if you don't already know
 * the primary key value.  Instead, please use any of the iterator approaches such as foreach() or
 * next().
 *
 * @package framework
 * @subpackage core
 */
abstract class core_row implements Countable, Iterator, ArrayAccess {

	const READ_WRITE = 0;
	const READ_ONLY = 1;
	const NO_ACCESS = 2;
	const SQL_COMMAND_NOW = '{{SQL_COMMAND_NOW}}';

	private $data = array();
	private $caret = 0;

	/**
	 * @var const $defaultCache Default cache store
	 */
	protected $defaultCache = CACHE_NONE;

	/**
	 * @var int $defaultCacheTtl Default cache TTL
	 */
	protected $defaultCacheTtl = false;

	/**
	 * @var array $fieldPermissions Array of field permissions
	 */

	protected $db;
	protected $table;
	protected $pk;

	protected $fieldPermissions = array();

	public abstract static function getInstance($rowID);

	/**
	 *
	 * @param mixed $pkID
	 */
	public function __construct($pkID = false) {
		$this->init();
		if (!empty($pkID)) {
			$this->load($pkID);
		}
		if(empty($this->data[$this->caret])) {
			// If we weren't given a pkID to load or the value couldn't be found in the DB, start with a new row
			$this->data[$this->caret] = array(
				'curr' => array(),
				'orig' => array(),
				'isNew' => true,
				'valid' => true
			);
		}
	}

	/**
	 * Build an INSERT query for new data
	 *
	 * @param bool $async
	 * @param string $db
	 * @param string $table
	 * @param mixed[] $data
	 * @return string
	 */
	protected function buildSaveInsert($async, $db, $table, array $data) {
		$delayed = ($async ? 'DELAYED' : '');
		$kvp = $this->makeKvp($data);
		return "INSERT {$delayed} INTO `{$db}`.`{$table}` SET {$kvp}";
	}

	/**
	 * Build an UPDATE query for existing data
	 *
	 * @param bool $async
	 * @param string $db
	 * @param string $table
	 * @param mixed[] $data
	 * @param mixed $pk
	 * @return string
	 */
	protected function buildSaveUpdate($async, $db, $table, array $data, $pk) {
		$lowPri = ($async ? 'LOW_PRIORITY' : '');
		$kvp = $this->makeKvp($data);
		return "UPDATE {$lowPri} `{$db}`.`{$table}` SET {$kvp} WHERE `{$this->pk}` = '{$pk}'";
	}

	/**
	 * Initialize the vital class vars db, table, and pk.
	 * Not part of the constructor because the constructor may be overridden and not called in time.
	 */
	private function init() {
		list(, $this->db, $this->table) = explode('_', get_called_class(), 3);
		$this->db = defined('static::DB') ? static::DB : $this->db;
		$this->table = defined('static::TABLE') ? static::TABLE : $this->table;
		$this->pk = defined('static::PK') ? static::PK : 'id';
	}

	/**
	 * Return the primary key value.  Returns false if there is no value available.
	 * @return string
	 */
	public function getPK() {
		return isset($this->data[$this->caret]['curr'][$this->pk]) ? $this->data[$this->caret]['curr'][$this->pk] : false;
	}

	/**
	 * Inject the data into the class.  Useful for when you have a row already and don't want to read from the DB.
	 * @param array $data
	 */
	protected function jumpStart(array $data) {
		if(empty($data[$this->pk])) {
			$this->caret = 0;
			$this->data[0] = array(
				'curr' => $data,
				'orig' => array(),
				'isNew' => true,
				'valid' => true
			);
		} else {
			$this->caret = $data[$this->pk];
			$this->data[$this->caret] = array(
				'curr' => $data,
				'orig' => $data,
				'isNew' => false,
				'valid' => true
			);
		}
	}

	/**
	 * Inject multiple rows of data into the class.  Useful for when you have multiple rows to jumpStart() at once.
	 * @param array $data an array of row arrays
	 */
	protected function jumpStartArr(array $data) {
		$this->data = array();
		$hasnew = false;
		foreach($data as $row) {
			if(empty($row[$this->pk])) {
				if($hasnew) {
					trigger_error(__METHOD__ . ': cannot jumpStartArr with more than one new row', E_USER_ERROR);
					continue;
				} else {
					$hasnew = true;
				}
			}
			$this->jumpStart($row);
		}
		$this->rewind();
	}

	/**
	 * Set a value by key name
	 * @param string $key
	 * @param mixed $val
	 */
	public function set($key, $val) {
		// Check whether the field is writable
		if (!isset($this->fieldPermissions[$key]) || $this->fieldPermissions[$key] == self::READ_WRITE) {
			$this->data[$this->caret]['curr'][$key] = $val;
			$this->data[$this->caret]['valid'] = true;
		} else {
			trigger_error("Column $key is not publicly accessible", E_USER_ERROR);
		}
	}

	/**
	 *
	 * @param string $key
	 * @param string $value
	 */
	public function __set($key, $value) {
		$this->set($key, $value);
	}

	/**
	 * Get a value by key name
	 * @param string $key
	 * @return string
	 */
	public function get($key) {
		// Check whether this column is readable
		if (
				!isset($this->fieldPermissions[$key])
				|| $this->fieldPermissions[$key] == self::READ_ONLY
				|| $this->fieldPermissions[$key] == self::READ_WRITE
		) {
			return isset($this->data[$this->caret]['curr'][$key]) ? $this->data[$this->caret]['curr'][$key] : false;
		} else {
			trigger_error("Column $key is not publicly accessible", E_USER_ERROR);
		}
	}

	/**
	 * Alias for self::get()
	 * @param string $key
	 * @return string
	 */
	public function __get($key) {
		return $this->get($key);
	}

	/**
	 * Returns all data for the active row
	 */
	public function getData() {
		return $this->data[$this->caret]['curr'];
	}

	/**
	 * Writes the object contents to the database.  It automatically inserts or
	 * updates based on the presence of a primary key value. On update, this only
	 * saves the columns that were changed. Calls to set() are not immediately saved
	 * to DB and must be explicitly written with a call to save()
	 *
	 * @param bool $async asynchronous DB write?
	 */
	public function save($async = false) {
		$dbObj = core_db::getDB();
		$pk = $dbObj->escapeVal($this->pk);
		$table = $dbObj->escapeVal($this->table);
		$db = $dbObj->escapeVal($this->db);

		$pkID = isset($this->data[$this->caret]['curr'][$pk]) ? $this->data[$this->caret]['curr'][$pk] : false;
		$data = array();


		foreach ($this->data[$this->caret]['curr'] as $key => $val) {
			// For update queries, strip out the values that are unchanged so we minimize stepping on near-simultaneous updates.
			if ($this->data[$this->caret]['isNew'] == false &&
				// Never change the primary key
				($key == $pk
				// If the field exists (whether or not it is null) and the data has changed, update it
				|| (
					(isset($this->data[$this->caret]['orig'][$key]) || is_null($this->data[$this->caret]['orig'][$key]))
					&& $this->data[$this->caret]['orig'][$key] == $val
				)
				)
			) {
				continue;
			}
			$data[$key] = $val;
		}
		// If the table has a date_added column and the row is new, set the value.
		if ($this->data[$this->caret]['isNew'] == true || empty($pkID)) {
			$data['date_added'] = self::SQL_COMMAND_NOW;
		}
		$data['date_modified'] = self::SQL_COMMAND_NOW;

		// Insert or update the record, where appropriate
		if ($this->data[$this->caret]['isNew'] == true || empty($pkID)) {
			// New row.  Perform an insert.

			$success = $dbObj->query($this->buildSaveInsert($async, $db, $table, $data));
			if(!$success) {
				return false;
			}
			// This next line is a workaorund for tables that don't have auto_increment on their primary key column.
			// Tables without auto_increment MUST have their PK written manually or you will get duplicate entry errors.
			$rowID = !empty($data[$this->pk]) ? $data[$this->pk] : $dbObj->lastInsertID();
			$return = $this->data[$this->caret]['curr'][$pk] = $rowID;
			$this->data[$this->caret]['isNew'] = false;

			// Since we wrote a new row, its caret ID just got real.  Wipe out the old one and re-load the row from DB.
			unset($this->data[$this->caret]);
			$this->caret = $rowID;

			// In the event the row written does not populate every column, we need to fetch back all of the other cols.
			$this->load($rowID, $this->pk, 1, false, false, true);

		} else {
			// Existing row.  Perform an update.
			// Handle the case where the code doesn't change anything but calls save() anyway.
			if (empty($data)) {
				return $this->caret;
			}
			$dbObj->query($this->buildSaveUpdate($async, $db, $table, $data, $dbObj->escapeVal($pkID)));
			$return = $this->data[$this->caret]['curr'][$pk];
		}
		// Now that we've saved, we have the "original" data
		$this->data[$this->caret]['orig'] = $this->data[$this->caret]['curr'];

		// Don't need to fetch the latest from the DB unless asked because we don't overwrite cells unless
		// they were explicitly updated
		return $return;
	}

	/**
	 * Iterate over all of the loaded rows and save each one individually.
	 * Note: This method will not reset the caret so any external iterations will not be disturbed
	 */
	public function saveAll() {
		$originalCaret = $this->caret;
		foreach($this as $key => $row) {
			$this->save();
		}
		$this->caret = $originalCaret;
	}

	/**
	 * Fetch a row by its primary key value
	 * @param string $val
	 * @param string $col
	 * @param const cache engine
	 * @param int cache ttl
	 * @param bool $useWriteMaster
	 */
	protected function load($val, $col = false, $limit = 1, $cacheEngine = false, $ttl = false, $useWriteMaster = false) {
		$col = empty($col) ? $this->pk : $col;
		$this->loadArr(array($col => $val), $limit, $cacheEngine ?: $this->defaultCache, $ttl, $useWriteMaster);
	}

	/**
	 * Load a row based on a multi-column query
	 * Store a copy of that data in an array called origData.  This array will remain untouched, but
	 * we need to store a copy of the rows (or perhaps a hash of that data) so that when the data is
	 * saved, we can tell what fields were changed and only save the updated cells.  Storing a copy
	 * of the original content is better than an "isChanged" flag because it is possible for code to
	 * change a value and then change it back before saving.
	 *
	 * @internal string orderBy Set this to the column name that you want to sort by
	 * @internal string orderDir Set the order to sort the data in DESC || ASC
	 * @param array $arr
	 * @param const cache engine
	 * @param int cache ttl
	 * @param bool $useWriteMaster
	 * @return bool
	 */
	protected function loadArr($arr, $limit = 1, $cacheEngine = false, $ttl = false, $useWriteMaster = false) {
		$this->init();
		$dbObj = core_db::getDB();
		if($useWriteMaster) {
			$dbObj->setSelectModeMaster(true);
		}

		$this->data = array();

		$db = $dbObj->escapeVal($this->db);
		$table = $dbObj->escapeVal($this->table);

		$where = $this->makeKvp($arr, 'AND');
		$sql = 'SELECT * FROM `' . $db . '`.`' . $table . '` WHERE ' . $where;
		$sql .= ($this->orderBy) ? ' ORDER BY ' . $this->orderBy . ' ' . $this->orderDir : '';
		$sql .= ($limit) ? ' LIMIT ' . $limit : '';

		$rows = $dbObj->selectAll($sql, $cacheEngine, $ttl);

		$return = false;
		foreach($rows as $row) {
			$return = true;
			if (!empty($row) && count($row)) {
				$this->caret = $row[$this->pk];
				$this->data[$this->caret]['isNew'] = false;
				$this->data[$this->caret]['orig'] = $this->data[$this->caret]['curr'] = $row;
				$this->data[$this->caret]['valid'] = true;
			}
		}

		if(!$return) {
			$this->caret = 0;
			$this->data[0] = array(
				'isNew' => true,
				'orig' => array(),
				'curr' => array(),
				'valid' => false
			);
		}

		$this->rewind();
		return $return;
	}

	/**
	 * Define public fields with an array
	 * @param array $fieldArr
	 * @param bool read only
	 */
	protected function setFieldPermissions($fieldArr, $access = self::NO_ACCESS) {
		if (!is_array($fieldArr)) {
			$fieldArr = array($fieldArr);
		}
		foreach ($fieldArr as $field) {
			$this->fieldPermissions[$field] = $access;
		}
	}

	/**
	 * Turn an array of keys and values to a SQL kvp string.
	 * The value of each key-value pair can be a literal, or "{{NOW()}}",
	 * or any SQL function enclosed in "{{ }}".
	 *
	 * The value of each key-value pair can be a literal, or "{{NOW()}}", or any SQL function enclosed in "{{ }}".
	 * @param array $arr
	 * @return string
	 */
	protected function makeKvp($arr, $glue = ',') {
		$dbObj = core_db::getDB();
		$valArr = array();

		foreach ($arr as $key => $val) {
			$key = $dbObj->escapeVal($key);
			$operator = '=';
			if ($val instanceof DateTime) {
				$val = (string) core_db_expression::dateTime($val);
			} elseif ((string) $val == self::SQL_COMMAND_NOW) {
				$val = 'NOW()';
			} elseif (is_null($val)) {
				$val = 'NULL';
			} elseif ($val instanceof core_db_expression) {
				$val = (string) $val;
			} elseif ($val instanceof core_db_comparison) {
				$val = (string) $val;
				$operator = '';
			} else {
				$val = "'" . $dbObj->escapeVal($val) . "'";
			}
			$valArr[] = "`$key` $operator $val";
		}
		if (empty($valArr)) {
			// No changes.  Nothing to save.
			return;
		}
		return implode(" {$glue} ", $valArr);
	}

	/**
	 * #################################################################################################################
	 * Interface methods for Iterator, Countable, and ArrayAccess
	 * #################################################################################################################
	 */

	/**
	 * @see Iterator
	 *
	 * @return mixed
	 */
	public function current() {
		return $this;
	}

	/**
	 * @see Iterator
	 *
	 * @return scalar
	 */
	public function key() {
		return key($this->data);
	}

	/**
	 * @see Iterator
	 */
	public function next() {
		next($this->data);
		$this->caret = key($this->data);
	}

	/**
	 * @see Iterator
	 */
	public function prev() {
		prev($this->data);
		$this->caret = key($this->data);
	}

	/**
	 * @see Iterator
	 *
	 */
	public function rewind() {
		reset($this->data);
		$this->caret = key($this->data);
	}

	/**
	 * @see Iterator
	 *
	 * @return bool
	 */
	public function valid() {
		return isset($this->data[$this->caret]) && $this->data[$this->caret]['valid'];
	}

	/**
	 * @see Countable
	 *
	 * @return int
	 */
	public function count() {
		return count($this->data) == 1 && (empty($this->data[$this->caret]['curr']) || !$this->data[$this->caret]['valid']) ? 0 : count($this->data);
	}

	/**
	 * @see ArrayAccess
	 *
	 * @param mixed $offset
	 * @return bool
	 */
	public function offsetExists($offset) {
		return isset($this->data[$offset]) && $this->data[$offset]['valid'];
	}

	/**
	 * @see ArrayAccess
	 *
	 * @param mixed $offset
	 * @return mixed
	 */
	public function offsetGet($offset) {
		if($this->offsetExists($offset)) {
			$this->caret = $offset;
			return $this;
		}
		return false;
	}

	/**
	 * @see ArrayAccess
	 *
	 * @param mixed $offset
	 * @param mixed $value
	 */
	public function offsetSet($offset, $value) {
		trigger_error('offsetSet ArrayAccess method not available on core_row', E_USER_WARNING);
	}

	/**
	 * @see ArrayAccess
	 *
	 * @param mixed $offset
	 */
	public function offsetUnset($offset) {
		trigger_error('offsetUnset ArrayAccess method not available on core_row', E_USER_WARNING);
	}

}
