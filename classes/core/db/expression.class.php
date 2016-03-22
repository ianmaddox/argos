<?php

/**
 * An object for building database expressions such as function calls.
 * Expression objects are useful in core_row classes, core_db_expression calls, and raw SQL generation.
 *
 * @package framework
 * @subpackage db
 */
class core_db_expression {
	/**
	 * @var string $sqlCmd The raw SQL command
	 */
	public $sqlCmd;

	/**
	 * Date constants
	 */
	const CMD_NOW = 'NOW()';
	const INC_YEAR = 'YEAR';
	const INC_MONTH = 'MONTH';
	const INC_DAY = 'DAY';

	/**
	 * Allows you to send in a RAW SQL command/expression
	 *
	 * @param string $value
	 * @return core_db_expression
	 */
	public static function createRaw($value)
	{
		$obj = new self();
		$obj->sqlCmd = $value;

		return $obj;
	}

	/**
	 * Allows the creation of a date in the future
	 *
	 * @param string $startDate Start date
	 * @param int $incAmount The amount to increment
	 * @param string $incType The type of increment
	 * @return core_db_expression
	 */
	public static function createDate($startDate, $incAmount, $incType = self::INC_YEAR)
	{
		// Todo: Check whether $startDate is a DateTime and convert accordingly.
		$obj = new self();
		$obj->sqlCmd = 'DATE_ADD('.$startDate.', INTERVAL '.$incAmount.' '.$incType.')';
		return $obj;
	}

	/**
	 * Allow direct writing of PHP DateTime objects to the DB.  Converts all values to
	 * UTC before save.
	 * @param DateTime $date
	 * @return self
	 */
	public static function dateTime(DateTime $date) {
		$obj = new self();
		// All dates in the DB are UTC.
		$date->setTimezone(new DateTimeZone('UTC'));
		$obj->sqlCmd = '"' . $date->format("Y-m-d H:i:s") . '"';
		return $obj;
	}

	/**
	 * Helper which creates a DateTime from a Unix timestamp
	 * @param int $timestamp
	 * @return self
	 */
	public static function dateTimeFromUnix($timestamp) {
		return self::dateTime(new DateTime('@'.$timestamp));
	}
	
	/**
	 * Shorthand for the current time.
	 */
	public static function now() {
		return new DateTime('now', new DateTimeZone('UTC'));
	}

	/**
	 * Return the suilt SQL command/expression as a string
	 *
	 * @return string SQL command/expression
	 */
	public function __toString()
	{
		return (string)$this->sqlCmd;
	}
}
