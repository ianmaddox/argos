<?php
/**
 * Timer class is a development tool used to count the time a given process takes to execute.
 * It should not be used in production.
 */
class util_timer {
	var $startTime;
	var $endTime;
	var $isRunning = false;

	/**
	 * Constants for time units
	 */
	const SEC = 'second';
	const MIN = 'min';
	const HOUR = 'hour';
	const DAY = 'day';

	/**
	 * Start the timer
	 */
	function __construct() {
		$this->startTime = 0;
		$this->endTime = 0;
	}

	/**
	 * Get a timestamp
	 *
	 * @return int
	 */
	function getTimestamp() {
		return microtime(true);
	}

	/**
	 * Start the counter
	 */
	function startCounter() {
		$this->startTime = $this->getTimestamp();
		$this->isRunning = true;
	}

	/**
	 * Stop the counter
	 */
	function stopCounter() {
		$this->endTime = $this->getTimestamp();
		$this->isRunning = false;
	}

	/**
	 * Return the elapsed time
	 *
	 * @param string $type The type of unit of time
	 * @return string
	 */
	function getElapsedTime($type = null, $precision = 2) {
		$total = array();
		$split = $this->isRunning ? $this->getTimestamp() : $this->endTime;
		$elapsedSec = $split - $this->startTime;
		$total[self::DAY] = $elapsedSec / SEC_DAY;
		$total[self::HOUR] = $elapsedSec / SEC_HOUR;
		$total[self::MIN] = $elapsedSec / SEC_MINUTE;
		$total[self::SEC] = $elapsedSec % SEC_MINUTE + ($elapsedSec - floor($elapsedSec));
		if($type == 'second') {
			return $elapsedSec;
		} elseif($type) {
			return number_format($total[$type], $precision) . ' (' . $total[$type] . ')';
		} else {
			$comma = '';
			$ret = '';
			foreach($total as $type => $value) {
				$precision = $type == self::SEC ? 7 : 0;
				$value = number_format($value, $precision);
				$plural = round($value) == 1 ? '' : 's';
				$ret .= "{$comma}{$value} {$type}{$plural}";
				$comma = ", ";
			}

			return $ret;
		}
	}
}
