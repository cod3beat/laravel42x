<?php

class DatabaseSQLiteProcessorTest extends \L4\Tests\BackwardCompatibleTestCase {

	public function testProcessColumnListing()
	{
		$processor = new Illuminate\Database\Query\Processors\SQLiteProcessor;

		$listing = [['name' => 'id'], ['name' => 'name'], ['name' => 'email']];
		$expected = ['id', 'name', 'email'];

		$this->assertEquals($expected, $processor->processColumnListing($listing));

		// convert listing to objects to simulate PDO::FETCH_CLASS
		foreach($listing as &$row)
		{
			$row = (object) $row;
		}

		$this->assertEquals($expected, $processor->processColumnListing($listing));
	}

}
