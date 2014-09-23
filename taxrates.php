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
		$states = ['AK', 'AL', 'AR', 'AZ', 'CA', 'CO', 'CT', 'DC', 'DE', 'FL', 'GA', 'HI', 'IA', 'ID', 'IL', 'IN', 'KS', 'KY', 'LA', 'MA', 'MD', 'ME', 'MI', 'MN', 'MO', 'MS', 'MT', 'NC', 'ND', 'NE', 'NH', 'NJ', 'NM', 'NV', 'NY',  'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VA', 'VT', 'WA', 'WI', 'WV', 'WY'];
		
		$states = array_merge($states, ['PR']);
		
		foreach ($states as $state) {
			$url = sprintf('https://s3-us-west-2.amazonaws.com/taxrates.csv/TAXRATES_ZIP5_%s201409.csv', $state);
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
		$collection = (new MongoClient)->{$input->getArgument('database')}->taxrates;
		$colArchive = (new MongoClient)->{$input->getArgument('database')}->{'taxrates-archive'};
		
		// Archive all items:
		foreach ($collection->find() as $item) {
			$colArchive->insert($item);
		}
		$collection->remove();
		
		$importDate = date('Ymd');
		
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
						'importDate'    => $importDate,
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

class EbookRate
{
	public function __construct($rate) {
		$this->rate = (object) $rate;
		
		// List compiled from Publisher document.
		// @see also http://www.taxrates.com/blog/2013/08/27/taxing-the-ebook/
		if (in_array($this->rate->state, ['AK', 'AR', 'CA', 'DE', 'FL', 'GA', 'IA', 'IL', 'KS', 'MA', 'MD', 'MI', 'MO', 'MT', 'ND', 'NH', 'NV', 'NY', 'OK', 'OR', 'PA', 'RI', 'SC', 'VA', 'WV'])) {
			$this->rate->taxable = false;
		}
		else {
			if (in_array($this->rate->state, ['AL', 'ID', 'LA'])) {
				// Alabama, Idaho, Lousiana
				// NOOP (Publisher Nexus)
			}
			$this->rate->taxable = true;
		}
	}
	
	public function export() {
		return $this->rate;
	}
}

class GetCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('get')
			->setDescription('Get tax object from zipcode, adapted for ebooks.')
			->addArgument(
				'zipcode',
				InputArgument::REQUIRED
			)
			->addOption(
				'database',
				'd',
				InputOption::VALUE_REQUIRED,
				'Where is the data stored',
				'tax'
			)
		;
	}
	
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$collection = (new MongoClient)->{$input->getOption('database')}->taxRates;
		$taxRate = $collection->findOne(array('zipcode' => $input->getArgument('zipcode')));
		$ebookRate = new EbookRate($taxRate);
		$output->writeln('<fg=green>'.json_encode($ebookRate->export()).'</fg=green>');
	}
}


$application = new Application();
$application->add(new CrawlCommand);
$application->add(new SeedCommand);
$application->add(new GetCommand);
$application->run();


