<?php

/**
 * siteTest.tests
 *
 * @package framework
 * @subpackage db_siteTest
 *
 * @property-read int $id
 * @property-read string $date_added
 * @property-read string $date_modified
 * @property string $friendlyName
 * @property-read string $name
 * @property STATE $state
 * @property float $percentage
 * @property-read int $winningVariantFK
 */
class db_siteTest_test extends core_row {

	const DB = 'siteTest';
	const TABLE = 'test';
	const PK = 'id';

	const DEFAULT_CACHE = CACHE_DEFAULT;
	const DEFAULT_CACHE_TTL = 600; // 10m

	const STATE_DISABLED = 'disabled';
	const STATE_FINISHED = 'finished';
	const STATE_RUNNING = 'running';
	const STATE_STOPPED = 'stopped';

	protected $defaultCache = self::DEFAULT_CACHE;
	protected $defaultCacheTtl = self::DEFAULT_CACHE_TTL;

	/**
	 * Fetch an object instance based on an ID
	 *
	 * @param string $id
	 * @return db_siteTest_test
	 */
	public static function getInstance($id = null) {
		return new self($id);
	}

	/**
	 * Get a test by its name
	 *
	 * @param string $name Test name
	 * @param bool $running Only return the test if it is running
	 * @return db_siteTest_test
	 */
	public static function getNamedTest($name, $running = true) {
		$db = core_db::getDB();
		$sqlname = $db->escapeVal($name);
		$sqlfinished = $db->escapeVal(self::STATE_FINISHED);
		$sqlrunning = $db->escapeVal(self::STATE_RUNNING);

		$query = "SELECT * FROM `siteTest`.`test` WHERE `name` = '{$sqlname}'";
		if($running) {
			$query .= " AND `state` IN ('{$sqlfinished}', '{$sqlrunning}')";
		}
		$row = $db->selectRow($query, self::DEFAULT_CACHE, self::DEFAULT_CACHE_TTL);
		if($row) {
			$test = new self();
			$test->jumpStart($row);
			return $test;
		} else {
			return null;
		}
	}

	/**
	 * Get all running tests for a site
	 *
	 * @return db_siteTest_test[]
	 */
	public static function getRunningTests() {
		$db = core_db::getDB();

		$sqlfinished = $db->escapeVal(self::STATE_FINISHED);
		$sqlrunning = $db->escapeVal(self::STATE_RUNNING);
		$sql = "
			SELECT * FROM `siteTest`.`test`
			WHERE `state` IN ('{$sqlfinished}', '{$sqlrunning}')
		";
		$data = $db->selectAll($sql, self::DEFAULT_CACHE, self::DEFAULT_CACHE_TTL);
		$test = new self();
		$test->jumpStartArr($data);
		return $test;
	}

	/**
	 * Get this test's variants
	 *
	 * @return db_siteTest_variant[]
	 */
	public function getVariants() {
		return db_siteTest_variant::getInstancesFromTest($this);
	}

	/**
	 * Check if this test is still running (state is running or finished)
	 *
	 * @return bool
	 */
	public function isRunning() {
		return $this->state == self::STATE_RUNNING || $this->state == self::STATE_FINISHED;
	}

	/**
	 * Set a value by key name
	 *
	 * Disallows any changes if the test is disabled. Enforces ENUM values on $state.
	 *
	 * @param string $key
	 * @param mixed $val
	 */
	public function set($key, $val) {
		if($this->state == self::STATE_DISABLED && $key != 'state') {
			trigger_error(__METHOD__ . ': Test is disabled, all fields are locked', E_USER_ERROR);
			return;
		}
		if($key == 'state' && !in_array($val, array(self::STATE_DISABLED, self::STATE_FINISHED, self::STATE_RUNNING, self::STATE_STOPPED))) {
			trigger_error(__METHOD__ . ': Invalid value for "state", must be a STATE constant', E_USER_ERROR);
			return;
		}

		parent::set($key, $val);
	}

}
