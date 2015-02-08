<?php
namespace ApacheSolrForTypo3\Tika\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * A Tika service implementation using the tika-app.jar
 *
 * @package ApacheSolrForTypo3\Tika\Service
 */
class JarService extends AbstractTikaService {

	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		parent::__construct();

		if (!is_file(GeneralUtility::getFileAbsFileName($this->configuration['tikaPath'], FALSE))) {
			throw new \RuntimeException(
				'Invalid path or filename for Tika application jar.',
				1266864929
			);
		}
	}

	/**
	 * Takes a file reference and extracts the text from it.
	 *
	 * @param \TYPO3\CMS\Core\Resource\File $file
	 * @return string
	 */
	public function extractText(File $file) {
		$tikaCommand = CommandUtility::getCommand('java')
			. ' -Dfile.encoding=UTF8' // forces UTF8 output
			. ' -jar ' . escapeshellarg(GeneralUtility::getFileAbsFileName($this->configuration['tikaPath'], FALSE))
			. ' -t'
			. ' ' . escapeshellarg($file);

		$extractedText = shell_exec($tikaCommand);

		$this->log('Text Extraction using local Tika', array(
			'file'         => $file,
			'tika command' => $tikaCommand,
			'shell output' => $extractedText
		));

		return $extractedText;
	}

	/**
	 * Takes a file reference and extracts its meta data.
	 *
	 * @param \TYPO3\CMS\Core\Resource\File $file
	 * @return array
	 */
	public function extractMetaDate(File $file) {
		$tikaCommand = CommandUtility::getCommand('java')
			. ' -Dfile.encoding=UTF8'
			. ' -jar ' . escapeshellarg(GeneralUtility::getFileAbsFileName($this->configuration['tikaPath'], FALSE))
			. ' -m'
			. ' ' . escapeshellarg($file);

		$shellOutput = array();
		exec($tikaCommand, $shellOutput);
		$metaData = $this->shellOutputToArray($shellOutput);

		$this->log('Meta Data Extraction using local Tika', array(
			'file' => $file,
			'tika command' => $tikaCommand,
			'shell output' => $shellOutput,
			'meta data' => $metaData
		));

		return $metaData;
	}

	/**
	 * Takes a file reference and detects its content's language.
	 *
	 * @param \TYPO3\CMS\Core\Resource\File $file
	 * @return string Language ISO code
	 */
	public function detectLanguageFromFile(File $file) {
		// TODO: Implement detectLanguageFromFile() method.
	}

	/**
	 * Takes a string as input and detects its language.
	 *
	 * @param string $input
	 * @return string Language ISO code
	 */
	public function detectLanguageFromString($input) {
		// TODO: Implement detectLanguageFromString() method.
	}


	/**
	 * Takes shell output from exec() and turns it into an array of key => value
	 * pairs.
	 *
	 * @param array $shellOutput An array containing shell output from exec() with one line per entry
	 * @return array Key => value pairs
	 */
	protected function shellOutputToArray(array $shellOutput) {
		$metaData = array();

		foreach ($shellOutput as $line) {
			list($key, $value) = explode(':', $line, 2);
			$value = trim($value);

			if (in_array($key, array('dc', 'dcterms', 'meta', 'tiff', 'xmp', 'xmpTPg'))) {
				// Dublin Core metadata and co
				$keyPrefix = $key;
				list($key, $value) = explode(':', $value, 2);

				$key   = $keyPrefix . ':' . $key;
				$value = trim($value);
			}

			if (array_key_exists($key, $metaData)) {
				if ($metaData[$key] == $value) {
					// first duplicate key hit, but also duplicate value
					continue;
				}

				// allow a meta data key to appear multiple times
				if (!is_array($metaData[$key])) {
					$metaData[$key] = array($metaData[$key]);
				}

				// but do not allow duplicate values
				if (!in_array($value, $metaData[$key])) {
					$metaData[$key][] = $value;
				}
			} else {
				$metaData[$key] = $value;
			}
		}

		return $metaData;
	}
}