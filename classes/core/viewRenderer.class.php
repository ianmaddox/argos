<?php

/**
 * This class is a very simple sandbox which takes an array of data, makes all of the variables local, and
 * then loads another PHP file which pulls from the local vars.
 *
 * This prevents view template from accidentally tampering with important vars in the view controller.
 * It is a tool for the view class and should not be instantiated directly.
 *
 * @author ianmaddox
 *
 * @package framework
 * @subpackage core
 */
class core_viewRenderer extends core_view
{
	/**
	 * @var core_view $view The view that we're here to render.
	 */
	public $view;

	private $forCache;

	private $widgetObjs = array();

	/**
	 * Constructor
	 *
	 * @param array $data
	 * @param string $template
	 */
	public function __construct($data, $template, $preRender = null, $postRender = null, $forCache = false) {
		$this->data = $data;
		$this->forCache = $forCache;

		if(!empty($preRender)) {
			// Kick off all the pre render widgets
			foreach($preRender as $widgetName) {
				$this->runWidget($widgetName);
			}
		}

		// Fire off the view
		include($template);

		if(!empty($postRender)) {
			// Kick off all the post render widgets
			foreach($postRender as $widgetName) {
				$this->runWidget($widgetName);
			}
		}
	}

	/**
	 * Escape a scalar or single-dimensional array, stripping special characters.
	 *
	 * @param mixed $raw
	 * @return mixed
	 */
	public function escape($raw) {
		if(is_array($raw)) {
			$filtered = array();
			foreach($raw as $key => $val) {
				$filtered[$key] = filter_var($val, FILTER_SANITIZE_SPECIAL_CHARS);
			}
			return $filtered;
		} else {
			return filter_var($raw, FILTER_SANITIZE_SPECIAL_CHARS);
		}
	}

	/**
	 * Run a widget
	 *
	 * @param string $widgetName The widget's name
	 * @param options Optional data for the widget
	 */
	public function runWidget($widgetName) {
		if(empty($this->widgetObjs[$widgetName])) {
			// Pop the widget name off the args list
			call_user_func_array(array($this,'initWidget'), func_get_args());
		}
		/* @var $widget interface_widget */
		if($this->forCache) {
			$widgetClass = $this->widgetObjs[$widgetName]['class'];
			// Default behavior for all widgets when building cache is to return a placeholder.
			// Only VIEW_CACHE_ENABLED = TRUE widgets will return HTML to the template.
			if(!defined("$widgetClass::VIEW_CACHE_ENABLED") || $widgetClass::VIEW_CACHE_ENABLED == false) {
				$widgetPlaceholder =
					self::DEFERRED_WIDGET_DELIM_PREFIX . self::DEFERRED_WIDGET_DELIM_START
					. serialize(
						array(
							'widgetClass' => $widgetClass,
							'args' => $this->widgetObjs[$widgetName]['args']
						)
					)
					. self::DEFERRED_WIDGET_DELIM_PREFIX . self::DEFERRED_WIDGET_DELIM_END;
				 echo $widgetPlaceholder;
				 return;
			}
		}
		echo $this->widgetObjs[$widgetName]['obj']->render();
		unset($this->widgetObjs[$widgetName]);
	}

	/**
	 * Rapper for runWidget() that returns the HTML instead of outputting it.
	 * @param string $widgetName
	 * @return string HTML
	 */
	public function initWidget($widgetName) {
		// Collapse possible directory traversal attempts   +
		$widgetName = str_replace(array('.', '/'), '', $widgetName);
		$widgetClass = 'site_widget_' . $widgetName;


		// Grab the dynamic args list
		$args = func_get_args();

		// Pop the widget name off the args list
		array_shift($args);

		// Make a reflection object
		$reflectionObj = new ReflectionClass($widgetClass);
		// Use Reflection to create a new instance, using the $args
		$this->widgetObjs[$widgetName]['class'] = $widgetClass;
		$this->widgetObjs[$widgetName]['args'] = $args;
		$this->widgetObjs[$widgetName]['obj'] = $reflectionObj->newInstanceArgs($args);
	}

	/**
	 * Get an escaped version of a view variable
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function getEsc($key) {
		return $this->escape($this->get($key));
	}

	/**
	 * Format a string as dollars, adding the dollar sign
	 *
	 * @param float $val
	 * @param bool $includeCents
	 * @return string
	 */
	public function dollars($val, $includeCents) {
		$decimals = $includeCents ? 2 : 0;
		return '$' . number_format($val, $decimals);
	}

