<?php
/*
 * This is the main level class for search on our website.  It works as a factory
 * and should instantiate a child object when created
 */
abstract class util_search {
	const ENGINE_SOLR = 'solr';

	/**
	 * Construct a new search engine driver class
	 * 
	 * @param type $engine
	 * @return interface_search
	 */
	public static function getInstance($engine = self::ENGINE_SOLR) {
		$class = 'util_search_'.$engine;
		if(!isClassValid($class)) {
			trigger_error("Invalid search engine: '$engine' $class", E_USER_ERROR);
		}
		return new $class;
	}
}

