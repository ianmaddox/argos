<?php
/**
 * This class controls the cacheEx flag which is used to expire static page elements (css, js, images) all at once.
 * It is locally cached to improve performance and reduce load on the network server.
 */

class util_cssCacheEx {

    //the value for the cache buster string
    private $cacheEx = null;
    //the cache key we're using to track
    private $cacheKey = "cssCacheEx";
    private $masterCache = core_cache::ENGINE_REDIS;
    private $localCache = CACHE_MEMORY;
    private $localTTL = SEC_MINUTE;

    /**
     * A candy wrapper for ease of use.
     */
    public static function get() {
        $obj = new self;
        return $obj->getCacheEx();
    }

    public function __construct() {
        $this->getCacheEx();
    }

    /**
     * Retrieves the new cacheex value from cache
     * @return int Timestamp from last update
     */
    public function getCacheEx() {
        if (!empty($this->cacheEx)) {
            return $this->cacheEx;
        }
        $localCache = new core_cache($this->localCache);
        $cacheValue = $localCache->get($this->cacheKey);

        if (!$cacheValue) {
            $masterCache = new core_cache($this->masterCache);
            $cacheValue = $masterCache->get($this->cacheKey);
            if (!$cacheValue) {
                // If there is no cacheEx value, roll the dice to help prevent cache slam.
                if (rand(0, 100) == 1) {
                    $cacheValue = $this->resetCacheEx();
                } else {
		    // Not cached, just throwing something out there
		    $cacheValue = 'nc-' + time();
		}
            } else {
                $localCache->set($this->cacheKey, $cacheValue, $this->localTTL);
	    }
        }
        $this->cacheEx = $cacheValue;
        return $this->cacheEx;
    }

    /**
     * Retrieves the cacheex value as a date
     * @return string Date of cacheex value
     */
    public function getCacheExDate() {
        $cacheEx = $this->getCacheEx();
        return date('Y/m/d H:i:s', $cacheEx);
    }

    /**
     * Resets cachex value when called, returns the new cache value
     * @return int Timestamp for new cache value
     */
    public function resetCacheEx() {
        $this->cacheEx = time();
        $masterCache = new core_cache($this->masterCache);
        $masterCache->set($this->cacheKey, $this->cacheEx, SEC_YEAR);

        $localCache = new core_cache($this->localCache);
        $localCache->set($this->cacheKey, $this->cacheEx, $this->localTTL);
        return $this->cacheEx;
    }
}
