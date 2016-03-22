<?php

/**
 * Mysqli resultset container.
 * See interface_dbResult for more info
 *
 * @package framework
 * @subpackage db
 */
class core_db_mysqliStatement implements interface_dbStatement {
	/**
	 * @var mysqli_stmt $stmt
	 */
	private $stmt;

	/**
	 * Constructor
	 *
	 * @param string $stmt
	 */
	public function __construct($stmt) {
		$this->stmt = $stmt;
	}

	/**
	 * Execute the prepared statement
	 *
	 * @param string $errorMsg
	 * @return mixed
	 */
	public function execute($errorMsg = '') {
		// If we have data to bind, collect it and attach it to the statment.
		if(!empty($this->bindArr)) {
			$args = array();
			$args[] = $this->bindKey;

			// The arguments to the bind function must be passed by reference.
			foreach($this->bindArr as $key => $val) {
				$args[] = &$this->bindArr[$key];
			}

			call_user_func_array(array($this->stmt, "bind_param"), $args);
			if(!empty($this->stmt->error)) {
				trigger_error($this->stmt->error . '|' . $errorMsg, E_USER_WARNING);
			}
			$this->bindKey = '';
			$this->bindArr = array();
		}

		return $this->stmt->execute();
	}

	/**
	 * Bind an integer to a prepared statement
	 *
	 * @param int $integer
	 */
	public function bindInt($integer) {
		$this->bindKey .= 'i';
		$this->bindArr[] = $integer;
	}

	/**
	 * Bind a floating point variable to a prepared statement
	 *
	 * @param float $double
	 */
	public function bindFloat($double) {
		$this->bindKey .= 'd';
		$this->bindArr[] = $double;
	}

	/**
	 * Bind a string to a prepared statement
	 *
	 * @param string $string
	 */
	public function bindStr($string) {
		$this->bindKey .= 's';
		$this->bindArr[] = $string;
	}

	/**
	 * Bind a blob to a prepared statement
	 *
	 * @param string $blob
	 */
	public function bindBlob($blob) {
		$this->bindKey .= 'b';
		$this->bindArr[] = $blob;
	}

	/**
	 * Get the last insert ID
	 *
	 * @return int
	 */
	public function getInsertID() {
		return $this->stmt->insert_id;
	}
}
