<?php
/**
 * acl.context table model
 *
 * @package framework
 * @subpackage db_acl
 *
 */
class db_acl_context extends core_row {
	const DB = 'acl';
	const TABLE = 'context';
	const PK = 'id';

	/**
	 * Fetch an object instance based on an ID
	 *
	 * @param integer $id
	 * @return db_acl_context
	 */
	public static function getInstance($id = NULL) {
		return new self($id);
	}

	/**
	 * Fetch an object instance based on a context name
	 *
	 * @param string $name The context name
	 * @return db_acl_context
	 */
	public static function getInstanceByName($name) {
		$obj = new self();
		$obj->load($name, 'name');

		return $obj;
	}
}