<?php
/**
 * acl.users table model
 *
 * @package framework
 * @subpackage db_acl
 *
 */
class db_acl_users extends core_row {
	const DB = 'acl';
	const TABLE = 'users';
	const PK = 'id';

	/**
	 * Fetch an object instance based on an ID
	 *
	 * @param integer $id
	 * @return db_acl_users
	 */
	public static function getInstance($id = NULL) {
		return new self($id);
	}

	/**
	 * Fetch an object instance based on a contextID and a user name
	 *
	 * @param integer $contextID The contextID
	 * @param string $username The user name
	 * @return db_acl_users
	 */
	public static function getInstanceByContext($contextID, $username) {
		$columns = array('contextFK' => $contextID, 'username' => $username);

		$obj = new self();
		$obj->loadArr($columns);

		return $obj;
	}
}