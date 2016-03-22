<?php
/**
 * acl.groupActions table model
 *
 * @package framework
 * @subpackage db_acl
 *
 */
class db_acl_groupActions extends core_row {
	const DB = 'acl';
	const TABLE = 'groupActions';
	const PK = 'id';

	/**
	 * Fetch an object instance based on an ID
	 *
	 * @param integer $id
	 * @return db_acl_groupActions
	 */
	public static function getInstance($id = NULL) {
		return new self($id);
	}

	/**
	 * Fetch an object instance based on a groupID and $actionID
	 *
	 * @param integer $groupID The userID
	 * @param integer $actionID The groupID
	 * @return db_acl_groupActions
	 */
	public static function getInstanceByGroupAction($groupID, $actionID) {
		$columns = array('groupFK' => $groupID, 'actionFK' => $actionID);

		$obj = new self();
		$obj->loadArr($columns);

		return $obj;
	}
}