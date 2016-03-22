<?php

/**
 * Mysqli resultset container.
 * See interface_dbResult for more info
 *
 * @package framework
 * @subpackagee db
 */
class core_db_mysqliResult implements interface_dbResult {

	/** @var mysqli_result $rs */
	private $rs;
	private $success;

	/**
	 * Constructor
	 *
	 * @param mysqli_result $rs
	 */
	public function __construct($rs, $success) {
		$this->rs = $rs;
		$this->success = $success;
	}

	/**
	 * Get the row
	 *
	 * @return array
	 */
	public function getRow() {
		if(empty($this->rs)) {
			trigger_error("Trying to use a mysqli result set that had failed", E_USER_WARNING);
			return array();
		} else {
			return $this->rs->fetch_assoc();
		}
	}

	/**
	 * Get a value
	 *
	 * @return mixed
	 */
	public function getVal() {
		if(empty($this->rs)) {
			trigger_error("Trying to use a mysqli result set that had failed", E_USER_WARNING);
			return FALSE;
		} else {
			$row = $this->rs->fetch_row();
			return $row[0];
		}
	}

	/**
	 * Get the count
	 *
	 * @return int
	 */
	public function getCount() {
		if(empty($this->rs)) {
			trigger_error("Trying to use a mysqli result set that had failed", E_USER_WARNING);
			return 0;
		} else {
			return isset($this->rs) ? $this->rs->num_rows : 0;
		}
	}

	/**
	 * Returns whether the resultset was successful
	 * @return bool success
	 */
	public function getSuccess() {
		return !empty($this->success);
	}

}
