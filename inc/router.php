<?php
define('UNDERSCORE_PLACEHOLDER', chr(1));
define('ARG_SEPARATOR', chr(2));

// Set the prerender mode flag.  If true, no site tests will be run and the view cache will be stored
define('PRERENDER_MODE', isset($_GET['POPULATE_VIEW_CACHE']));

// Smart Routes are pretty URLs where part of the URL corresponds with a class and the rest of it is params.
// If an inbound request is /from/Seattle/November and the corresponding class is site_page_from, Seattle and November will
// be passed to the class constructor as args
$smartRoutes = array();
include(cfg::get('dirHome') . '/inc/smartRoutes.php');

$page = $_SERVER['SCRIPT_NAME'] == '/' ? '/index' : $_SERVER['SCRIPT_NAME'];

$class = 'site_page' . str_replace(array('_', '.php', '.html', '/'), array(UNDERSCORE_PLACEHOLDER, '', '', '_'), $page);

// Trim off any trailing underscores that come from closing slashes
if($class{strlen($class) - 1} == '_') {
	$class = substr($class, 0, -1);
}

$attemptedclass = $class;
$args = generateArgs($attemptedclass, 'site_page');

// Iterate over the smart routes and pick the first match
if(!empty($smartRoutes)) {
	foreach($smartRoutes as $route => $routeClass) {
		if(strpos($attemptedclass, $route) === 0) {
			$args = generateArgs($attemptedclass, $route);
			$class = $routeClass;
			break;
		}
	}
}

/* @var $class core_page */
if(!isClassValid($class)) {
	// The inbound request, after potential smart route matching, does not resolve to a valid class.
	// Generate the args list for the default class then send the user to that page
	$args = generateArgs($attemptedclass, 'site_page');
	$class = DEFAULT_ROUTE;
} else {
	// Determine if the current protocol is allowed by the given controller.  If not, throw the default route.
	$isSSL = isset($_SERVER['HTTPS']);
	$allowed = false;
	if($isSSL) {
		$allowed = $class::getAllowHTTPS();
	} else {
		$allowed = $class::getAllowHTTP();
	}
	if(!$allowed) {
		$args = generateArgs($class, 'site_page');
		$class = DEFAULT_ROUTE;
	}
}

$deviceClassSuffixes = array(
	core_view::DEVICE_DESKTOP => '',
	core_view::DEVICE_MOBILE => 'Mobile'
);
$options = core_view::getViewOptions();
if($options['device'] && !empty($deviceClassSuffixes[$options['device']])) {
	$deviceclass = $class . $deviceClassSuffixes[$options['device']];
	if(isClassValid($deviceclass)) {
		$class = $deviceclass;
	}
}

// Determine whether the page controller uses sessions.  If so, start it up.
if($class::usesSession()) {
	session_id() || session_start();
}

// Give NewRelic a meaningful name for the script.  Otherwise, all pages that hit this point will be identified as router.php.
if (extension_loaded('newrelic')) {
  newrelic_name_transaction('route:' . $class);
}

$page = new $class();
/* @var $page core_page */

core_view::setViewOptions($page);

$page->view->setDefaultView($class);

// Execute the page logic
$page->execute($args);

// Generate page output and display
$page->render();

$page->view->inUse = false;

/**
 * Split up the requested URL into a list of arguments with the first element in the array being the portion of the URL
 * that was matched by the smartRoute and the remainder being the rest of the request split up on "/" bounaries (previously
 * replaced with the const UNDERSCORE_PLACEHOLDER).
 * @var string $reqClass The class that will be loaded
 * @var string $trueClass The smartroute string that was found at the beginning of the URL
 */
function generateArgs($reqClass, $trueClass) {
	// Turn plusses back into spaces
	$argStr = str_replace('+', ' ', $reqClass);

	// Safely recover actual underscores so we can split the URL on directory separators
	$rawArgs = str_replace(
		array('_', UNDERSCORE_PLACEHOLDER),
		array(ARG_SEPARATOR, '_'),
		substr($argStr, strlen($trueClass)));

	// In the case that the smartRoute matched an entire directory name, there may be a slash at the beginning of the remainder.
	// Trim that off here.
	if(isset($rawArgs{0}) && $rawArgs{0} == ARG_SEPARATOR) {
		$rawArgs = substr($rawArgs, 1);
	}

	// We want the first element of the array to be the part of the smartRoute that we matched so merge them back together.
	if(strpos($trueClass, 'site_page_') === 0) {
		$trueClass = substr($trueClass, strlen('site_page') + 1);
	}

	return array_merge(array($trueClass), explode(ARG_SEPARATOR, $rawArgs));
}
