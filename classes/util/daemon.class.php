<?php

/**
 * @author ianmaddox
 * @copyright 2010 Ian Maddox.
 *    FreeBSD License.  In short: reuse, modification, and commercial use are OK provided attribution remains intact.
 * @license http://www.freebsd.org/copyright/freebsd-license.html
 * @version 1.2
 *
 * This script is a PHP daemon pattern controller.  It provides the ability to run
 * multiple concurrent instances for a daemon.  It uses a PID file stored in
 * the /tmp directory by default.
 *
 * When conflicts are found, the results are returned in a non-fatal form.
 * It is up to the daemon to read the responses to start() and heartbeat() and
 * act accordingly.
 *
 * If a TRUE response is returned after calling start(), the daemon *must* call
 * stop() before execution ends.
 *
 * This code also captures SIGTERM, SIGHUP, and SIGINT.  The next "heartbeat"
 * after one of these signals is received will tell the daemon to halt.
 *
 * @package framework
 * @subpackage util
 */
class util_daemon {

	private $fp;
	private $data;
	private $pname;
	private $maxPeers;
	private $timeoutSec;
	private $pidDir;
	private $lastLoopTime;
	private $killFlag;
	private $started;

	/**
	 * Constructor method.  Takes in the vital data related to the daemon.
	 *
	 * @param string $processName The name for this daemon type
	 * @param int $timeoutSeconds How long after a heartbeat to consider a daemon dead
	 * @param int $maxPeers Max number of $processName daemons allowed to run at once
	 * @param int $pidDir The directory to store the PID files
	 */
	public function __construct($processName, $timeoutSeconds, $maxPeers = 1, $pidDir = '/tmp') {
		$this->pname = $processName;
		$this->timeoutSec = (int) $timeoutSeconds;
		$this->maxPeers = (int) $maxPeers;

		$pidDir .= substr($pidDir, -1) == DIRECTORY_SEPARATOR ? '' : '/';
		$this->pidDir = $pidDir;
		$this->killFlag = false;
		$this->started = false;
	}

	/**
	 * Attempt to obtain clearance to run daemon
	 * Starting the daemon registers a listener for SIGTERM, SIGHUP, and SIGINT events.
	 *
	 * @return boolean $clearToRun FALSE indicates PID not registered.  Daemon should quit.
	 */
	public function start() {
		declare(ticks = 1);
		if(function_exists('pcntl_signal')) {
			pcntl_signal(SIGTERM, array(__CLASS__, "signalHandler"));
			pcntl_signal(SIGHUP, array(__CLASS__, "signalHandler"));
			pcntl_signal(SIGINT, array(__CLASS__, "signalHandler"));
		} else {
			trigger_error("NOTICE: This PHP install does not include the PCNTL library.\nPlease include it for advanced process management:\nhttp://us3.php.net/manual/en/book.pcntl.php", E_USER_NOTICE);
		}
		$this->started = true;
		$clearToRun = $this->heartbeat(false);

		return $clearToRun;
	}

	/**
	 * Update the PID file, indicating continued daemon activity
	 *
	 * @param float $minLoopSec Indicates the minimum amount of seconds between heartbeats.
	 *         Will throttle execution to maintain minimum. Expects decimal values of 1 second.
	 * @return boolean FALSE indicates daemon should call stop() and terminate
	 */
	public function heartbeat($minLoopSec = false) {
		// Check for a kill flag or if the daemon hasn't been "started" yet.
		if($this->killFlag == true || $this->started == false) {
			return false;
		}
		// If a min loop time is provided, make sure this call is more than the minimum
		// amount of time since the last one.  If not, usleep until it is.
		if($minLoopSec > 0) {
			if($this->lastLoopTime > 0 && microtime(true) < ($this->lastLoopTime + $minLoopSec)) {
				$sleep_usec = ($minLoopSec - (microtime(true) - $this->lastLoopTime)) * 1000000;
				usleep($sleep_usec);
			}
			$this->lastLoopTime = microtime(true);
		}
		if(!$this->openLockPidFile()) {
			return false;
		}
		$this->readPidFile();
		if(count($this->data) > $this->maxPeers) {
			$this->unlockPidFile();
			// Could not obtain a lock.  Daemon should clean up and quit.
			return false;
		}
		$this->data[getmypid()] = time() + $this->timeoutSec;
		$this->writeUnlockPidFile();

		return true;
	}

	/**
	 * Unregister this daemon process.
	 * Should always be called when a daemon is terminating.
	 */
	public function stop() {
		if($this->openLockPidFile()) {
			$this->readPidFile();
			unset($this->data[getmypid()]);
			$this->writeUnlockPidFile();
		}
	}

	/**
	 * Lock then read the daemon PID file and parse it into a usable format.
	 */
	private function readPidFile() {
		$fdata = '';
		rewind($this->fp);
		while($line = fgets($this->fp)) {
			$fdata .= $line;
		}
		$this->data = unserialize($fdata);
		if(!is_array($this->data)) {
			$this->data = array();
		}

		// Remove expired entries
		foreach($this->data as $pid => $expires) {
			if($expires < time()) {
				unset($this->data[$pid]);
			}
		}
	}

	/**
	 * Remove the temporary lock on the daemon PID file.
	 */
	private function unlockPidFile() {
		flock($this->fp, LOCK_UN);
		unset($this->fp);
	}

	/**
	 * Write to the PID file then unlock it.
	 */
	private function writeUnlockPidFile() {
		$fdata = serialize($this->data);
		rewind($this->fp);
		ftruncate($this->fp, 0);
		fwrite($this->fp, $fdata);
		$this->unlockPidFile();
	}

	/**
	 * Open the PID file, lock it, and store the resource handle.
	 *
	 * @return boolean FALSE indicating could not obtain a lock on the file within 1 second.
	 */
	private function openLockPidFile() {
		$i = 0;
		$fname = '';

		if(!ctype_alnum($this->pname{0})) {
			throw new Exception("Invalid process name.  First character must be alpha.");
		}

		// Replace all non alphanumeric chars with underscores.
		while($i < strlen($this->pname)) {
			$fname .= ctype_alnum($this->pname{$i}) ? $this->pname{$i} : '_';
			$i++;
		}
		$fname = $this->pidDir . 'daemon.' . $fname . '.pid';
		$mode = file_exists($fname) ? 'rb+' : 'wb+';
		$this->fp = fopen($fname, $mode);
// TODO: Check for FP open failure
		$i = 0;
		// Attempt to unlock up to 10 times
		while(!flock($this->fp, LOCK_EX + LOCK_NB)) {
			if($i++ > 10) {
				return false;
			}
			// Sleep for 1/10th of a second
			usleep(100000);
		}
		return true;
	}

	/**
	 * Capture linux signal events.  When captured, a flag is set that causes the next
	 * heartbeat to return false, which indicates to the daemon that it must shut down.
	 *
	 * @param int $signo
	 */
	public function signalHandler($signo) {
		echo "Caught signal #$signo.\n";
		@$this->killFlag = true;
	}

}
