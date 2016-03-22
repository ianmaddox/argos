<?php
/**
 * String utility class
 *
 * To be used statically.
 *
 * @package framework
 * @subpackage util
 */
class util_string {
	/**
	 * Used by ucwords: Exceptions to the rule of uppercasing the first letter of every word.
	 *
	 * @var array $aSmallWords
	 */
	private static $aSmallWords = array ('a', 'and', 'her', 'his', 'in', 'into', 'my', 'of', 'or', 'our', 'the', 'their', 'your');

	/**
	 * Method constructor.  Sole action is to throw an exception if this object is
	 * instantiated instead of being called statically.
	 */
	public function __construct()
	{
		trigger_error('Cannot instantiate static class', E_USER_ERROR);
	}

	/**
	 * This title-cases a string. Title-casing capitalizes the first letter of
	 * the first word, plus the first letter of each word that's not a preposition,
	 * pronoun, (or maybe other housekeeping) word.
	 *
	 * @param string $rawString The raw word or phrase to transform.
	 * @return string $retString The input string, properly cased as for a title.
	 */
	public static function makeTitleCase($rawString)
	{
		$aWords = explode(' ', $rawString);

		// Capitalize the first word.
		$aWords[0] = ucwords($aWords[0]);

		// Capitalize every other word that's not in self::$aSmallWords.
		$aWords[count($aWords) - 1] = ucwords($aWords[count($aWords) - 1]);
		for ($ix=1; $ix < count($aWords) - 1; $ix++) {
			if (!in_array($aWords[$ix], self::$aSmallWords)) {
				$aWords[$ix] = ucwords($aWords[$ix]);
			}
		}

		$retString = implode(' ', $aWords);

		return $retString;
	}

	/**
	 * Generates the correct singular/plural form for a number's units.
	 * @example print "$nMsgs "     . util_string::plural($nMsgs,     "answer");
	 * @example print "$nEntities " . util_string::plural($nEntities, "entity", "entities");
	 *
	 * @param integer $value The numeric value.
	 * @param string $sUnit The unit to display if the value is 1.
	 * @param string $sUnits The unit to display if the value is plural.
	 * @return string $return The proper displayable form for the number's units.
	 */
	public static function plural ($value, $sUnit, $sUnits="")
	{
		if($value == 1) {
			$return = $sUnit;
		} else {
			$return = ($sUnits ? $sUnits : $sUnit . "s");
		}
		return $return;
	}

	/**
	 * Converts a string to UTF8 safe
	 * @param string $string The string to convert
	 * @param boolean $convertAccents Convert accented characters to unaccented
	 * @return $string
	 */
	public static function utf8Safe($string, $convertAccents = true) {
		// ensure it's actually utf-8
		// add a space because mb_detect_encoding() allows partial sequences
		if(!mb_detect_encoding($string . ' ', 'UTF-8,auto')) {
			$string = iconv('ISO-8859-1', 'UTF-8//IGNORE', $string);
		}

		// entities
		$string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
		if($convertAccents) {
			$string = self::transliterate($string);
		}
		return $string;
	}


	public static function oxfordComma(array $input) {
		if(empty($input)) {
			return '';
		}
		//clean up input
		$clean = array_filter($input, create_function('$val', 'return !empty($val);'));
		$output = $clean[0];
		switch (count($clean)) {
			case 1:
				break;
			case 2:
				$output .= ' and ' . $clean[1];
				break;
			default:
				for($n=1; $n<count($clean); $n++) {
					$output .= ((!empty($output) && !empty($clean[$n])) ? ', ' : '') .
								((!empty($output) && $n == count($clean)-1) ? 'and ' : '') .
								$clean[$n];
				}
				break;
		}
		return $output;
	}

