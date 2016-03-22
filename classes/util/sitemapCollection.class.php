<?php

/**
 * Generate multiple XML sitemaps using util_sitemap
 *
 * @see util_sitemap
 *
 * @package framework
 * @subpackage util
 */

class util_sitemapCollection extends util_sitemap {

	private $defaultS3Bucket;
	private $defaultS3BucketSaveDir;
	private $defaultSaveDir;

	// sitemap name => array('finalized' => bool, 'sitemap' => util_sitemap)
	private $sitemaps = array();

	// a *reference* to a subarray of $sitemaps
	private $workingSitemap = array();

	/**
	 * Construct a sitemap collection
	 */
	public function __construct() { }

	/**
	 * Clean up any temp files or directories that are left
	 */
	public function __destruct() {
		// pre-upload directory
		if ($this->defaultS3Bucket && is_dir($this->defaultSaveDir)) {
			$diriter = new RecursiveDirectoryIterator($this->defaultSaveDir);
			$iteriter = new RecursiveIteratorIterator($diriter, RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($iteriter as $file) {
				if (in_array(basename($file), array('.', '..'))) {
					continue;
				}

				if (is_dir($file)) {
					rmdir($file);
				} else {
					unlink($file);
				}
			}

			rmdir($this->defaultSaveDir);
		}
	}

	/**
	 * (Deprecated: use addNodeTo) Create an individual sitemap entry
	 *
	 * @param string $url
	 * @param float $priority
	 * @param const $changeFreq
	 * @param core_dateTime $lastModified
	 * @return bool success
	 */
	public function addNode($url, $priority = false, $changeFreq = false, core_dateTime $lastModified = null) {
		if (!$this->workingSitemap) {
			trigger_error('No working sitemap chosen', E_USER_WARNING);
			return false;
		}

		return $this->workingSitemap['sitemap']->addNode($url, $priority, $changeFreq, $lastModified);
	}

	/**
	 * Add a node to a new or existing sitemap
	 *
	 * @param string $filename
	 * @param string $url
	 * @param float $priority
	 * @param const $changeFreq
	 * @param core_dateTime $lastModified
	 * @return bool
	 */
	public function addNodeTo($filename, $url, $priority = false, $changeFreq = false, core_dateTime $lastModified = null) {
		$sitemap = $this->getSitemap($filename);
		return $sitemap['sitemap']->addNode($url, $priority, $changeFreq, $lastModified);
	}

	/**
	 * Generate the sitemaps index file for all the collected sitemaps
	 *
	 * @param string $fileNameBase
	 * @param string $urlBase
	 */
	public function createIndex($fileNameBase, $urlBase) {
		$index = "<?xml version='1.0' encoding='utf-8'?>\n<sitemapindex xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'>\n";
		$dtObj = new core_dateTime();
		$modDate = $dtObj->getDate();
		foreach ($this->sitemaps as $sitemap) {
			foreach ($sitemap['sitemap']->fileNumber as $fname => $max) {
				for ($i = 1; $i <= $max; $i++) {
					$index .= "<sitemap><loc>{$urlBase}{$fname}-{$i}.xml.gz</loc><lastmod>{$modDate}</lastmod></sitemap>\n";
				}
			}
		}
		$index .= '</sitemapindex>';

		if ($this->defaultS3Bucket) {
			//echo "\nTrying to upload to S3: bucket={$this->defaultS3Bucket}, file=";
			//echo $this->defaultS3BucketSaveDir . $fileNameBase . "index.xml\n";
			///*
			$s3 = new AmazonS3();
			$response = $s3->create_object($this->defaultS3Bucket, $this->defaultS3BucketSaveDir . $fileNameBase . 'index.xml', array(
				'acl' => AmazonS3::ACL_PUBLIC,
				'body' => $index,
				'contentType' => 'application/xml',
                                'meta' => ['X-Robots-Tag:' => 'noindex']
			));
			if (!$response->isOK()) {
				trigger_error('Could not upload index to S3', E_USER_WARNING);
			}
			//*/
		} else {
			// make sure the full directory hierarchy exists
			$prefix = $this->defaultSaveDir . escapeshellcmd($fileNameBase);
			$dir = dirname($prefix);
			if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
				trigger_error("Cannot create index file path {$dir}", E_USER_ERROR);
			}

			file_put_contents($prefix . 'index.xml', $index);
		}
	}

