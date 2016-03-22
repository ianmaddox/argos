<?php
/**
 * This file is included at the end of every PHP execution.  We generally don't want to do anything here, but this file
 * serves as a placeholder for future functionality.
 *
 * @package framework
 * @subpackage inc
 */

// Check for a site-specific prepend script and load it.
if(file_exists_path('local_append.php')) {
	require_once('local_append.php');
}
