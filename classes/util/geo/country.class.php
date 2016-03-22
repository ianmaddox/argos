<?php
/**
 * This class provides country-specific geo functionality.
 *
 * @package framework
 * @subpackage util_geo
 */
class util_geo_country {
	const POSTALCODE_NONE = false;
	const POSTALCODE_ZIP = 'zip';
	const POSTALCODE_POSTALCODE = 'postalcode';

	const REGION_NONE = false;
	const REGION_STATE = 'state';
	const REGION_PROVINCE = 'province';

	private $icao;

	private $full2icao = array(
		'Afghanistan' => 'AF',
		'Albania' => 'AL',
		'Algeria' => 'DZ',
		'Andorra' => 'AD',
		'Angola' => 'AO',
		'Antigua & Barbuda' => 'AG',
		'Argentina' => 'AR',
		'Armenia' => 'AM',
		'Australia' => 'AU',
		'Austria' => 'AT',
		'Azerbaijan' => 'AZ',
		'Bahamas' => 'BS',
		'Bahrain' => 'BH',
		'Bangladesh' => 'BD',
		'Barbados' => 'BB',
		'Belarus' => 'BY',
		'Belgium' => 'BE',
		'Belize' => 'BZ',
		'Benin' => 'BJ',
		'Bhutan' => 'BT',
		'Bolivia' => 'BO',
		'Bosnia-Herzegovina' => 'BA',
		'Botswana' => 'BW',
		'Brazil' => 'BR',
		'Brunei Darussalam' => 'BN',
		'Bulgaria' => 'BG',
		'Burkina Faso' => 'BF',
		'Burundi' => 'BI',
		'Cambodia' => 'KH',
		'Cameroon' => 'CM',
		'Canada' => 'CA',
		'Cape Verde' => 'CV',
		'Central African Republic' => 'CF',
		'Chad' => 'TD',
		'Chile' => 'CL',
		'China' => 'CN',
		'Colombia' => 'CO',
		'Comoros' => 'KM',
		'Congo' => 'CG',
		'Cook Islands' => 'CK',
		'Costa Rica' => 'CR',
		'Croatia' => 'HR',
		'Cuba' => 'CU',
		'Cyprus' => 'CY',
		'Czech Republic' => 'CZ',
		'Côte d\'Ivoire' => 'CI',
		'Dem. Rep. of the Congo' => 'CD',
		'Denmark' => 'DK',
		'Djibouti' => 'DJ',
		'Dominica' => 'DM',
		'Dominican Republic' => 'DO',
		'Ecuador' => 'EC',
		'Egypt' => 'EG',
		'El Salvador' => 'SV',
		'Equatorial Guinea' => 'GQ',
		'Eritrea' => 'ER',
		'Estonia' => 'EE',
		'Ethiopia' => 'ET',
		'Fiji' => 'FJ',
		'Finland' => 'FI',
		'France' => 'FR',
		'Gabon' => 'GA',
		'Gambia' => 'GM',
		'Georgia' => 'GE',
		'Germany' => 'DE',
		'Ghana' => 'GH',
		'Greece' => 'GR',
		'Grenada' => 'GD',
		'Guatemala' => 'GT',
		'Guinea' => 'GN',
		'Guinea-Bissau' => 'GW',
		'Guyana' => 'GY',
		'Haiti' => 'HT',
		'Honduras' => 'HN',
		'Hungary' => 'HU',
		'Iceland' => 'IS',
		'India' => 'IN',
		'Indonesia' => 'ID',
		'Iran' => 'IR',
		'Iraq' => 'IQ',
		'Ireland' => 'IE',
		'Israel' => 'IL',
		'Italy' => 'IT',
		'Jamaica' => 'JM',
		'Japan' => 'JP',
		'Jordan' => 'JO',
		'Kazakhstan' => 'KZ',
		'Kenya' => 'KE',
		'Kiribati' => 'KI',
		'Korea, North' => 'KP',
		'Korea, South' => 'KR',
		'Kuwait' => 'KW',
		'Kyrgyzstan' => 'KG',
		'Laos' => 'LA',
		'Latvia' => 'LV',
		'Lebanon' => 'LB',
		'Lesotho' => 'LS',
		'Liberia' => 'LR',
		'Libya' => 'LY',
		'Liechtenstein' => 'LI',
		'Lithuania' => 'LT',
		'Luxembourg' => 'LU',
		'Macedonia' => 'MK',
		'Madagascar' => 'MG',
		'Malawi' => 'MW',
		'Malaysia' => 'MY',
		'Maldives' => 'MV',
		'Mali' => 'ML',
		'Malta' => 'MT',
		'Marshall Islands' => 'MH',
		'Mauritania' => 'MR',
		'Mauritius' => 'MU',
		'Mexico' => 'MX',
		'Micronesia' => 'FM',
		'Moldova' => 'MD',
		'Monaco' => 'MC',
		'Mongolia' => 'MN',
		'Montenegro' => 'ME',
		'Morocco' => 'MA',
		'Mozambique' => 'MZ',
		'Myanmar' => 'MM',
		'Namibia' => 'NA',
		'Nauru' => 'NR',
		'Nepal' => 'NP',
		'Netherlands' => 'NL',
		'New Zealand' => 'NZ',
		'Nicaragua' => 'NI',
		'Niger' => 'NE',
		'Nigeria' => 'NG',
		'Norway' => 'NO',
		'Oman' => 'OM',
		'Pakistan' => 'PK',
		'Palau' => 'PW',
		'Panama' => 'PA',
		'Papua New Guinea' => 'PG',
		'Paraguay' => 'PY',
		'Peru' => 'PE',
		'Philippines' => 'PH',
		'Poland' => 'PL',
		'Portugal' => 'PT',
		'Qatar' => 'QA',
		'Romania' => 'RO',
		'Russian Federation' => 'RU',
		'Rwanda' => 'RW',
		'Saint Kitts & Nevis' => 'KN',
		'Saint Lucia' => 'LC',
		'Saint Vincent & the Grenadines' => 'VC',
		'Samoa' => 'WS',
		'San Marino' => 'SM',
		'Sao Tome & Principe' => 'ST',
		'Saudi Arabia' => 'SA',
		'Senegal' => 'SN',
		'Serbia' => 'RS',
		'Seychelles' => 'SC',
		'Sierra Leone' => 'SL',
		'Singapore' => 'SG',
		'Slovak Republic' => 'SK',
		'Slovenia' => 'SI',
		'Solomon Islands' => 'SB',
		'Somalia' => 'SO',
		'South Africa' => 'ZA',
		'Spain' => 'ES',
		'Sri Lanka' => 'LK',
		'Sudan' => 'SD',
		'Suriname' => 'SR',
		'Swaziland' => 'SZ',
		'Sweden' => 'SE',
		'Switzerland' => 'CH',
		'Syrian Arab Republic' => 'SY',
		'Tajikistan' => 'TJ',
		'Thailand' => 'TH',
		'Timor-Leste' => 'TL',
		'Togo' => 'TG',
		'Tonga' => 'TO',
		'Trinidad & Tobago' => 'TT',
		'Tunisia' => 'TN',
		'Turkey' => 'TR',
		'Turkmenistan' => 'TM',
		'Tuvalu' => 'TV',
		'Uganda' => 'UG',
		'Ukraine' => 'UA',
		'United Arab Emirates' => 'AE',
		'United Kingdom' => 'GB',
		'United Rep. of Tanzania' => 'TZ',
		'United States' => 'US',
		'Uruguay' => 'UY',
		'Uzbekistan' => 'UZ',
		'Vanuatu' => 'VU',
		'Vatican' => 'VA',
		'Venezuela' => 'VE',
		'Viet Nam' => 'VN',
		'Yemen' => 'YE',
		'Zambia' => 'ZM',
		'Zimbabwe' => 'ZW'
	);