	/**
	 * Turn a UNIX timestamp into a standard date value
	 *
	 * @param int $timestamp
	 * @return string
	 */
	public function date($timestamp) {
		return date('m/d/Y', $timestamp);
	}

	/**
	 * Turn a UNIX timestamp into a standard date value
	 *
	 * @param int $timestamp
	 * @return string
	 */
	public function longDate($timestamp) {
		return date('F j, Y', $timestamp);
	}

	/**
	 * Turn a UNIX timestamp into a standard time value
	 *
	 * @param int $timestamp
	 * @return string
	 */
	public function time($timestamp) {
		return date('G:i a', $timestamp);
	}

	/**
	 * Turn any string URL friendly
	 *
	 * <ol>
	 * <li>Lowercase</li>
	 * <li>Remove apostrophes</li>
	 * <li>Replace any non-letter, non-digit substring with a single hyphen</li>
	 * <li>Strip leading and trailing hyphens</li>
	 * </ol>
	 *
	 * @param string $string
	 * @return string
	 */
	public function urlFriendly($string) {
		return trim(preg_replace('/[^\w]+/i', '-', str_replace("'", '', strtolower($string))), '-');
	}

	/**
	 * Turn a UNIX timestamp into a standard date/time value
	 *
	 * @param int $timestamp
	 * @return string
	 */
	public function dateTime($timestamp) {
		return date('m/d/Y G:i a', $timestamp);
	}

	/**
	 * A helper function for creating drop-down select boxes
	 * @param string name field name.  If id is not provided in $tagArgs, it will be the same as the name
	 * @param array associative options list value=>label
	 * @param string value of selected item
	 * @param array extra args to place into the tag
	 */
	public function inputSelect($name, $options, $selectedVal = false, $tagArgs = array()) {
		$name = htmlspecialchars($name, ENT_QUOTES);
		$args = '';
		$id = $name;
		foreach($tagArgs as $key => $val) {
			$key = htmlspecialchars($key, ENT_QUOTES);
			$val = htmlspecialchars($val, ENT_QUOTES);
			if($key != 'id') {
				$args .= " $key='$val'";
			} else {
				$id = $val;
			}
		}
		$ret = "<select name='{$name}' id='{$id}'{$args}>\n";
		foreach($options as $val => $desc) {
			$sel = $selectedVal == $val ? ' SELECTED' : '';
			$val = htmlspecialchars($val, ENT_QUOTES);
			$desc = htmlspecialchars($desc, ENT_QUOTES);
			$ret .= "<option value='{$val}'{$sel}>{$desc}</option>\n";
		}
		$ret .= "</select>";
		return $ret;
	}

