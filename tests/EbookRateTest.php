<?php

class EbookRateTest extends PHPUnit_Framework_TestCase
{
	
	public static function setUpBeforeClass()
	{
		TaxRate::$collection = (new MongoClient)->tax->taxRates;
	}
	
	
	public function testEurope()
	{
		$rate = new EbookRate(array(
			'country' => 'FR',
		));
		$this->assertTrue($rate->taxable);
		$this->assertEquals(0.055, $rate->rate);
		
		
		$rate = new EbookRate(array(
			'country' => 'ES',
		));
		if (time() < 1420070400) {
			// Until Jan'15:
			$this->assertTrue($rate->taxable);
			$this->assertEquals(0.055, $rate->rate);
		}
		else {
			$this->assertTrue($rate->taxable);
			$this->assertEquals(0.21, $rate->rate);
		}
	}
	
	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testCanadaException()
	{
		$rate = new EbookRate(array(
			'country' => 'CA',
		));
	}
	
	public function testCanada()
	{
		$rate = new EbookRate(array(
			'country' => 'CA',
			'zipcode' => 'T0E 1S2',
		));
		$this->assertEquals(true, $rate->taxable);
		$this->assertEquals(0.05, $rate->rate);
		$this->assertEquals('GST', $rate->type);
	}
	
	
	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testUSException()
	{
		$rate = new EbookRate(array(
			'country' => 'US',
			'zipcode' => '00000',
		));
	}
	
	public function testUS()
	{
		$rate = new EbookRate(array(
			'country' => 'US',
			'zipcode' => '94305',
		));
		$this->assertEquals(false, $rate->taxable);
		$this->assertEquals(0, $rate->rate);
		$this->assertEquals('CA', $rate->state);
		$this->assertEquals(0.0875, $rate->combinedRate);
		
		// Special cases for publishers
		$rate = new EbookRate(array(
			'country' => 'US',
			'zipcode' => '35004',
		));
		$this->assertEquals(true, $rate->taxable);
		$this->assertEquals(0.09, $rate->rate);
		$this->assertEquals('AL', $rate->state);
		$this->assertEquals(0.09, $rate->combinedRate);
		
		$rate = new EbookRate(array(
			'country' => 'US',
			'zipcode' => '35004',
			'tag'     => 'macmillan',
		));
		$this->assertEquals(true, $rate->taxable);
		$this->assertEquals(0, $rate->rate);
		$this->assertEquals('AL', $rate->state);
		$this->assertEquals(0.09, $rate->combinedRate);
	}
}