	/**
	 * Create a sortable string
	 *
	 * @param string $string
	 * @return string
	 */
	public static function sortable($string) {
		$newstring = $string;

		// first merge acronym letters
		$newstring = preg_replace_callback('/(^|[ _()])(([A-Z]\.)+)([ _()]|$)/', function($matches) {
			return $matches[1] . str_replace('.', '', $matches[2]) . $matches[4];
		}, $newstring);

		$suffixes = '';
		// suffixes
		$newstring = preg_replace_callback('/ \([^)]+\)$/', function($matches) use (&$suffixes) {
			$suffixes = $matches[0] . $suffixes;
			return '';
		}, $newstring);

		// prefixes
		// ^a has to be handled specially because it could be an initial
		if(preg_match('/^a /i', $newstring) && !preg_match('/^a\./i', $string)) {
			$newstring = substr($newstring, 2) . ', ' . $newstring[0];
		}
		// other multiple-letter prefixes should be fine
		if(preg_match('/^((an|the) )+/i', $newstring, $matches)) {
			$newstring = substr($newstring, strlen($matches[1])) . ', ' . rtrim($matches[1]);
		}

		return self::transliterate($newstring . $suffixes);
	}

	/**
	 * Create a sortable author name
	 *
	 * @param string $author
	 * @return string
	 */
	public static function sortableAuthor($author) {
		$author = self::utf8Safe($author, $true);
		$newauthor = $author;

		// strip some suffixes
		$newauthor = preg_replace('/ \([^)]+\)$/', '', $newauthor); // disambiguations
		$newauthor = preg_replace('/, (1st|2nd|3rd|[4-9]th|baron) .*/i', '', $newauthor); // titles

		$suffixes = '';
		// keep others
		$newauthor = preg_replace_callback('/,? (jr|sr|v?i+)$/i', function($matches) use (&$suffixes) {
			$suffixes = ', ' . $matches[1] . $suffixes;
			return '';
		}, $newauthor);

		// of the remaining characters [a-z0-9,() -] we don't want any more parentheses
		$newauthor = strtr($newauthor, array(
			' ( ' => ' ',
			' ) ' => ' ',
			'(' => '',
			')' => ''
		));

		// names
		$matches = array();
		if(preg_match('/^([^ ,]+( (?!and )[^ ,]+)*)(.*)/', $newauthor, $matches)) {
			$parts = explode(' ', $matches[1]);
			$split = count($parts) - 1;

			// names could be like "Name Name word Name" - include the "word" in the
			// last name
			while($split > 0 && (
				// lowercase letter
				$parts[$split - 1][0] >= 'a' && $parts[$split - 1][0] <= 'z'
				// or it's one of these words
				|| in_array(strtolower($parts[$split - 1]), array('de', 'le', 'van', 'von')))
			) {
				// "A B word C" that should be broken up as "B word C, A"
				if(in_array(strtolower($parts[$split - 1]), array('from', 'of'))) {
					$split--; // skip another word
				}
				$split--;
			}

			if($split > 0) {
				$newauthor = implode(' ', array_slice($parts, $split)) . ', ' . implode(' ', array_slice($parts, 0, $split));
			} else {
				$newauthor = implode(' ', $parts);
			}
			$newauthor .= $matches[3]; // whatever was remaining
		}

		return $newauthor . $suffixes;
	}

	/**
	 * Convert a string to a searchable variant
	 * @param string $term
	 * @return string
	 */
	public static function searchable($term) {
		$term = self::transliterate($term);
		$term = strtolower($term);
		$term = preg_replace('[^a-z0-9]', '', $term);
		return $term;
	}

	/**
	 * Convert a author name string to a searchable variant
	 * @param strung $name
	 * @return string
	 */
	public static function searchableAuthor($name) {
		$name = self::transliterate($name);
		$name = strtolower($name);
		$name = preg_replace('/[^\w]/', '', $name);
		return $name;
	}

	/**
	 * Convert all accented characters into their non-accented counterparts.
	 * @param type $string
	 * @return type
	 */
	public static function transliterate($string) {
		// unusual characters, starting with ones iconv() doesn't recognize
		$string = strtr($string, array(
			"\xC3\xB8" => 'o' // o with stroke
		));
		setlocale(LC_CTYPE, 'en_US.UTF-8');
		$string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
		return $string;
	}
}
