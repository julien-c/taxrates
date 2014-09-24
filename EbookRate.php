<?php

class EbookRate
{
	// Sources:
	// http://ec.europa.eu/taxation_customs/resources/documents/taxation/vat/how_vat_works/rates/vat_rates_en.pdf
	// http://www.taxrates.com/blog/2013/08/27/taxing-the-ebook/
	// Publisher documents
	
	static $europe = [
		'BE' => 21,
		'FR' => 5.5,
		'BG' => 20,
		'DK' => 25,
		'HR' => 5,
		'DE' => 19,
		'HU' => 5,
		'FI' => 24,
		'NL' => 6,
		'PT' => 6,
		'LV' => 21,
		'LT' => 21,
		'LU' => 3,
		'RO' => 9,
		'PL' => 23,
		'EL' => 23,
		'EE' => 20,
		'IT' => 22,
		'CZ' => 21,
		'CY' => 19,
		'AT' => 20,
		'IE' => 23,
		'ES' => 21,
		'SK' => 20,
		'MT' => 18,
		'SI' => 9.5,
		'GB' => 20,
		'JE' => 20,
		'GG' => 20,
		'IM' => 20,
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
	
	
	
	public $taxable;   // Boolean
	public $rate;      // Combined rate to apply.
	
	
	/**
	 * $specs is an array that can contain:
	 * `country`      required
	 * `state`        optional (required in CA)
	 * `zipcode`      optional (required in US)
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
				$this->rate    = static::$europe[$specs->country];
			}
		}
		else if ($specs->country == 'CA') {
			if (!isset($specs->state))  throw new InvalidArgumentException('state required for CA');
			if (!isset(static::$canada[$specs->state]))  throw new InvalidArgumentException('Invalid state for CA');
			
			$this->taxable = true;
			$this->rate    = static::$canada[$specs->state][0];
			$this->type    = static::$canada[$specs->state][1];
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
				if ($specs->tag == 'macmillan' && in_array($this->rate->state, ['AL', 'ID', 'LA'])) {
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
