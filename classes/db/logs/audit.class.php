<?php

/**
 * @property-read int $id
 * @property-read string $date_added
 * @property-read string $date_modified
 * @property int $auditFK
 * @property string $databaseName
 * @property string $tableName
 * @property string $keyField
 * @property string $keyValue
 */
class db_logs_audit extends core_row {

	/**
	 * Get an instance
	 *
	 * @param int $id
	 * @return db_user_action
	 */
	public static function getInstance($id) {
		return new self($id);
	}

	/**
	 * Get all by userIDs
	 *
	 * @param int $userID
	 * @return db_user_action
	 */
	public static function getAllByUserID($userID) {
		$obj = new self();
		$obj->load($userID, 'userFK', 0);
		return $obj;
	}
}
