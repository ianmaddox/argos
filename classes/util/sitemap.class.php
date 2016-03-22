<?php

/**
 * Generate an XML sitemap based on the common sitemaps protocol:
 *    http://www.sitemaps.org/protocol.php
 *
 * @package framework
 * @subpackage util
 */

class util_sitemap {
	const FREQUENCY_ALWAYS  = 'always';
	const FREQUENCY_HOURLY  = 'hourly';
	const FREQUENCY_DAILY   = 'daily';
	const FREQUENCY_WEEKLY  = 'weekly';
	const FREQUENCY_MONTHLY = 'monthly';
	const FREQUENCY_YEARLY  = 'yearly';
	const FREQUENCY_NEVER   = 'never';

	const MAX_SITEMAP_FILE_SIZE = 10000000; // 10 MiB max.  10MB is safe.
	const MAX_SITEMAP_URL_COUNT = 50000;

	// Define some flags to help us keep track of the read/write carets in the temp file
	const TMP_ACTION_READ = 'r';
	const TMP_ACTION_WRITE = 'w';

	private $nodes = array();
	private $fname;
	private $dir;
	private $tmpFp;
	private $tmpFile;
	private $tmpCaretRead = 0;
	private $tmpCaretWrite = 0;
	private $tmpLastAction;
	private $openFileSize = 0;
	private $saveFp;
	protected $fileNumber = array();
	private $fileUrlCount = 0;

	/**
	 * Construct the sitemaps class.
	 * Define the output file (used for both DB and file output)
	 */
	public function __construct() {
		$this->tmpFile = '/tmp/sitemap-workfile-'.uniqid();
	}

	/**
	 * Set the output file name
	 *
	 * @param string $fileNameBase
	 */
	public function setFileName($fileNameBase) {
		if($this->openFileSize > 0) {
			$this->saveToFile();
			$this->finalizeFile();
		}
		// Strip the .xml extension off the file if one was provided.
		$this->fname = str_replace('.xml','',$fileNameBase);
	}

	/**
	 * Create an individual sitemaps entry duplicate URLs are merged.
	 * Higher priorities and newer modified dates take precedence.
	 * Changefreq is not overwritten if previously set
	 *
	 * @param string $url
	 * @param core_dateTime $lastModified
	 * @param float $priority
	 * @param const $changeFreq
	 * @return bool success
	 */
	public function addNode($url, $priority = false, $changeFreq = false, core_dateTime $lastModified = null) {
		if(!$this->validateUrl($url) || !$this->validatePriority($priority || $this->validateChangeFreq($changeFreq))) {
			return false;
		}

		$modDate = $lastModified == null ? false : $lastModified->getDate();
		if(!empty($url) && substr($url, 0, -1) === "/") {
			$url = substr($url, 0, strlen($url)-1);
		}
		$nodeData = array(
			'url' => $url,
			'lastModified' => $modDate,
			'priority' => (float)$priority,
			'changeFreq' => $changeFreq
		);
		$this->setNode($nodeData);

		return true;
	}

	/**
	 * Open or reopen the working temp file
	 */
	private function openTempFile() {
		$this->tmpCaretRead = $this->tmpCaretWrite = 0;
		// Close the file if it is already open.
		if($this->tmpFp) {
			fclose($this->tmpFp);
		}

		// Opening with w+ overwrites the file, deleting its contents.
		$this->tmpFp = fopen($this->tmpFile,'w+');
		if(!$this->tmpFp) {
			trigger_error("Cannot create temp file");
		}
	}

	/**
	 * Close the temp file
	 */
	private function closeTempFile() {
		$this->tmpCaretRead = $this->tmpCaretWrite = 0;
		if(!$this->tmpFp) {
			return;
		}
		fclose($this->tmpFp);
		unlink($this->tmpFile);
		$this->tmpFp = false;
	}

	/**
	 * Turn an individual node into XML
	 *
	 * @param string $url
	 * @param array $nodeData
	 * @return string XML node data
	 */
	private function renderNode($nodeData) {
		$nodeXml = "<url><loc>{$nodeData['url']}</loc>";
		if(!empty($nodeData['lastModified'])) {
			$nodeXml .= '<lastmod>' . $nodeData['lastModified'] . '</lastmod>';
		}

		if($nodeData['priority'] === 0 || !empty($nodeData['priority'])) {
			$nodeXml .= "<priority>" . number_format((float)$nodeData['priority'],1) . "</priority>";
		}

		if(!empty($nodeData['changeFreq'])) {
			$nodeXml .= "<changefreq>{$nodeData['changeFreq']}</changefreq>";
		}

		$nodeXml .= "</url>";

		return $nodeXml;
	}

