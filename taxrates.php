<?php

require 'vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class CrawlCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('crawl')
			->setDescription('Crawl CSV files')
		;
	}
	
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$states = ['AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'DC', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND',  'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'];
		
		foreach ($states as $state) {
			$url = sprintf('https://s3-us-west-2.amazonaws.com/taxrates.csv/TAXRATES_ZIP5_%s201407.csv', $state);
			file_put_contents('csv/'.$state.'.csv', file_get_contents($url));
			usleep(500000); // 500ms
		}
	}
}

class DirtyRow implements ArrayAccess, Countable
{
	private $container = array();
	public function __construct($string) {
		$this->container = explode(',', $string);
	}
	
	public function offsetSet($offset, $value) {}
	public function offsetExists($offset) {}
	public function offsetUnset($offset) {}
	public function offsetGet($offset) {
		return ($offset < 0 ) ? $this->container[count($this->container) + $offset] : $this->container[$offset];
	}
	public function count() {
		return count($this->container);
	}
	public function slice($offset, $length) {
		return array_slice($this->container, $offset, $length);
	}
}

class SeedCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('seed')
			->setDescription('Seed to Mongo')
			->addArgument(
				'database',
				InputArgument::REQUIRED
			)
		;
	}
	
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$collection = (new MongoClient)->{$input->getArgument('database')}->taxRates;
		$collection->drop();
		
		foreach (array_diff(scandir('csv'), ['.', '..']) as $filename) {
			$documents = [];
			$csv = array_slice(explode("\n", file_get_contents('csv/'.$filename)), 1); // Skip the first heading line.
			foreach ($csv as $line) {
				if ($line !== "") {
					$r = new DirtyRow($line);
					
					if (count($r) == 9) {
						$taxRegionName = $r[2];
					} else {
						$taxRegionName = implode(',', $r->slice(2, count($r) - 8));
					}
					
					$document = array(
						'state'         => $r[0],
						'zipcode'       => $r[1],
						'taxRegionName' => $taxRegionName,
						'taxRegionCode' => $r[-6],
						'combinedRate'  => floatval($r[-5]),
						'stateRate'     => floatval($r[-4]),
						'countyRate'    => floatval($r[-3]),
						'cityRate'      => floatval($r[-2]),
						'specialRate'   => floatval($r[-1]),
					);
					
					// Sanity check:
					if (strlen($document['taxRegionCode']) !== 4 || !ctype_upper($document['taxRegionCode'])) {
						$output->writeln('<error>Wrong data formatting</error>');
						$output->writeln($line);
					} else {
						$documents[] = $document;
					}
				}
			}
			$res = $collection->batchInsert($documents);
			$output->writeln('Inserted '.count($documents).' documents '.'<fg=green>'.json_encode($res).'</fg=green>');
		}
		
		// Create index:
		$collection->createIndex(array('zipcode' => 1), array('unique' => true));
		$output->writeln('<comment>Index created</comment>');
	}
}

$application = new Application();
$application->add(new CrawlCommand);
$application->add(new SeedCommand);
$application->run();


