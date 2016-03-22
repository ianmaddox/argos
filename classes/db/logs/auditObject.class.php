<?php

/**
 * @property-read int $id
 * @property-read string $date_added
 * @property-read string $date_modified
 * @property int $userFK
 * @property string $source
 * @property string $event
 * @property string $message
 * @property string $ip
 * @property string $requestURI
 * @property string $referrerURI
 */
class db_logs_auditObject extends core_row {

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
	 * Get all by audit IDs
	 *
	 * @param int $auditID
	 * @return db_user_action
	 */
	public static function getAllByAuditID($auditID) {
		$obj = new self();
		$obj->load($auditID, 'auditFK', 0);
		return $obj;
	}
}
