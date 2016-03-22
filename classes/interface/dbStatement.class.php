<?php
/**
 *
 * @package framework
 * @subpackage interface
 */
interface interface_dbStatement {
	/* Connection management */
	public function __construct($rs);

	/* Direct query and select methods */

	/**
	 * Execute a prepared statement.  Wipe any bound variables and make the
	 * statement ready to accept a new set of input.
	 *
	 * @param string $errorMsg
	 * @return bool success
	 */
	public function execute($errorMsg = '');

	/**
	 * Bind a string to a statement
	 *
	 * @param string $input
	 */
	public function bindStr($input);

	/**
	 * Bind a number to a statement
	 *
	 * @param int $input
	 */
	public function bindInt($input);

	/**
	 * Bind a float to a statement
	 *
	 * @param float $input
	 */
	public function bindFloat($input);

	/**
	 * Bind a large input to a statement (binary safe)
	 *
	 * @param string $input
	 */
	public function bindBlob($input);

	/**
	 * Get the last insertID
	 */
	public function getInsertID();
}