	/**
	 * Finalize all sitemaps
	 */
	public function finalizeAllFiles() {
		foreach ($this->sitemaps as &$sitemap) {
			if (!$sitemap['finalized']) {
				$sitemap['sitemap']->saveToFile();
				$sitemap['sitemap']->finalizeFile();
				$sitemap['finalized'] = true;

				$this->maybeUploadSitemap($sitemap['sitemap']);
			}
		}
	}

	/**
	 * Write the working sitemap footer
	 */
	public function finalizeFile() {
		if (!$this->workingSitemap) {
			trigger_error('No working sitemap chosen', E_USER_WARNING);
			return;
		}
		if (!$this->workingSitemap['finalized']) {
			$this->workingSitemap['sitemap']->saveToFile();
			$this->workingSitemap['sitemap']->finalizeFile();
			$this->workingSitemap['finalized'] = true;

			$this->maybeUploadSitemap($this->workingSitemap['sitemap']);
		}
	}

	/**
	 * Get or create a sitemap for a filename
	 *
	 * @param string $fileName
	 * @return util_sitemap
	 */
	private function &getSitemap($fileName) {
		if (!isset($this->sitemaps[$fileName])) {
			$this->sitemaps[$fileName] = array(
				'finalized' => false,
				'sitemap' => new util_sitemap()
			);
			$this->sitemaps[$fileName]['sitemap']->setFileName($fileName);
			if ($this->defaultSaveDir) {
				$this->sitemaps[$fileName]['sitemap']->setSaveDir($this->defaultSaveDir);
			}
		}
		return $this->sitemaps[$fileName];
	}

	/**
	 * If requested, upload a sitemap to S3
	 *
	 * @param util_sitemap $sitemap
	 */
	private function maybeUploadSitemap(util_sitemap $sitemap) {
		if (empty($this->defaultS3Bucket)) {
			return;
		}

		foreach ($sitemap->fileNumber as $fname => $max) {
			for ($i = 1; $i <= $max; $i++) {
				$file = "{$this->defaultSaveDir}/{$fname}-{$i}.xml.gz";
				if (!is_file($file)) {
					trigger_error("Sitemap file {$file} does not exist yet - has the sitemap been finalized?", E_USER_WARNING);
					continue;
				}

				//echo "\nTrying to upload to S3: bucket={$this->defaultS3Bucket}, file=";
				//echo $this->defaultS3BucketSaveDir . basename($file) . "\n";
				///*
				$s3 = new AmazonS3();
				$response = $s3->create_object($this->defaultS3Bucket, $this->defaultS3BucketSaveDir . basename($file), array(
					'acl' => AmazonS3::ACL_PUBLIC,
					'contentType' => 'application/gzip',
					'fileUpload' => $file
				));
				if ($response->isOK()) {
					unlink($file);
				}
				//*/
			}
		}
	}

	/**
	 * A work stub for future functionality
	 *
	 * @see util_sitemap::notifySearchEngines
	 */
	public function notifySearchEngines() {
		trigger_error('Method not yet implemented', E_USER_ERROR);
	}

	/**
	 * Write the working sitemap to a file. The file is not complete until finalizeFile() or finalizeAllFiles() is run.
	 */
	public function saveToFile() {
		if (!$this->workingSitemap) {
			trigger_error('No working sitemap chosen', E_USER_WARNING);
			return;
		}
		$this->workingSitemap['sitemap']->saveToFile();
	}

	/**
	 * Set the output file names
	 *
	 * @param string $fileNameBase
	 */
	public function setFileName($fileNameBase) {
		$this->workingSitemap =& $this->getSitemap($fileNameBase);
	}

	/**
	 * Set the AWS S3 bucket to upload finalized files to
	 *
	 * @param string $bucket
	 * @param string $dir Optional path inside the bucket
	 */
	public function setS3Bucket($bucket, $dir = '') {
		$tempdir = sys_get_temp_dir() . '/sitemap-preupload-' . uniqid();
		mkdir($tempdir, 0700);

		$this->defaultS3Bucket = $bucket;
		$this->defaultS3BucketSaveDir = trim($dir, '\\/') . '/';
		$this->defaultSaveDir = $tempdir . '/';

		foreach ($this->sitemaps as $sitemap) {
			$sitemap['sitemap']->setSaveDir($tempdir);
		}
	}

	/**
	 * Set the dir to save to
	 *
	 * @param string $dir
	 */
	public function setSaveDir($dir) {
		$this->defaultS3Bucket = null;
		$this->defaultSaveDir = $dir;

		foreach ($this->sitemaps as $sitemap) {
			$sitemap['sitemap']->setSaveDir($dir);
		}
	}

}
