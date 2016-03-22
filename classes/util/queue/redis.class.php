<?php

/**
 * This class implements a redis queue client and worker interface.
 *
 * @package Framework
 */
class util_queue_redis implements interface_queue {

	private $redis;
	private $queue;

	/**
	 * Constructor
	 *
	 * @param string $queue
	 * @throws Exception
	 */
	public function __construct($queue)
	{
		if(!isset($queue) || empty($queue))
		{
			trigger_error('$queue not specified or empty', E_USER_ERROR);
		}
		$this->queue = $queue;
		$this->redis = new Redis();
		$this->redis->connect(cfg::get('redis_server'), cfg::get('redis_port'), 2.5);
	}

	/**
	 * Pushes an serialized var (string) onto the queue (a list) and returns
	 * the length of the queue.
	 *
	 * @param mixed $data the data to be serialized and queued.
	 * @param boolean $async not used in this context.
	 * @return int the length of the queue.
	 */
	public function send($data, $async = true)
	{
		if($data === false)
		{
			trigger_error("Cannot queue empty message", E_USER_WARNING);
			return false;
		}
		try {
			$data = serialize($data);
			return $this->redis->rPush($this->queue, $data);
		} catch(Exception $e) {
			trigger_error("Lost connection to redis server", E_USER_WARNING);
		}
		return false;
	}

	/**
	 * Retrieves the first element off
	 * @return string a serialized variable previously stored on the queue
	 */
	public function fetch()
	{
		try {
			$message = $this->redis->ping();
			return unserialize($this->redis->lPop($this->queue));
		} catch(Exception $e) {
			trigger_error("Connection to redis $queue lost", E_USER_WARNING);
		}
		return false;
	}

	/**
	 * Return a count of the messages in the queue
	 *
	 * @return int
	 */
	public function count() {
		return $this->redis->lLen($this->queue);
	}

	/**
	 * Delete the queue
	 *
	 * @param bool $ifempty Only delete if the queue is empty
	 */
	public function delete($ifempty = true) {
		if(!$ifempty || $this->count() == 0) {
			$this->redis->delete($this->queue);
		}
	}

}