	/**
	 * Creates the HTML necessary for pure CSS buttons on our site.
	 * @param array $options Options for button:
	 *				color: green, orange, blue, solidblue
	 *				text: the text you'd like in the button (can be HTML)
	 *				alt: the text you'd like for the alt/title tag
	 *              name: the name / id for the button
	 *              width: textwidth, small (s), medium (m), large (l), xlarge (xl), or a css width value
	 * @return string button code to insert into page
	 */
	public function createCSSButton($options) {
		$myOptions = array(
			'color' => ((!empty($options['color'])) ? $options['color'] : 'green'),
			'text' => ((!empty($options['text'])) ? $options['text'] : 'Submit'),
			'textstyle' => ((!empty($options['textstyle'])) ? $options['textstyle'] : 'none'),
			'alt' => ((!empty($options['alt'])) ? $options['alt'] : ''),
			'title' => ((!empty($options['alt'])) ? $options['title'] : ''),
			'name' => ((!empty($options['name'])) ? $options['name'] : 'Submit'),
			'width' => ((!empty($options['width'])) ? $options['width'] : 'textwidth'),
			'height' => ((!empty($options['height'])) ? $options['height'] : ''),
			'class' => ((!empty($options['class'])) ? $options['class'] : ''),
			'href' => ((!empty($options['href'])) ? $options['href'] : ((!empty($options['onclick'])) ? '#' : 'javascript:void(0);')),
			'onclick' => ((!empty($options['onclick'])) ? $options['onclick'] : ''),
			'style' => ((!empty($options['style'])) ? $options['style'] : ''),
		);
		$classAddition = (($myOptions['class']) ? " {$myOptions['class']}" : '');
		switch (strtolower($myOptions['color'])) {
			case 'green':
				unset($myOptions['color']);
				$classAddition .= ' buttonGreen';
				break;
			case 'solidgreen':
				unset($myOptions['color']);
				$classAddition .= ' buttonSolidGreen';
				break;
			case 'orange':
				unset($myOptions['color']);
				$classAddition .= ' buttonOrange';
				break;
			case 'blue':
				unset($myOptions['color']);
				$classAddition .= ' buttonBlue';
				break;
			case 'solidblue':
				unset($myOptions['color']);
				$classAddition .= ' buttonSolidBlue';
				break;
			case 'clearblue':
				unset($myOptions['color']);
				$classAddition .= ' buttonClearBlue';
				break;
			case 'cleargreen':
				unset($myOptions['color']);
				$classAddition .= ' buttonClearGreen';
				break;
			case 'clearorange':
				unset($myOptions['color']);
				$classAddition .= ' buttonClearOrange';
				break;
			case 'cleargray':
				unset($myOptions['color']);
				$classAddition .= ' buttonClearGray';
				break;
			case 'whiteblue':
				unset($myOptions['color']);
				$classAddition .= ' buttonWhiteBlue';
				break;
			case 'whitegreen':
				unset($myOptions['color']);
				$classAddition .= ' buttonWhiteGreen';
				break;
			case 'whiteorange':
				unset($myOptions['color']);
				$classAddition .= ' buttonWhiteOrange';
				break;
			case 'whitegray':
				unset($myOptions['color']);
				$classAddition .= ' buttonWhiteGray';
				break;
			case 'gradientblue':
				unset($myOptions['color']);
				$classAddition .= ' buttonGradientBlue';
				break;
		}
		switch (strtolower($myOptions['textstyle'])) {
			case 'aswrit':
				$classAddition .= ' asWrit';
				break;
		}
		switch (strtolower($myOptions['width'])) {
			case 'small':
			case 's':
			case '60px':
				unset($myOptions['width']);
				$classAddition .= ' buttonSmall';
				break;
			case 'medium':
			case 'm':
			case '100px':
				unset($myOptions['width']);
				$classAddition .= ' buttonMedium';
				break;
			case 'large':
			case 'l':
			case '150px':
				unset($myOptions['width']);
				$classAddition .= ' buttonLarge';
				break;
			case 'x-large':
			case 'xlarge':
			case 'xl':
			case '200px':
				unset($myOptions['width']);
				$classAddition .= ' buttonXLarge';
				break;
			case 'textwidth':
				unset($myOptions['width']);
				break;
		}
		$styleProperty = (($myOptions['style']) ? $myOptions['style'] : '');
		if(!empty($myOptions['color'])) {
			$styleProperty .= (($styleProperty) ? ' ' : '') . "background-color: {$myOptions['color']};";
			unset($myOptions['color']);
		}
		if(!empty($myOptions['width'])) {
			$styleProperty .= (($styleProperty) ? ' ' : '') . "width: {$myOptions['width']};";
			unset($myOptions['width']);
		}
		if(!empty($myOptions['height'])) {
			$styleProperty .= (($styleProperty) ? ' ' : '') . "height: {$myOptions['height']};";
			unset($myOptions['height']);
		}
		if(empty($myOptions['alt'])) {
			$myOptions['alt'] = $myOptions['title'];
		}
		if(empty($myOptions['title'])) {
			$myOptions['title'] = $myOptions['alt'];
		}
		if(empty($myOptions['alt'])) {
			$myOptions['alt'] = $myOptions['title'] = htmlentities($myOptions['text'], ENT_QUOTES);
		}
		if($styleProperty) $styleProperty = " style='{$styleProperty}'";
		$buttonHTML = "<div class='buttonWrapper{$classAddition}'{$styleProperty}><a href='{$myOptions['href']}'";
		if($myOptions['onclick']) {
			$buttonHTML .= " onclick='" . htmlentities($myOptions['onclick'], ENT_QUOTES) . "'";
		}
		if($myOptions['name']) {
			$buttonHTML .= " name='" . $myOptions['name'] . "' id='" . $myOptions['name'] . "'";
		}
		if($myOptions['alt']) {
			$buttonHTML .= " alt='" . $myOptions['alt'] . "' title='" . $myOptions['title'] . "'";
		}
		$buttonHTML .= ">{$myOptions['text']}</a></div>";
		return $buttonHTML;
	}

	/**
	 * Get a singular or plural word
	 *
	 * Very trivial code - here for convenience
	 *
	 * @param int $number
	 * @param mixed $singular
	 * @param mixed $plural
	 * @return mixed
	 */
	public function plural($number, $singular, $plural) {
		return ($number == 1 ? $singular : $plural);
	}

}
