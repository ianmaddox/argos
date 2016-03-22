<?php
/**
 * acl.groups table model
 *
 * @package framework
 * @subpackage db_acl
 *
 */
class db_acl_groups extends core_row {
	const DB = 'acl';
	const TABLE = 'groups';
	const PK = 'id';

	/**
	 * Fetch an object instance based on an ID
	 *
	 * @param integer $id
	 * @return db_acl_groups
	 */
	public static function getInstance($id = NULL) {
		return new self($id);
	}

	/**
	 * Fetch an object instance based on a contextID and a group name
	 *
	 * @param integer $contextID The contextID
	 * @param string $name The group name
	 * @return db_acl_groups
	 */
	public static function getInstanceByContextName($contextID, $name) {
		$columns = array('contextFK' => $contextID, 'name' => $name);

		$obj = new self();
		$obj->loadArr($columns);

		return $obj;
	}
}