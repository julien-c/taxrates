<?php

class PostalCodesCanadaTest extends PHPUnit_Framework_TestCase
{
	public function testMapCodeToState()
	{
		$fakes = [
			'AB' =>	'T0E 1S2',
			'BC' => 'V6B 3P8',
			'MB' =>	'R0A 0C0',
			'NB' => 'E1A 1A1',
			'NL' => 'A0A 1A1',
			'NS' => 'B4V 2K4',
			'NT' => 'X1A 2P7',
			'NU' =>	'X0B 1B0',
			'ON' =>	'L6Y 2N4',
			'PE' => 'C0A 1A3',
			'QC' =>	'H4N 1J7',
			'SK' =>	'S6H 2X1',
			'YT' =>	'Y0A 1A1',
		];
		
		foreach ($fakes as $state => $code) {
			$this->assertEquals($state, EbookRate::map($code));
		}
	}
	
	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testCanadaException()
	{
		$state = EbookRate::map('D3D 1A1');
	}
}
