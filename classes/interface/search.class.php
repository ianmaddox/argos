<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

interface interface_search {
	/**
	 * Search for a term (optionally give an offset)
	 * @param string $searchFor String to search for
	 * @param int $offset offset to begin at, default is 0
	 * @return util_search_resultSet Search results
	 */
	public function search($searchFor, $offset=0);

	/**
	 * Add/update content in the search engine, pass true for $batch if adding multiple docs, then call batchAdd when done
	 * @param util_search_insert $contentInfo Search information to create
	 * @param boolean $batch Is this a batch insert, default is false
	 */
	public function addContent(util_search_insert $contentInfo, $batch=false);

	/**
	 * Returns the offset of the current result set
	 * @return int Offset of current result set
	 */
	public function getOffset();

	/**
	 * Gets the number of records to show per page
	 * @return int Number of records per page
	 */
	public function getPerPage();

	/**
	 * Set the number of records to show per page
	 * @param int $perPage number of records to show per page
	 */
	public function setPerPage($perPage);

	/**
	 * Return the total number of records found
	 * @return int total records found
	 */
	public function getRecordCount();
}
