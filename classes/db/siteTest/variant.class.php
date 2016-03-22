<?php

/**
 * siteTest.variants
 *
 * @package framework
 * @subpackage db_siteTest
 *
 * @property-read $id
 * @property-read $date_added
 * @property-read $date_modified
 * @property $friendlyName
 * @property-read $name
 * @property-read $testFK
 * @property $weight
 */
class db_siteTest_variant extends core_row {

	const DB = 'siteTest';
	const TABLE = 'variant';
	const PK = 'id';

	const DEFAULT_CACHE = CACHE_DEFAULT;
	const DEFAULT_CACHE_TTL = 600; // 10m

	protected $defaultCache = self::DEFAULT_CACHE;
	protected $defaultCacheTtl = self::DEFAULT_CACHE_TTL;

	/**
	 * Fetch an object instance based on an ID
	 *
	 * @param string $id
	 * @return db_siteTest_variant
	 */
	public static function getInstance($id = null) {
		return new self($id);
	}

	/**
	 * Fetch object instances based on a test they belong to
	 *
	 * @param db_siteTest_test $test
	 * @return db_siteTest_variant[]
	 */
	public static function getInstancesFromTest(db_siteTest_test $test) {
		$obj = new self();
		$obj->load($test->id, 'testFK', 0, self::DEFAULT_CACHE, self::DEFAULT_CACHE_TTL);
		return $obj;
	}

	/**
	 * Get a test variant by its name
	 *
	 * @param db_siteTest_test $test
	 * @param string $name
	 * @return db_siteTest_variant
	 */
	public static function getNamedInstance(db_siteTest_test $test, $name) {
		if (!$test->id) {
			trigger_error(__METHOD__ . ': test must be saved first', E_USER_ERROR);
			return null;
		}

		$obj = new self();
		$obj->loadArr(array('name' => $name, 'testFK' => $test->id));
		return ($obj->id ? $obj : null);
	}

	/**
	 * Get a test variant according to a percentage value
	 *
	 * @param db_siteTest_test $test
	 * @param float $percentage Value >0.0 and <=1.0
	 * @return db_siteTest_variant
	 */
	public static function getWeightedInstance(db_siteTest_test $test, $percentage) {
		$db = core_db::getDB();
		$weightsum = $db->selectVal("SELECT SUM(`weight`) FROM `siteTest`.`variant` WHERE `testFK` = {$test->id}", CACHE_MEMORY, self::DEFAULT_CACHE_TTL);
		$target = $percentage * $weightsum;
		foreach(self::getInstancesFromTest($test) as $variant) {
			$target -= $variant->weight;
			if($target <= 0) {
				return $variant;
			}
		}

		// if we're here then we didn't find any variants at all...?
		trigger_error(__METHOD__ . ": test #{$test->id} ('{$test->name}') doesn't have any variants", E_USER_WARNING);
		return null;
	}

	/**
	 * Record a conversion using this test variant
	 *
	 * @param string $sessionID
	 * @param ecomm_purchInfo $purchInfo
	 * @param float $value
	 * @return db_siteTest_conversion
	 */
	public function recordConversion($sessionID, ecomm_purchInfo $purchInfo = null, $value = null) {
		$conversion = db_siteTest_conversion::createInstance($this, $sessionID, $purchInfo, $value);
		$conversion->save();
		return $conversion;
	}

	/**
	 * Record a view
	 *
	 * @param string $sessionID
	 * @return db_siteTest_variantView
	 */
	public function recordView($sessionID) {
		$view = db_siteTest_variantView::createInstance($this, $sessionID);
		$view->save();
		return $view;
	}

	/**
	 * Set the test for this variant
	 *
	 * @param db_siteTest_test $test
	 */
	public function setTest(db_siteTest_test $test) {
		if(!$test->id) {
			trigger_error(__METHOD__ . ': test must be saved first', E_USER_ERROR);
			return;
		}
		$this->testFK = $test->id;
	}

}
