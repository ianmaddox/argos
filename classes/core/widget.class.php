<?php

abstract class core_widget implements interface_widget {

	// NOTE:  Declare the following constant in your widget class if you want it to be cached at the view level.
	const VIEW_CACHE_ENABLED = false;

	protected $idPrefix = '';

	/**
	 * @var core_view
	 */
	protected $view = null;

	/**
	 */
	public function __construct() {
		$this->view = core_view::getInstance(get_class($this));
	}

	/**
	 * Render this widget
	 */
	public function render() {
		$this->view->idPrefix = $this->idPrefix;
		$this->view->render();
	}

	/**
	 * Use render() to output to a string
	 *
	 * @return string
	 */
	public final function renderToString() {
		ob_start();
		$this->render();
		return ob_get_clean();
	}

	/**
	 * Set the ID prefix for this widget
	 *
	 * @param string $idPrefix
	 */
	public function setIdPrefix($idPrefix) {
		if($idPrefix != '' && !ctype_alpha($idPrefix[0])) {
			$this->idPrefix = 'x' . (string)$idPrefix;
		} else {
			$this->idPrefix = (string)$idPrefix;
		}
	}

}
