<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class util_search_solr implements interface_search {
	private $server;
	private $apiVersion = '2.2';
	//columns available
	private $columns = array(
		'id', 'topic', 'sku', 'prodType', 'topicTitle', 'title', 'author'
	);
	//group by column
	private $groupBy = 'topic';
	//should or should not return the group that has no values
	private $returnBlankGroup = false;

	//internal search properties
	private $searchFor = '';
	private $perPage = 50;
	private $offset = 0;
	private $recordCount = 0;

	//insert data properties
	private $insertData = false;

	public function __construct() {
		$this->server = cfg::get('searchHost') . cfg::get('searchPath');
	}

	/**
	 * Search for a term (optionally give an offset)
	 * @param string $searchFor String to search for
	 * @param int $offset offset to begin at, default is 0
	 * @return mixed false or search results
	 */
	public function search($searchFor, $offset=0) {
		$this->searchFor = $searchFor;
		$this->offset = intval($offset);
		return $this->doSearch();
	}

	/**
	 * queries solr server with information required
	 * @return mixed false or the search results information
	 */
	private function doSearch() {
		$searchTerm = addslashes(trim($this->searchFor));
		// Define the levenshtein threshold from 0 to 1.  Higher values are less fuzzy searches.
		$levenshteinLimit = "0.7";

		// Make sure the termMod has a space at the end or all the search terms will be mashed together.
		$termMod = '~' . $levenshteinLimit . ' ';
		$searchTerm = addcslashes($searchTerm, '+-&|!(){}[]^"~*?:\\');
		$searchTerm = str_replace(' ', $termMod, $searchTerm) . $termMod;

		$request = $this->server . '/select' .
			'?indent=on' .
			'&version=' . $this->apiVersion .
			'&start=' . $this->offset .
			'&rows=' . $this->perPage .
			'&fl=*' . urlencode(',score') .
			'&wt=json' .
			'&explainOther=' .
			'&hl=true' .
			'&hl.fl=title,author,text' .
			'&fq=';
		$searchClause = 'title:' . $searchTerm .
			' OR topictitle:' . $searchTerm .
			' OR text:' . $searchTerm .
			' OR author:' . $searchTerm;
		if(!empty($this->groupBy)) {
			if(!$this->returnBlankGroup) {
				$request .= urlencode('-' . $this->groupBy . ':""');
			}
			$request .= "&group=true&group.ngroups=true&group.field=" . $this->groupBy;
		}
		$request .= '&q=' . urlencode($searchClause);
		$result = file_get_contents($request);
		if(!$result) {
			return false;
		} else {
			return $this->processResult($result);
		}
	}

	/**
	 * Gets the search server to be used with an instance
	 * @return string server URL
	 */
	public function getSearchServer() {
		return $this->server;
	}

	/**
	 * Sets the search server to be used with an instance, utilized by the worker when initializing a new solr instance
	 * @param string $server The server you would like to connect to
	 */
	public function setSearchServer($server = null) {
		if (!empty($server)) {
			$this->server = $server;
		}
	}

	/**
	 * Process the data returned from the Solr server
	 * @param string $resultJSON Data from Solr
	 * @return mixed false or data array
	 */
	private function processResult($resultJSON) {
		$results = json_decode($resultJSON);
		$resultData = new util_search_resultSet();
		$this->recordCount = 0;
		$docIDs = array();
		if(!empty($this->groupBy)) {
			//deal with grouped results
			if(!empty($results->grouped->{$this->groupBy}->matches)) {
				if(!empty($results->grouped->{$this->groupBy}->groups)) {
					$resultsRecords = $results->grouped->{$this->groupBy}->groups;
					$this->recordCount = $results->grouped->{$this->groupBy}->ngroups;
					foreach($resultsRecords as $record) {
						if(!empty($record->doclist->docs[0])) {
							$result = new util_search_result();
							$result->id = ((!empty($record->doclist->docs[0]->id)) ? $record->doclist->docs[0]->id : '');
							$result->author = ((!empty($record->doclist->docs[0]->author)) ? $record->doclist->docs[0]->author : array());
							$result->authorurl = ((!empty($record->doclist->docs[0]->authorurl)) ? $record->doclist->docs[0]->authorurl : array());
							$result->categories = ((!empty($record->doclist->docs[0]->categories)) ? $record->doclist->docs[0]->categories : array());
							$result->prodtype = ((!empty($record->doclist->docs[0]->prodtype)) ? $record->doclist->docs[0]->prodtype : '');
							$result->publisher = ((!empty($record->doclist->docs[0]->publisher)) ? $record->doclist->docs[0]->publisher : '');
							$result->sku = ((!empty($record->doclist->docs[0]->sku)) ? $record->doclist->docs[0]->sku : '');
							// "text" is available
							$result->title = ((!empty($record->doclist->docs[0]->title)) ? $record->doclist->docs[0]->title : '');
							$result->topic = ((!empty($record->doclist->docs[0]->topic)) ? $record->doclist->docs[0]->topic : '');
							$result->topicid = ((!empty($record->doclist->docs[0]->topicid)) ? $record->doclist->docs[0]->topicid : 0);
							$result->topicJSON = ((!empty($record->doclist->docs[0]->topicJSON)) ? $record->doclist->docs[0]->topicJSON : '');
							$result->topictitle = ((!empty($record->doclist->docs[0]->topictitle)) ? $record->doclist->docs[0]->topictitle : '');
							$result->url = ((!empty($record->doclist->docs[0]->url)) ? $record->doclist->docs[0]->url : '');

							// score: provided by solr, I assume?
							$result->score = ((!empty($record->doclist->docs[0]->score)) ? $record->doclist->docs[0]->score : '');

							$resultData->addResult($result);
							$docIDs[$result->id] = count($resultData)-1;
						}
					}
				}
			}
			//deal with highlighting
			if(!empty($results->highlighting)) {
				foreach($results->highlighting as $docID => $highlightData) {
					if(isset($docIDs[$docID])) {
						if($resultData[$docIDs[$docID]]->id == $docID) {
							//found the matching doc
							$highlightArray = array();
							foreach ($highlightData as $key => $value) {
								$highlightArray[$key] = $value;
							}
							$resultData[$docIDs[$docID]]->highlights = $highlightArray;
						}
					}
				}
			}
		} else {
			//TODO: handle non-grouped results
		}
		return $resultData;
	}

	/**
	 * Add/update content in the search engine, pass true for $batch if adding multiple docs, then call batchAdd when done
	 * @param util_search_insert $doc Search document information to create
	 * @param boolean $batch Is this a batch insert, default is false
	 */
	public function addContent(util_search_insert $doc, $batch=false) {
		if(!$batch || !$this->insertData) {
			$this->insertData = new util_search_insertSet();
		}
		$this->insertData->addDocument($doc);
		if(!$batch) {
			$this->batchAdd();
		}
	}

	/**
	 * Add any records that have been queued
	 * @return boolean success of attempt
	 */
	public function batchAdd() {
		$url = $this->server . "/update/json";

		//process insertData records
		//create search document from each record and then call solr update
		$addNode = new stdClass;
		$nodeIDs = array();
		foreach ($this->insertData as $docData) {
			$nodeIDs[] = $nodeID = uniqid();
			$addNode->$nodeID->doc->id = $docData->id;
			$addNode->$nodeID->doc->author = $docData->author;
			$addNode->$nodeID->doc->authorurl = $docData->authorurl;
			$addNode->$nodeID->doc->categories = $docData->categories;
			$addNode->$nodeID->doc->prodtype = $docData->prodtype;
			$addNode->$nodeID->doc->publisher = $docData->publisher;
			$addNode->$nodeID->doc->sku = $docData->sku;
			$addNode->$nodeID->doc->text = $docData->text;
			$addNode->$nodeID->doc->title = $docData->title;
			$addNode->$nodeID->doc->topic = $docData->topic;
			$addNode->$nodeID->doc->topicid = $docData->topicid;
			$addNode->$nodeID->doc->topicJSON = $docData->topicJSON;
			$addNode->$nodeID->doc->topictitle = $docData->topictitle;
			$addNode->$nodeID->doc->url = $docData->url;

			$addNode->$nodeID->boost = $docData->resultModifier;
		}
		$addNode->commit = new stdClass();
		$requestJson = str_replace($nodeIDs, 'add', json_encode($addNode));

		//curl call for solr update
		$solrCurl = curl_init();
		curl_setopt($solrCurl, CURLOPT_URL, $url);
		curl_setopt($solrCurl, CURLOPT_HEADER, 1);
		curl_setopt($solrCurl, CURLOPT_HTTPHEADER, array('Content-type:application/json'));
		curl_setopt($solrCurl, CURLOPT_POST, 1);
		curl_setopt ($solrCurl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($solrCurl, CURLOPT_POSTFIELDS, $requestJson);
		$addResult = curl_exec($solrCurl);
		if(!$addResult) {
			trigger_error("Solr curl failure\n" . curl_errno($solrCurl) . "\n" . curl_error($solrCurl) . "\n", E_USER_NOTICE);
		}
		$info = curl_getinfo($solrCurl);
		if($info['http_code'] != 200) {
			trigger_error("Solr Response {$addResult}\n", E_USER_WARNING);
		}
		curl_close($solrCurl);

		$this->insertData = new util_search_insertSet();
		return true;
	}

	/**
	 * Returns the offset of the current result set
	 * @return int Offset of current result set
	 */
	public function getOffset() {
		return $this->offset;
	}

	/**
	 * Gets the number of records to show per page
	 * @return int Number of records per page
	 */
	public function getPerPage() {
		return $this->perPage;
	}

	/**
	 * Set the number of records to show per page
	 * @param int $perPage number of records to show per page
	 */
	public function setPerPage($perPage) {
		$this->perPage = intval($perPage);
	}

	/**
	 * Return the total number of records found
	 * @return int total records found
	 */
	public function getRecordCount() {
		return $this->recordCount;
	}
}
