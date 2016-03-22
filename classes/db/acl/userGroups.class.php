<?php
/**
 * acl.userGroups table model
 *
 * @package framework
 * @subpackage db_acl
 *
 */
class db_acl_userGroups extends core_row {
	const DB = 'acl';
	const TABLE = 'userGroups';
	const PK = 'id';

	/**
	 * Fetch an object instance based on an ID
	 *
	 * @param integer $id
	 * @return db_acl_userGroups
	 */
	public static function getInstance($id = NULL) {
		return new self($id);
	}

	/**
	 * Fetch an object instance based on a userID and groupID
	 *
	 * @param integer $userID The userID
	 * @param integer $groupID The groupID
	 * @return db_acl_userGroups
	 */
	public static function getInstanceByUserGroup($userID, $groupID) {
		$columns = array('userFK' => $userID, 'groupFK' => $groupID);

		$obj = new self();
		$obj->loadArr($columns);

		return $obj;
	}
}