	/**
	 * Get the XML header
	 *
	 * @return string
	 */
	private function getSitemapHead() {
		return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
	}

	/**
	 * Get the XML footer
	 *
	 * @return string
	 */
	private function getSitemapFoot() {
		return "</urlset>\n";
	}

	/**
	 *Set the dir to save to
	 *
	 * @param string $dir
	 */
	public function setSaveDir($dir) {
		$this->dir = $dir;
	}

	/**
	 * Write the sitemap to a file.  The file is not complete until finalizeFile() is run.
	 */
	public function saveToFile() {
		$nodeData = $this->getNode();
		$fp = $this->getSaveFilePointer();

		// If there aren't any nodes and the file is still empty (ie, writing an empty sitemap)
		if (!$nodeData && $this->openFileSize == 0) {
			// Write the header anyways
			$this->openFileSize = fputs($fp, $this->getSitemapHead());
		}

		while ($nodeData) {
			// If we haven't written anything to this file yet, add the sitemap header.
			if($this->openFileSize == 0) {
				fputs($fp, $this->getSitemapHead());
			}
			fputs($fp, $this->renderNode($nodeData)."\n");
			$this->fileUrlCount++;
			$this->openFileSize = ftell($fp);

			$nodeData = $this->getNode();
			$fp = $this->getSaveFilePointer();
		}
	}

	/**
	 * Returns a copy of the save file pointer.  Automatically switches to a new file
	 *
	 * @return resource
	 */
	private function getSaveFilePointer() {
		// Init the file number if needed
		if(empty($this->fileNumber[$this->fname])) {
			$this->fileNumber[$this->fname] = 1;
		}

		// Check whether we need to close an open file and start a new one
		if($this->openFileSize >= self::MAX_SITEMAP_FILE_SIZE || $this->fileUrlCount >= self::MAX_SITEMAP_URL_COUNT) {
			if($this->saveFp) {
				fclose($this->saveFp);
				$this->saveFp = false;
			}
			$this->finalizeFile();
			$this->fileNumber[$this->fname]++;
		}

		// If we already have a file pointer, return it
		if(!empty($this->saveFp)) {
			return $this->saveFp;
		}

		$this->saveFp = fopen($this->getSaveFileName(), 'w');
		$this->openFileSize = 0;
		$this->fileUrlCount	= 0;
		if(!$this->saveFp) {
			trigger_error("Could not open file {$this->fname} for writing!", E_USER_ERROR);
		}

		return $this->saveFp;
	}

	/**
	 * Generates the file name for the XML output
	 *
	 * @return string
	 */
	private function getSaveFileName() {
		if(empty($this->fname)) {
			trigger_error("File name is empty!", E_USER_ERROR);
		}
		$fname = $this->dir . escapeshellcmd($this->fname) . '-' . (int)$this->fileNumber[$this->fname] . '.xml';

		$dir = dirname($fname);
		if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
			trigger_error('Cannot create file path', E_USER_ERROR);
		}

