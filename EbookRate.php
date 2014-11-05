<?php

class EbookRate
{
	// Sources:
	// http://ec.europa.eu/taxation_customs/resources/documents/taxation/vat/how_vat_works/rates/vat_rates_en.pdf
	// http://www.taxrates.com/blog/2013/08/27/taxing-the-ebook/
	// Publisher documents
	
	static $europe = [
		'AT' => 20,
		'BE' => 21,
		'BG' => 20,
		'CY' => 19,
		'CZ' => 21,
		'DE' => 19,
		'DK' => 25,
		'EE' => 20,
		'EL' => 23,
		'ES' => 21,
		'FI' => 24,
		'FR' => 5.5,
		'GB' => 20,
		'GG' => 20,
		'HR' => 5,
		'HU' => 5,
		'IE' => 23,
		'IM' => 20,
		'IT' => 22,
		'JE' => 20,
		'LT' => 21,
		'LU' => 3,
		'LV' => 21,
		'MT' => 18,
		'NL' => 6,
		'PL' => 23,
		'PT' => 6,
		'RO' => 9,
		'SE' => 6,
		'SI' => 9.5,
		'SK' => 20,
	];
	
	static $canada = [
		'AB' =>	[0.05, 'GST'],
		'BC' => [0.05, 'HST'],
		'MB' =>	[0.05, 'GST'],
		'NB' => [0.13, 'HST'],
		'NL' => [0.13, 'HST'],
		'NS' => [0.15, 'HST'],
		'NT' => [0.05, 'GST'],
		'NU' =>	[0.05, 'GST'],
		'ON' =>	[0.13, 'HST'],
		'PE' => [0.14, 'GST'],
		'QC' =>	[0.05, 'GST'],
		'SK' =>	[0.05, 'GST'],
		'YT' =>	[0.05, 'GST'],
	];
	
	/**
	 * Map a Canadian postal code to a state
	 * @param  $postalCode
	 * @return $state
	 */
	public static function map($postalCode)
	{
		switch ($postalCode[0]) {
			case 'A':
				return 'NL';
			case 'B':
				return 'NS';
			case 'C':
				return 'PE';
			case 'E':
				return 'NB';
			case 'G':
			case 'H':
			case 'J':
				return 'QC';
			case 'K':
			case 'L':
			case 'M':
			case 'N':
			case 'P':
				return 'ON';
			case 'R':
				return 'MB';
			case 'S':
				return 'SK';
			case 'T':
				return 'AB';
			case 'V':
				return 'BC';
			case 'X':
				if (in_array(substr($postalCode, 0, 3), ['X0A', 'X0B', 'X0C'])) {
					return 'NU';
				}
				return 'NT';
			case 'Y':
				return 'YT';
			default:
				throw new InvalidArgumentException('Invalid postal code for CA');
		}
	}
	
	
	
	public $taxable;   // Boolean
	public $rate;      // Combined rate to apply.
	
	
	/**
	 * $specs is an array that can contain:
	 * `country`      required
	 * `zipcode`      optional (required in US and CA)
	 * `tag`          optional
	 */
	public function __construct($specs)
	{
		$specs = (object) $specs;
		if (!isset($specs->country))  throw new InvalidArgumentException('country required');
		
		
		if (in_array($specs->country, array_keys(static::$europe))) {
			if (time() < 1420070400) {
				// Until Jan'15:
				$this->taxable = true;
				$this->rate    = static::$europe['FR'] / 100;
			}
			else {
				$this->taxable = true;
				$this->rate    = static::$europe[$specs->country] / 100;
			}
		}
		else if ($specs->country == 'CA') {
			if (!isset($specs->zipcode))  throw new InvalidArgumentException('zipcode required for CA');
			$state = static::map($specs->zipcode);
			
			$this->taxable = true;
			$this->rate    = static::$canada[$state][0];
			$this->type    = static::$canada[$state][1];
		}
		else if ($specs->country == 'US') {
			if (!isset($specs->zipcode))  throw new InvalidArgumentException('zipcode required for US');
			
			$localRate = TaxRate::findOne(['zipcode' => $specs->zipcode]);
			if (!$localRate)  throw new InvalidArgumentException('Invalid zipcode for US');
			
			// Extend EbookRate object:
			foreach ($localRate->attributes as $k => $v) {
				$this->$k = $v;
			}
			
			if (in_array($this->state, ['AK', 'AR', 'CA', 'DE', 'FL', 'GA', 'IA', 'IL', 'KS', 'MA', 'MD', 'MI', 'MO', 'MT', 'ND', 'NH', 'NV', 'NY', 'OK', 'OR', 'PA', 'RI', 'SC', 'VA', 'WV'])) {
				$this->taxable = false;
				$this->rate    = 0;
			}
			else if ($this->state == 'CT') {
				$this->taxable = true;
				$this->rate    = 0.01;
			}
			else {
				// Special cases for publishers
				if (isset($specs->tag)  && ($specs->tag == 'macmillan') && in_array($this->state, ['AL', 'ID', 'LA'])) {
					// Alabama, Idaho, Lousiana
					// NOOP (Publisher Nexus)
					$this->taxable = true;
					$this->rate    = 0;
				}
				else {
					$this->taxable = true;
					$this->rate    = $this->combinedRate;
				}
			}
		}
	}
	
}
