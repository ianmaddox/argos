<?php

/**
 * The db comparison class is used by core_row and others to override default equals expressions.
 * As a value, it may accept strings or arrays (depending on which comparison is used), or core_db_expression objects.
 *
 *
 *
 * @package framework
 * @subpackage db
 */
class core_db_comparison {
	/**
	 * @var string $sqlCmd The raw SQL chunk
	 */
	public $sqlCmd;

	/**
	 * Date constants
	 */
	const COMP_GT = 'GT';
	const COMP_GTE = 'GTE';

	const COMP_LT = 'LT';
	const COMP_LTE = 'LTE';

	const COMP_IS_NULL = 'ISNULL';
	const COMP_IS_NOT_NULL = 'ISNOTNULL';

	const COMP_NOT_EQUAL = 'NOTEQUAL';
	const COMP_EQUAL = 'EQUAL';

	const COMP_LIKE = 'LIKE';
	const COMP_NOT_LIKE = 'NOTLIKE';

	const COMP_IN = 'IN';
	const COMP_NOT_IN = 'NOTIN';

	const COMP_BOOL_TRUE = 'BOOLTRUE';
	const COMP_BOOL_FALSE = 'BOOLFALSE';

	private static $operations = array(
		self::COMP_GT => '> {val}',
		self::COMP_GTE => '>= {val}',
		self::COMP_LT => '< {val}',
		self::COMP_LTE => '<= {val}',
		self::COMP_IS_NULL => 'IS NULL',
		self::COMP_IS_NOT_NULL => 'IS NOT NULL',
		self::COMP_NOT_EQUAL => '!= {val}',
		self::COMP_EQUAL => '= {val}',
		self::COMP_LIKE => 'LIKE {val}',
		self::COMP_NOT_LIKE => 'NOT LIKE {val}',
		self::COMP_IN => 'IN ({val})',
		self::COMP_NOT_IN => 'NOT IN ({val})',
		self::COMP_BOOL_TRUE => 'IS TRUE',
		self::COMP_BOOL_FALSE => 'IS FALSE'
	);

	/**
	 * Build the comparison operator
	 *
	 * @param const $comparison Any of the constants in core_db_comparison::COMP_*
	 * @param mixed $value can be an array, string, or omitted based on the comparison type
	 * @return core_db_comparison
	 */
	public static function build($comparison, $value = '') {
		if(!isset(self::$operations[$comparison])) {
			trigger_error("Invalid comparison indicated: '$comparison'.  Defaulting to equals.", E_USER_WARNING);
			$comparison = self::COMP_EQUAL;
		}

		if($comparison == self::COMP_IN || $comparison == self::COMP_NOT_IN) {
			if(!is_array($value)) {
				trigger_error("IN() or NOT IN() comparison called with non-array value.  Converting to array.", E_USER_WARNING);
				$value = array($value);
			}
			$newValArr = array();
			foreach($value as $val) {
				$newValArr[] = self::prepValue($val);
			}
			$value = implode(',', $newValArr);
		} elseif($comparison == self::COMP_BOOL_FALSE || $comparison == self::COMP_BOOL_TRUE || $comparison == self::COMP_IS_NULL || $comparison == self::COMP_IS_NOT_NULL) {
			// Nothing to do here.
		} else {
			if(is_array($value)) {
				trigger_error("This comparison operator requres string input, array given", E_USER_WARNING);
			}
			$value = self::prepValue($value);
		}

		$obj = new self();
		$obj->sqlCmd = str_replace('{val}', $value, self::$operations[$comparison]);
		return $obj;
	}

	/**
	 * Prepare a value for inclusion in a SQL chunk
	 *
	 * @param core_db_expression $val
	 * @return string
	 */
	private static function prepValue($val) {
		$db = core_db::getDB();
		if($val instanceof core_db_expression) {
			return (string)$val;

		} elseif(is_null($val)) {
			return 'NULL';

		}

		return '\'' . $db->escapeVal($val) . '\'';
	}

	/**
	 * Return the rendered SQL chunk
	 *
	 * @return string
	 */
	public function getSql() {
		return $this->sqlCmd;
	}

	/**
	 * Return the built SQL command/expression as a string
	 *
	 * @return string SQL command/expression
	 */
	public function __toString()
	{
		return $this->getSql();
	}

	/* ----------------------------------------------------------------------------------------------------------------
	 * Candy wrappers
	 * These are simply helpful access methods for build()
	 * ----------------------------------------------------------------------------------------------------------------
	 */

	/**
	 * Generate an IS FALSE comparison.  No params needed.
	 * @return core_db_comparison
	 */
	public static function isFalse() {
		return self::build(self::COMP_BOOL_FALSE);
	}

	/**
	 * Generate an IS TRUE comparison.  No params needed.
	 * @return core_db_comparison
	 */
	public static function isTrue() {
		return self::build(self::COMP_BOOL_TRUE);
	}

	/**
	 * Generate an IS NULL comparison.  No params needed.
	 * @return core_db_comparison
	 */
	public static function isNull() {
		return self::build(self::COMP_IS_NULL);
	}

	/**
	 * Generate an IS NOT NULL comparison.  No params needed.
	 * @return core_db_comparison
	 */
	public static function isNotNull() {
		return self::build(self::COMP_IS_NOT_NULL);
	}

	/**
	 * Generate an '= {val}' comparison.
	 * @param string value
	 * @return core_db_comparison
	 */
	public static function equals($val) {
		return self::build(self::COMP_EQUAL, $val);
	}

	/**
	 * Generate a '!= {val}' comparison.
	 * @param string value
	 * @return core_db_comparison
	 */
	public static function notEqual($val) {
		return self::build(self::COMP_NOT_EQUAL, $val);
	}

	/**
	 * Generate a 'LIKE {val}' comparison.  BYO wildcards.
	 * @param string value
	 * @return core_db_comparison
	 */
	public static function like($val) {
		return self::build(self::COMP_LIKE, $val);
	}

	/**
	 * Generate a 'NOT LIKE {val}' comparison.  BYO wildcards.
	 * @param string value
	 * @return core_db_comparison
	 */
	public static function notLike($val) {
		return self::build(self::COMP_NOT_LIKE, $val);
	}

	/**
	 * Generate an '> {val}' comparison.
	 * @param string value
	 * @return core_db_comparison
	 */
	public static function gt($val) {
		return self::build(self::COMP_GT, $val);
	}

	/**
	 * Generate an '>= {val}' comparison.
	 * @param string value
	 * @return core_db_comparison
	 */
	public static function gte($val) {
		return self::build(self::COMP_GTE, $val);
	}

	/**
	 * Generate an '< {val}' comparison.
	 * @param string value
	 * @return core_db_comparison
	 */
	public static function lt($val) {
		return self::build(self::COMP_LT, $val);
	}

	/**
	 * Generate an '<= {val}' comparison.
	 * @param string value
	 * @return core_db_comparison
	 */
	public static function lte($val) {
		return self::build(self::COMP_LTE, $val);
	}

	/**
	 * Generate an 'IN ({val1}, {val2}, ...)' comparison.
	 * @param array value
	 * @return core_db_comparison
	 */
	public static function in($val) {
		return self::build(self::COMP_IN, $val);
	}

	/**
	 * Generate an 'NOT IN ({val1}, {val2}, ...)' comparison.
	 * @param array value
	 * @return core_db_comparison
	 */
	public static function notIn($val) {
		return self::build(self::COMP_NOT_IN, $val);
	}

}
