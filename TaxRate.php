<?php

// Shim for Mongovel Model

class TaxRate
{
	public static $collection;
	
	
	public static function findOne(array $query)
	{
		return static::$collection->findOne($query);
	}
}