	private $icao2full = array(
		'AD' => 'Andorra',
		'AE' => 'United Arab Emirates',
		'AF' => 'Afghanistan',
		'AG' => 'Antigua & Barbuda',
		'AL' => 'Albania',
		'AM' => 'Armenia',
		'AO' => 'Angola',
		'AR' => 'Argentina',
		'AT' => 'Austria',
		'AU' => 'Australia',
		'AZ' => 'Azerbaijan',
		'BA' => 'Bosnia-Herzegovina',
		'BB' => 'Barbados',
		'BD' => 'Bangladesh',
		'BE' => 'Belgium',
		'BF' => 'Burkina Faso',
		'BG' => 'Bulgaria',
		'BH' => 'Bahrain',
		'BI' => 'Burundi',
		'BJ' => 'Benin',
		'BN' => 'Brunei Darussalam',
		'BO' => 'Bolivia',
		'BR' => 'Brazil',
		'BS' => 'Bahamas',
		'BT' => 'Bhutan',
		'BW' => 'Botswana',
		'BY' => 'Belarus',
		'BZ' => 'Belize',
		'CA' => 'Canada',
		'CD' => 'Dem. Rep. of the Congo',
		'CF' => 'Central African Republic',
		'CG' => 'Congo',
		'CH' => 'Switzerland',
		'CI' => 'Côte d\'Ivoire',
		'CK' => 'Cook Islands',
		'CL' => 'Chile',
		'CM' => 'Cameroon',
		'CN' => 'China',
		'CO' => 'Colombia',
		'CR' => 'Costa Rica',
		'CU' => 'Cuba',
		'CV' => 'Cape Verde',
		'CY' => 'Cyprus',
		'CZ' => 'Czech Republic',
		'DE' => 'Germany',
		'DJ' => 'Djibouti',
		'DK' => 'Denmark',
		'DM' => 'Dominica',
		'DO' => 'Dominican Republic',
		'DZ' => 'Algeria',
		'EC' => 'Ecuador',
		'EE' => 'Estonia',
		'EG' => 'Egypt',
		'ER' => 'Eritrea',
		'ES' => 'Spain',
		'ET' => 'Ethiopia',
		'FI' => 'Finland',
		'FJ' => 'Fiji',
		'FM' => 'Micronesia',
		'FR' => 'France',
		'GA' => 'Gabon',
		'GB' => 'United Kingdom',
		'GD' => 'Grenada',
		'GE' => 'Georgia',
		'GH' => 'Ghana',
		'GM' => 'Gambia',
		'GN' => 'Guinea',
		'GQ' => 'Equatorial Guinea',
		'GR' => 'Greece',
		'GT' => 'Guatemala',
		'GW' => 'Guinea-Bissau',
		'GY' => 'Guyana',
		'HN' => 'Honduras',
		'HR' => 'Croatia',
		'HT' => 'Haiti',
		'HU' => 'Hungary',
		'ID' => 'Indonesia',
		'IE' => 'Ireland',
		'IL' => 'Israel',
		'IN' => 'India',
		'IQ' => 'Iraq',
		'IR' => 'Iran',
		'IS' => 'Iceland',
		'IT' => 'Italy',
		'JM' => 'Jamaica',
		'JO' => 'Jordan',
		'JP' => 'Japan',
		'KE' => 'Kenya',
		'KG' => 'Kyrgyzstan',
		'KH' => 'Cambodia',
		'KI' => 'Kiribati',
		'KM' => 'Comoros',
		'KN' => 'Saint Kitts & Nevis',
		'KP' => 'Korea, North',
		'KR' => 'Korea, South',
		'KW' => 'Kuwait',
		'KZ' => 'Kazakhstan',
		'LA' => 'Laos',
		'LB' => 'Lebanon',
		'LC' => 'Saint Lucia',
		'LI' => 'Liechtenstein',
		'LK' => 'Sri Lanka',
		'LR' => 'Liberia',
		'LS' => 'Lesotho',
		'LT' => 'Lithuania',
		'LU' => 'Luxembourg',
		'LV' => 'Latvia',
		'LY' => 'Libya',
		'MA' => 'Morocco',
		'MC' => 'Monaco',
		'MD' => 'Moldova',
		'ME' => 'Montenegro',
		'MG' => 'Madagascar',
		'MH' => 'Marshall Islands',
		'MK' => 'Macedonia',
		'ML' => 'Mali',
		'MM' => 'Myanmar',
		'MN' => 'Mongolia',
		'MR' => 'Mauritania',
		'MT' => 'Malta',
		'MU' => 'Mauritius',
		'MV' => 'Maldives',
		'MW' => 'Malawi',
		'MX' => 'Mexico',
		'MY' => 'Malaysia',
		'MZ' => 'Mozambique',
		'NA' => 'Namibia',
		'NE' => 'Niger',
		'NG' => 'Nigeria',
		'NI' => 'Nicaragua',
		'NL' => 'Netherlands',
		'NO' => 'Norway',
		'NP' => 'Nepal',
		'NR' => 'Nauru',
		'NZ' => 'New Zealand',
		'OM' => 'Oman',
		'PA' => 'Panama',
		'PE' => 'Peru',
		'PG' => 'Papua New Guinea',
		'PH' => 'Philippines',
		'PK' => 'Pakistan',
		'PL' => 'Poland',
		'PT' => 'Portugal',
		'PW' => 'Palau',
		'PY' => 'Paraguay',
		'QA' => 'Qatar',
		'RO' => 'Romania',
		'RS' => 'Serbia',
		'RU' => 'Russian Federation',
		'RW' => 'Rwanda',
		'SA' => 'Saudi Arabia',
		'SB' => 'Solomon Islands',
		'SC' => 'Seychelles',
		'SD' => 'Sudan',
		'SE' => 'Sweden',
		'SG' => 'Singapore',
		'SI' => 'Slovenia',
		'SK' => 'Slovak Republic',
		'SL' => 'Sierra Leone',
		'SM' => 'San Marino',
		'SN' => 'Senegal',
		'SO' => 'Somalia',
		'SR' => 'Suriname',
		'ST' => 'Sao Tome & Principe',
		'SV' => 'El Salvador',
		'SY' => 'Syrian Arab Republic',
		'SZ' => 'Swaziland',
		'TD' => 'Chad',
		'TG' => 'Togo',
		'TH' => 'Thailand',
		'TJ' => 'Tajikistan',
		'TL' => 'Timor-Leste',
		'TM' => 'Turkmenistan',
		'TN' => 'Tunisia',
		'TO' => 'Tonga',
		'TR' => 'Turkey',
		'TT' => 'Trinidad & Tobago',
		'TV' => 'Tuvalu',
		'TZ' => 'United Rep. of Tanzania',
		'UA' => 'Ukraine',
		'UG' => 'Uganda',
		'US' => 'United States',
		'UY' => 'Uruguay',
		'UZ' => 'Uzbekistan',
		'VA' => 'Vatican',
		'VC' => 'Saint Vincent & the Grenadines',
		'VE' => 'Venezuela',
		'VN' => 'Viet Nam',
		'VU' => 'Vanuatu',
		'WS' => 'Samoa',
		'YE' => 'Yemen',
		'ZA' => 'South Africa',
		'ZM' => 'Zambia',
		'ZW' => 'Zimbabwe'
	);

