<?php

// Shim for Mongovel\Model

class TaxRate
{
	public static $collection;
	
	
	public static function findOne(array $query)
	{
		$taxRate = static::$collection->findOne($query);
		return is_null($taxRate)
			? null 
			: (object) ['attributes' => $taxRate];
	}
}
