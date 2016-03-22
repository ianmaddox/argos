<?php

/**
 * siteTest.variantViews
 *
 * @package framework
 * @subpackage db_siteTest
 */
class db_siteTest_variantView extends core_row {

	const DB = 'siteTest';
	const TABLE = 'variantView';
	const PK = 'id';

	/**
	 * Create an instance
	 *
	 * @param db_siteTest_variant $variant
	 * @param string $sessionID
	 * @return db_siteTest_variantView
	 */
	public static function createInstance(db_siteTest_variant $variant, $sessionID) {
		if (!$variant->id) {
			trigger_error(__METHOD__ . ': variant must be saved first', E_USER_ERROR);
			return null;
		}

		$obj = new self();
		$obj->sessionID = $sessionID;
		$obj->variantFK = $variant->id;
		return $obj;
	}

	/**
	 * Fetch an object instance based on an ID
	 *
	 * @param string $id
	 * @return db_siteTest_variantView
	 */
	public static function getInstance($id = null) {
		return new self($id);
	}

}
