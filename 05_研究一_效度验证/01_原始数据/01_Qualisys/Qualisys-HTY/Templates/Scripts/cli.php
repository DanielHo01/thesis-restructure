<?php
require './vendor/autoload.php';

use Qualisys\PafToolbelt\Services\QtmHttpService;
// use Qualisys\PafToolbelt\Services\QtmService;
use Qualisys\Gait\AnalysisDispatcher;

$doc = <<<DOC
Gait command line interface.

Note: <measurement_guids> is a comma-separated list of name-guid pairs corresponding to
the measurements used in an analysis. The syntax is "measurement_1.qtm:guid_1,measurement2.qtm:guid_2".
<type_guids> is a comma-separated list of name-guid pairs corresponding to
the types used in an analysis. The syntax is "type_name_1:guid_1,type_name_2:guid_2".

Usage:
  cli.php analysis <analysis_name> <data_directory> [<measurement_guids>] [<type_guids>] [(-v | --verbose)]
  cli.php (-h | --help)

Options:
  -h --help       Show this help
  -v --verbose    Verbose output

DOC;

$docoptResponse = Docopt::handle($doc);
new Application($docoptResponse);

class Application {
	public function __construct($docopt) {
		$this->docopt = $docopt;
		
		$measurementGuidPairs = $this->docopt->args['<measurement_guids>']
			? preg_split('/,\s*/', $this->docopt->args['<measurement_guids>'])
			: [];
		$typeGuidPairs = $this->docopt->args['<type_guids>']
			? preg_split('/,\s*/', $this->docopt->args['<type_guids>'])
			: [];
		$typeGuids = [];
		$measurementGuids = [];
		$dataDirectory = realpath($this->docopt->args['<data_directory>']) . '/';
		
		if (!file_exists($dataDirectory)) {
			echo "Invalid path: <data_directory>.";
			return;
		}

		foreach ($measurementGuidPairs as $item) {
			$parts = preg_split('/\s*:\s*/', $item);
			
			if (count($parts) > 1) {
				$measurementGuids[$parts[0]] = $parts[1];
			}
			else {
				$measurementGuids[] = $item;
			}
		}

		foreach ($typeGuidPairs as $item) {
			$parts = preg_split('/\s*:\s*/', $item);
			
			if (count($parts) > 1) {
				$typeGuids[$parts[0]] = $parts[1];
			}
			else {
				$typeGuids[] = $item;
			}
		}
		
		// When no measurement guids are specified, find the ones associated with
		// the specified data directory.
		if (empty($measurementGuids)) {
			$api = new QtmHttpService('http://localhost:7979');
			$nodes = $api->get();
			
			foreach ($nodes['Children'] as $subjectNode) {
				$subjectPath = $subjectNode['Fields']['Path'];
				
				if ($this->isChildFolder($subjectPath, $dataDirectory)) {
					$typeGuids[$subjectNode['Fields']['Class']] = $subjectNode['ID'];
					
					foreach ($subjectNode['ChildIDs'] as $sessionNodeId) {
						$sessionNode = $api->get($sessionNodeId);
						$sessionPath = $sessionNode['Fields']['Path'];
				
						if ($this->isChildFolder($sessionPath, $dataDirectory)) {
							$typeGuids[$sessionNode['Fields']['Class']] = $sessionNode['ID'];
					
							foreach ($sessionNode['ChildIDs'] as $subSessionNodeId) {
								$subSessionNode = $api->get($subSessionNodeId);
								$subSessionPath = trim($subSessionNode['Fields']['Path'], '/\\');
						
								if ($this->isChildFolder($subSessionPath, $dataDirectory)) {
									$typeGuids[$subSessionNode['Fields']['Class']] = $subSessionNode['ID'];
								
									foreach ($subSessionNode['ChildIDs'] as $leafNodeId) {
										$leafNode = $api->get($leafNodeId);
										
										if ($leafNode['Fields']['Class'] === 'Measurements') {
											$measurementGuids[$leafNode['Fields']['Filename']] = $leafNode['ID'];
										}
									}
								}
							}
						}
					}
				}
			}
		}
		
		$qtmVars = [
			'templateDirectory' => __DIR__ . '/../',
			'workingDirectory'  => $dataDirectory,
			'measurementGuids'  => $measurementGuids,
			'typeGuids'         => $typeGuids,
			'cmoFile'           => null,
		];
		
		$this->dispatcher = new AnalysisDispatcher($qtmVars);

		if ($docopt->args['analysis']) {
			if (file_exists(realpath($this->docopt->args['<data_directory>']))) {
				$this->dispatcher->dispatch($this->docopt->args['<analysis_name>']);
			}
			else {
				echo 'Invalid path: <data_directory> (' . $this->docopt->args['<data_directory>'] . ').';
			}
		}
	}

	function isChildFolder($path, $parentFolder) {
		if (preg_match('/\/mnt\//', $parentFolder)) {
			$linuxPath = preg_replace('/([A-Z]):\\\/', '/mnt/$1/', $path);
			$linuxPath = str_replace('\\', '/', $linuxPath);
			$linuxPath[5] = strtolower($linuxPath[5]);
			$path = $linuxPath;
		}

		return substr($parentFolder, 0, strlen($path)) === $path;
	}
}