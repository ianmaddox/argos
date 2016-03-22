<?php

/**
 * A fully-functional cache driver that uses S3 as a storage engine.
 * The cache key should be given in the following form:
 * s3://bucketname/pathandfilename
 *
 * S3 is rock-solid reliable but very slow for read/write
 *
 * @package framework
 * @subpackage cache
 */
class core_cache_s3 implements interface_cache {

    /** @var AmazonS3 $s3 */
    private $s3;
    private $bucket;
    private $key;
    private $public;

    /**
     * Constructor
     */
    public function __construct() {
	$this->s3 = new AmazonS3();
	$this->public = false;
    }

    /**
     * @param string $key
     * @return null
     */
    public function get($key) {
	if (!$this->makeBucketAndKey($key)) {
	    return null;
	}

	$data = $this->s3->get_object($this->bucket, $this->key);
	if (empty($data) || !$data->isOK()) {
	    return null;
	}
	$out = unserialize($data->body);
	if ($out === false && !empty($data->body)) {
	    $this->set($key, $data->body);
	    $out = $data->body;
	}
	return $out;
    }

    /**
     * Limitations: Does not support keys with spaces or non URL-safe data
     *
     * @param string $key
     * @param mixed $val
     * @param int $ttl
     * @return boolean
     */
    public function set($key, $val, $ttl = CACHE_DEFAULT_TTL) {
	if (!$this->makeBucketAndKey($key)) {
	    return false;
	}

	$ttlDays = ceil($ttl / SEC_DAY);
	$resp = $this->s3->create_object($this->bucket, $this->key, array('body' => serialize($val)));
	if (!$resp->isOK()) {
	    return false;
	}
	if ($this->public) {
	    $resp = $this->s3->set_object_acl($this->bucket, $this->key, AmazonS3::ACL_PUBLIC);
	}
	if (!$resp->isOK()) {
	    return false;
	}

	$opts = array(
	    'rules' => array(array(
		    'id' => $this->key,
		    'prefix' => $this->key,
		    'expiration' => array(
			'days' => $ttlDays
		    ))
	    )
	);
	$resp = $this->s3->create_object_expiration_config($this->bucket, $opts);
	if (!is_object($resp)) {
	    return false;
	}
	return $resp->isOK();
    }

    /**
     * @param string $key
     * @return boolean
     */
    public function delete($key) {
	if (!$this->makeBucketAndKey($key)) {
	    return false;
	}
	$list = $this->s3->list_objects($this->bucket, array('prefix' => $this->key));
	foreach ($list->body->Contents as $object) {
	    $key = $object->Key;
	    if (substr($key, -1) == '/') {
		// Don't try to delete folders.  They're a figment of your imagination anyway.
		continue;
	    }
	    $resp = $this->s3->delete_object($this->bucket, $key);
	}

	/** @var $resp CFResponse * */
	if (!is_object($resp)) {
	    return false;
	}
	return $resp->isOK();
    }

    /**
     * Perform some standard cleanup on cache keys.
     * There should be no slash at the beginning.
     *
     * @param string $key
     * @return string
     */
    private function makeBucketAndKey($key) {
	if (stripos($key, 's3://') !== 0) {
	    $bt = debug_backtrace();
	    // [0] is this being called, [1] is core_cache calling us, [2] is something calling core_cache
	    trigger_error("S3 cache keys must be prefixed with s3:// and the bucket name: '$key' (called from {$bt[2]['file']}:{$bt[2]['line']})", E_USER_WARNING);
	    return false;
	}
	list($this->bucket, $this->key) = explode('/', substr($key, 5), 2);
	return true;
    }

}
