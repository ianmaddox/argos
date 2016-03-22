<?php

/**
 * This class implements a gearman queue client and worker interface.
 * Gearman does not natively support queues.  However, there are two workarounds:
 * 1) Pass the name of a class that implements interface_gearmanCallback as the queue.  The method setJob() will
 *    get called each time there is incoming data.
 * 2) Use multiple gearmand instances
 *
 * The storage mechanism of choice for dev is memory and for production, use mysql with MyISAM.
 * InnoDB proved to be about 1/100th the speed of MyISAM and SQLite was even slower.
 *
 *
 * @package framework
 * @subpackage util_queue
 */
class util_queue_gearman implements interface_queue, interface_gearmanCallback {

	private $client;
	private $worker;
	// The action is an alias for the callback
	// Both client and worker need to know the same action.  Worker registers a callback for a given
	// action name.
	private $callback;
	private $queue;
	// A storage mechanism for the queue callback
	public static $job;

	// The number of ms each work request will wait before timing out.
	// This is set very low because the daemon class should be in charge of throttling requests

	const WORKER_TIMEOUT = 10;

	// The clients should not experience wait timeouts unless working in sync mode and the remote
	// worker isn't answering.
	const CLIENT_TIMEOUT = 3000;

	/**
	 * The queue name must be that of a class that implements interface_gearmanCallback
	 *
	 * @param array $queue
	 */
	public function __construct($queue = 'util_queue_gearman')
	{
		trigger_error("Gearman is not currently supported in the framework", E_USER_ERROR);
		$implements = class_implements($queue);
		if(!isset($implements['interface_gearmanCallback']))
		{
			trigger_error("Queue callback class $queue does not implement interface_gearmanCallback", E_USER_ERROR);
		}

		// We don't have any means of handling the queue at this time.  Just store it for future use.
		$this->queue = $queue;

		// Define the callback as the setJob method of the $queue class
		$this->callback = array($queue, 'setJob');
	}

	/**
	 * Sync mode waits on a remote worker to grab the job.
	 * This shouldn't usually be required, but sometimes you want to be absolutely sure someone is on the
	 * case.
	 *
	 * @param mixed $data
	 * @param bool $async
	 * @return bool success
	 */
	public function send($data, $async = true)
	{
		if($data === false)
		{
			trigger_error("Cannot queue empty message", E_USER_WARNING);
			return false;
		}
		if(!isset($this->client))
		{
			$this->client = new GearmanClient();
			$this->client->addServer(cfg::get('gearman_server'));
			$this->client->setTimeout(3000);
		}

		// Context variable can be populated as follows:
		//$this->client->setContext($this->queue);

		$data = serialize($data);
		if($async == false)
		{
			$this->client->do($this->queue, $data);
		}
		else
		{
			$this->client->doBackground($this->queue, $data);
		}
		return $this->client->returnCode() == GEARMAN_SUCCESS;
	}

	/**
	 * Fetch something from the queue
	 *
	 * @param mixed $queue
	 * @return mixed data.  False indicates no data found.  All other responses are valid.
	 */
	public function fetch()
	{
		if(!isset($this->worker))
		{
			$this->worker = new GearmanWorker();
			$this->worker->addServer(cfg::get('gearman_server'));
			$this->worker->addFunction($this->queue, $this->callback);
			$this->worker->setTimeout(self::WORKER_TIMEOUT);
		}

		// The GearmanWorker has an unsavory habit of throwing a warning every time
		// the timeout is reached.  Suppress these warnings so we don't have to deal with
		// hundreds each second
		@$this->worker->work();

		// If this class is the queue, use the implementation of setJob in this class to capture the job data.
		if($this->queue == __CLASS__ && !empty(self::$job))
		{
			$data = self::$job;
			self::$job = false;
			return unserialize($data);
		}
		return false;
	}

	/**
	 * An internal utility function used by the callback spawned by GearmanWorker::work()
	 * Captures the output and sticks it in the local class.
	 *
	 * @param GearmanJob $job
	 */
	public static function setJob(GearmanJob $job)
	{
		self::$job = $job->workload();
	}

	/**
	 * Return a count of the messages in the queue
	 *
	 * @return int
	 */
	public function count() {
		trigger_error('Not implemented', E_USER_ERROR);
	}

	/**
	 * Delete the queue
	 *
	 * @param bool $ifempty Only delete if the queue is empty
	 */
	public function delete($ifempty = true) {
		trigger_error('Not implemented', E_USER_ERROR);
	}

}

