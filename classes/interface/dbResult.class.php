<?php
/**
 *
 * @package framework
 * @subpackage interface
 */
interface interface_dbResult {
	/* Connection management */
	public function __construct($rs, $success);

	/* Direct query and select methods */

	/**
	 * Return the next row of a resultset.
	 */
	public function getRow();

	/**
	 * Return the first column of the first row of a resultset.
	 */
	public function getVal();

	/**
	 * Return the first column of the first row of a resultset.
	 */
	public function getCount();

	/**
	 * Returns whether the resultset was successful
	 */
	public function getSuccess();
}
