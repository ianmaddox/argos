<?php
/**
 * This class is for individual search results
 */
class util_search_result {
	public $id = '';
	public $author = array();
	public $authorurl = array();
	public $categories = array();
	public $prodtype = '';
	public $publisher = '';
	public $sku = '';
	// public $text = ''; // not populated during a search
	public $title = '';
	public $topic = '';
	public $topicid = 0;
	public $topicJSON = '';
	public $topictitle = '';
	public $url = '';

	public $score = '';

	public $highlights = array(); //fieldname : highlight text
}