		return $fname;
	}

	/**
	 * Generates the file name for the XML output
	 *
	 * @return string
	 */
	private function getIndexFileName($fname) {
		return $this->dir . escapeshellcmd($fname) . 'index.xml';
	}

	/**
	 * Write the sitemap footer
	 */
	public function finalizeFile() {
		$fileName = $this->getSaveFileName();
		$fp = fopen($fileName, 'a+');
		if(!$fp) {
			trigger_error("Could not open file " . $this->getSaveFileName() . " for writing!", E_USER_ERROR);
		}
		fputs($fp, $this->getSitemapFoot());
		fclose($fp);

		// Compress the sitemap file
		`gzip -f {$fileName}`;
		$this->openFileSize = 0;
		$this->saveFp = false;
	}

	/**
	 * Generate the sitemaps index file.
	 * Sitemap files require a fully formed URL.
	 * This should be placed in the site root or as close as possible because a
	 * sitemaps file cannot contain links for any paths that are not contained in that dir.
	 *
	 * @param string $fileNameBase
	 * @param string $urlBase Sitemap files require a fully formed URL.  This should be placed in the site root or as
	 * close as possible because a sitemaps file cannot contain links for any paths that are not contained in that dir.
	 */
	public function createIndex($fileNameBase, $urlBase) {
		$index = '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
		$dtObj = new core_dateTime();
		$modDate = $dtObj->getDate();
		foreach($this->fileNumber as $fname => $max) {
			for($i = 1; $i <= $max; $i++) {
				$index .= "<sitemap><loc>{$urlBase}{$fname}-{$i}.xml.gz</loc><lastmod>{$modDate}</lastmod></sitemap>\n";
			}
		}
		$index .= '</sitemapindex>';
		file_put_contents($this->getIndexFileName($fileNameBase), $index);
	}

	/**
	 * Validate a url to ensure it conforms to the sitemaps protocol
	 *
	 * @param string $url
	 * @return bool valid
	 */
	private function validateUrl($url) {
		if(strpos($url,'http://') === 0 || strpos($url,'https://') === 0) {
			return true;
		}
		trigger_error("URL $url is invalid", E_USER_WARNING);

		return false;
	}

	/**
	 * Validate the priority setting for a given sitemaps URL
	 *
	 * @param float $priority
	 * @return bool valid
	 */
	private function validatePriority($priority) {
		if(empty($priority) || ($priority <= 1 AND $priority >= 0 )) {
			return true;
		}
		trigger_error("Priority $priority is invalid for sitemaps", E_USER_WARNING);

		return false;
	}

	/**
	 * Validate the change frequency
	 *
	 * @param const $changeFreq
	 * @return bool valid
	 */
	private function validateChangeFreq($changeFreq) {
		if(empty($changeFreq)
			|| $changeFreq == self::FREQUENCY_ALWAYS
			|| $changeFreq == self::FREQUENCY_HOURLY
			|| $changeFreq == self::FREQUENCY_DAILY
			|| $changeFreq == self::FREQUENCY_WEEKLY
			|| $changeFreq == self::FREQUENCY_MONTHLY
			|| $changeFreq == self::FREQUENCY_YEARLY
			|| $changeFreq == self::FREQUENCY_NEVER)
		{
			return true;
		}
		trigger_error("Change frequency $changeFreq is invalid for sitemaps", E_USER_WARNING);

		return false;
	}

	/**
	 * Store a node in a magical box that doesn't live in memory
	 *
	 * @param array $nodeData
	 */
	private function setNode($nodeData) {
		if(!$this->tmpFp) {
			$this->openTempFile();
		}

		fseek($this->tmpFp, $this->tmpCaretWrite);

		// Pack away the node data, stripping assoc array data.
		// Not strictly required, but it ensures the unpack gets the data in the right order.
		$csvArr = array(
			$nodeData['url'],
			$nodeData['lastModified'],
			$nodeData['priority'],
			$nodeData['changeFreq']
			);

		fputcsv($this->tmpFp,$csvArr);
		$this->tmpCaretWrite = ftell($this->tmpFp);
	}

	/**
	 * Retrieve a node from a magical box that doesn't live in memory
	 */
	private function getNode() {
		// Check for a difference in read/write buffer carets.
		// If there is none then don't bother reading.
		if($this->tmpCaretRead == $this->tmpCaretWrite) {
			return;
		}

		// Open the file if needed.
		if(!$this->tmpFp) {
			$this->openTempFile();
		}

		// Jump to the proper part of the file and read the next line
		fseek($this->tmpFp, $this->tmpCaretRead);
		$csvArr = fgetcsv($this->tmpFp);
		if(empty($csvArr)) {
			return;
		}

		// Unpack the node data into an assoc array
		$nodeData = array(
			'url' => $csvArr[0],
			'lastModified' => $csvArr[1],
			'priority' => $csvArr[2],
			'changeFreq' => $csvArr[3]
		);
		$this->tmpCaretRead = ftell($this->tmpFp);

		// If we have read everything that has been written, close and delete the file.
		if($this->tmpCaretRead == $this->tmpCaretWrite && $this->tmpCaretRead > 0) {
			$this->closeTempFile();
		}

		return $nodeData;
	}

	/**
	 * A work stub for future functionality
	 */
	public function notifySearchEngines() {
		trigger_error("Method not yet implemented.", E_USER_ERROR);
		/*
		To submit your Sitemap using an HTTP request (replace <searchengine_URL> with the URL provided by the search engine),
		issue your request to the following URL:
		<searchengine_URL>/ping?sitemap=sitemap_url

		For example, if your Sitemap is located at http://www.example.com/sitemap.gz, your URL will become:
		<searchengine_URL>/ping?sitemap=http://www.example.com/sitemap.gz

		URL encode everything after the /ping?sitemap=:
		<searchengine_URL>/ping?sitemap=http%3A%2F%2Fwww.yoursite.com%2Fsitemap.gz

		You can issue the HTTP request using wget, curl, or another mechanism of your choosing. A successful request will return an HTTP 200 response code; if you receive a different response, you should resubmit your request. The HTTP 200 response code only indicates that the search engine has received your Sitemap, not that the Sitemap itself or the URLs contained in it were valid. An easy way to do this is to set up an automated job to generate and submit Sitemaps on a regular basis.
		Note: If you are providing a Sitemap index file, you only need to issue one HTTP request that includes the location of the Sitemap index file; you do not need to issue individual requests for each Sitemap listed in the index.
		 */
	}
}
