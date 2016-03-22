<?php
/**
 * The core page class that is meant to be extended by any page controller.
 *
 * This class implements interface_page intentionally but does not fulfill the
 * contract, forcing the page controller itself to provide the missing method(s).
 *
 * @package framework
 * @subpackage core
 */
abstract class core_page
{
	/** @var core_view $view */
	public $view;

	// By default, pages have only HTTP visibility
	// These are static because they are checked before the class is instantiated.
	protected static $allowHTTP = true;
	protected static $allowHTTPS = false;

	// By default, pages do not allow view caching
	protected $viewCacheAllowed = false;
	protected $viewCacheTTL = SEC_WEEK;

	protected $pageCacheContextData = array();
	protected $pageCacheContextFlags = array();

	/** @var core_http_header A header object, stores request and response headers. */
	protected $header = null;

	const CACHE_DATA_BOUNDARY = '###CACHE.DATA.PREFIX.DELIM###';

	/**
	 * Instantiate the class.  Grab the global view and store it in an instance variable
	 */
	public function __construct() {
		$this->header = new core_http_header();

		$this->view = core_view::getInstance();
		$this->view->setDefaultView(get_class($this));
	}

	/**
	 * Return whether the page supports HTTP
	 *
	 * @return boolean $allowHTTP
	 */
	public static final function getAllowHTTP() {
		return static::$allowHTTP;
	}

	/**
	 * Return whether the page supports HTTPS
	 *
	 * @return boolean $allowHTTPS
	 */
	public static final function getAllowHTTPS() {
		return static::$allowHTTPS;
	}

	/**
	 * Return whether the page controller is view caching enabled.
	 *
	 * @return bool $allowViewCache
	 */
	public final function getAllowViewCache() {
		return $this->viewCacheAllowed;
	}

	/**
	 * Set up an alternate view
	 *
	 * @param string $altName The name of the alternate view
	 */
	protected function setAltView($altName) {
		$this->view->setAltView($altName);
	}

	/**
	 * Returns whether the page uses session
	 *
	 * @return boolean
	 */
	public static function usesSession() {
		return true;
	}

	/**
	 * Call the page controller
	 */
	public abstract function execute($args = array());

	public function render() {

		// Output the response headers before rendering any content
		$this->header->send();

		if($this->getAllowViewCache()) {
			$data = $this->loadPageCache();
			if(empty($data)) {
				header('x-vcache: 0');
				$html = $this->view->preRender();
				$pageData = $this->view->getPageData();
				$context = $this->getPageCacheContextData();

				$data = serialize($pageData) . self::CACHE_DATA_BOUNDARY
					. serialize($context) . self::CACHE_DATA_BOUNDARY
					. $html;
				$this->savePageCache($data);
			} else {
				header('x-vcache: 1');
				list($pageData, $context, $html) = explode(self::CACHE_DATA_BOUNDARY, $data);
				$context = unserialize($context);
				$pageData = unserialize($pageData);
				$this->setPageCacheContextData($context);
			}
			$this->view->renderCachedHtml($html, $pageData);
		} else {
			$this->view->render();
		}
	}

	/**
	 * Get the canonical URL for this page
	 */
	public function getCanonicalUrl($args = array()) {
		$newUrl = urlencode($_SERVER['SCRIPT_URI']);
		return $newUrl;
	}

	/**
	 * Is the present URL the canonical URL?
	 */
	public function isCanonicalUrl($args = array()) {
		return true;
	}

	protected function getPageCacheKey() {
		$context = '#';
		foreach($this->getPageCacheContextFlags() as $key => $val) {
			$context .= "{$key}={$val}&";
		}

		$cacheKey = 'pageViewCache::' . $_SERVER['SCRIPT_URL'] . $context;
		return $cacheKey;
	}

	/**
	 * Check if this controller supports views for a device
	 *
	 * @param core_view::DEVICE $device
	 * @return bool
	 */
	public function hasDeviceViewSupport($device) {
		return $device == core_view::DEVICE_DESKTOP;
	}

	/**
	 * Get any other contextual information that only the page can provide that differentiates different versions of the page.
	 * @return array()
	 */
	public final function getPageCacheContextFlags() {
		return $this->pageCacheContextFlags;
	}

	/**
	 * Set any other contextual information that only the page can provide that differentiates different versions of the page.
	 * @return array()
	 */
	public final function setPageCacheContextFlags($flags) {
		$this->pageCacheContextFlags = array_merge($this->pageCacheContextFlags, $flags);
	}

	/**
	 * Get supporting data required to render the page in a given context.
	 * @return array
	 */
	protected final function getPageCacheContextData() {
		return $this->pageCacheContextData;
	}

	/**
	 * Set supporting data required to render the page in a given context.
	 * @param array $data
	 */
	protected final function setPageCacheContextData($data) {
		$this->pageCacheContextData = array_merge($this->pageCacheContextData, $data);
	}

	protected function loadPageCache() {
		if(PRERENDER_MODE) {
			return null;
		} else {
			$cache = new core_cache(CACHE_NETWORK);
			$cacheKey = $this->getPageCacheKey();
			return $cache->get($cacheKey);
		}
	}

	protected function savePageCache($data) {
		$cache = new core_cache(CACHE_NETWORK);
		$cacheKey = $this->getPageCacheKey();
		$cache->set($cacheKey, $data, $this->viewCacheTTL);
	}
}
