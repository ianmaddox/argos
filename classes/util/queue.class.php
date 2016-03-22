<?php

/**
 * A very simple queue implementation.  An instance of this class can act as both client (message creator)
 * and worker (message consumer) for a given queue.
 *
 * @package framework
 * @subpackage util
 */
class util_queue implements interface_queue {

	private $engine = false;

	const ENGINE_GEARMAN = 'util_queue_gearman';
	const ENGINE_RABBIT = 'util_queue_rabbit';
	const ENGINE_REDIS = 'util_queue_redis';
	const ENGINE_SQS = 'util_queue_sqs';
	const MODE_ASYNC = 1;
	const MODE_SYNC = 0;

	/**
	 * $args is either a string containing the engine constant or a string
	 * specifying the name of the queue
	 * @param string $engineName corresponds to one of the framework queue classes
	 * @param string $queue the identifier you want the queue to have
	 */
	public function __construct($queue, $engineName = self::ENGINE_REDIS)
	{
		if(!class_exists($engineName))
		{
			throw new Exception('Invalid queue engine class: ' . $engineName);
		}

		$this->engine = new $engineName($queue);
	}

	/**
	 * Function to send off an object/whatnot to the queue engine for processing
	 *
	 * @param mixed $data Data to queue up
	 * @param bool $async Flag for async mode
	 * @return boolean $result True on successful send, false on error
	 */
	public function send($data, $async = self::MODE_ASYNC)
	{
		$result = $this->engine->send($data, $async);

		return $result;
	}

	/**
	 * Function for fetching the queued job
	 *
	 * @return mixed $result The data returned from the queue, false on error.
	 */
	public function fetch()
	{
		$result = $this->engine->fetch();

		return $result;
	}

	/**
	 * Return a count of the messages in the queue
	 *
	 * @return int
	 */
	public function count() {
		return $this->engine->count();
	}

	/**
	 * Delete the queue
	 *
	 * @param bool $ifempty Only delete if the queue is empty
	 */
	public function delete($ifempty = true) {
		return $this->engine->delete($ifempty);
	}

}
