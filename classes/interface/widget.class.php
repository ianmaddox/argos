<?php
/**
 *
 * @package framework
 * @subpackage interface
 */
interface interface_widget {
	// NOTE:  Declare the following constant in your widget class if you want it to be cached at the view level.
	// const VIEW_CACHE_ENABLED = true;

	public function render();
}