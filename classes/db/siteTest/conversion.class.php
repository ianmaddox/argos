<?php

/**
 * siteTest.conversions
 *
 * @package framework
 * @subpackage db_siteTest
 */
class db_siteTest_conversion extends core_row {

	const DB = 'siteTest';
	const TABLE = 'conversion';
	const PK = 'id';

	/**
	 * Create an instance
	 *
	 * @param db_siteTest_variant $variant
	 * @param string $sessionID
	 * @param ecomm_purchInfo $purchInfo Purchase information associated with this conversion, if any
	 * @param float $value Value associated with this conversion, defaults to order total (or null)
	 * @return db_siteTest_conversion
	 */
	public static function createInstance(db_siteTest_variant $variant, $sessionID, ecomm_purchInfo $purchInfo = null, $value = null) {
		if (!$variant->id) {
			trigger_error(__METHOD__ . ': variant must be saved first', E_USER_ERROR);
			return null;
		}

		$obj = new self();
		$obj->sessionID = $sessionID;
		$obj->testFK = $variant->testFK;
		$obj->orderFK = ($purchInfo ? $purchInfo->getOrderID() : null);
		$obj->value = $value ?: ($purchInfo ? $purchInfo->getTotal() : null);
		$obj->variantFK = $variant->id;
		return $obj;
	}

	/**
	 * Fetch an object instance based on an ID
	 *
	 * @param string $id
	 * @return db_siteTest_conversion
	 */
	public static function getInstance($id = null) {
		return new self($id);
	}

}