	/**
	 * Construct an instance, taking any valid country name or ICAO code and
	 * converting it to ICAO for internal storage.
	 *
	 * @param string $icao The ICAO code or country name.
	 */
	public function __construct($icao = false)
	{
		$icao = $this->autoIcao($icao);
		$this->setByIcao($icao);
	}

	/**
	 * Set the active country by name
	 *
	 * @param string $country
	 */
	public function setByName($country) {
		$icao = $this->autoIcao($country);
		$this->setByIcao($icao);
	}

	/**
	 * Set the active country by ICAO code
	 *
	 * @param string $icao
	 */
	public function setByIcao($icao) {
		if(!isset($this->icao2full[$icao])) {
			trigger_error("ICAO COUNTRY CODE '{$icao}' NOT FOUND", E_USER_WARNING);
		}
		$this->icao = $icao;
	}

	/**
	 * Automatically convert a country name to ICAO code
	 *
	 * @param string $country code or name
	 * @return string ICAO country code
	 */
	private function autoIcao($country) {
		if(strlen(trim($country)) == 2) {
			return $country;
		}
		return $this->full2icao[$country];
	}

	/**
	 * Determine whether a country uses postal codes, zip codes, or none
	 *
	 * @package INCOMPLETE
	 */
	public function getPostalCodeData() {
		trigger_error("This method is not yet implemented: " . __CLASS__ . '::' . __METHOD__, E_USER_ERROR);
	}

	/**
	 * Determine whether a given country has states, provinces, or none
	 *
	 * @return const REGION_* values for state, province, or none
	 * @package INCOMPLETE
	 */
	public function isStateOrProvince() {
		trigger_error("This method is not yet implemented: " . __CLASS__ . '::' . __METHOD__, E_USER_ERROR);
	}

	/**
	 * Get the ICAO country code
	 *
	 * @return string
	 */
	public function getIcao() {
		return $this->icao;
	}

	/**
	 * Get the full name for a given country
	 *
	 * @return string
	 */
	public function getFullName() {
		return $this->icao2full[$this->icao];
	}

	/**
	 * Return all country names in an array indexed by ICAO code
	 *
	 * @return array
	 */
	public function getAllByIcao() {
		return $this->icao2full;
	}

	/**
	 * Return all ICAO country codes in an array indexed by name
	 *
	 * @return array
	 */
	public function getAllByName() {
		return $this->full2icao;
	}
}
