<?php
namespace Qualisys\Gait\Pipeline;

use Colors\Color;
use Qualisys\PafToolbelt\Services\QtmHttpService;
use Qualisys\PafToolbelt\Util\ParseNumbers;

class GenerateReportDataPipeline extends Pipeline {
	public function run() {
		// Set verbose to true to see output useful for debugging.
		$this->verbose = true;

		// Fetch data from QTM using the rest api.
		$qtmHttpService = new QtmHttpService();
		$usedMeasurements = $qtmHttpService->get(array_values($this->measurementGuids));
		$dynamicMeasurementGuids = array_filter(array_map(function($m) { return $m['Fields']['Measurement type'] === 'Dynamic' ? $m['ID'] : null; }, $usedMeasurements), function($id) { return null !== $id; });

		// Get map normatives from settings.php.
		$xml_file = $this->workingDirectory. 'session.xml';
		$template_directory = $this->templateDirectory;
		$working_directory = $this->workingDirectory;
		include($this->templateDirectory. 'template_xml.php');
		include($this->templateDirectory. 'settings.php');

		if ($this->qtmData['session']->fields['Normative data'] === 'Adults') {
			$mapNorms = $map_norms_adults;
		}
		else {
			$mapNorms = $map_norms_paeds;
		}

		// Map Visual3D event signal names to events used in report.
		$v3dEventNames = [
			'LHS_to_LTO_pct' => ['name' => 'lto', 'cycle' => 'left', 'color' => 'left'], 
			'LHS_to_RTO_pct' => ['name' => 'rto', 'cycle' => 'left', 'color' => 'right'], 
			'LHS_to_RHS_pct' => ['name' => 'lhs', 'cycle' => 'left', 'color' => 'right'], 
			'RHS_to_RTO_pct' => ['name' => 'rto', 'cycle' => 'right', 'color' => 'right'], 
			'RHS_to_LHS_pct' => ['name' => 'rhs', 'cycle' => 'right', 'color' => 'left'], 
			'RHS_to_LTO_pct' => ['name' => 'lto', 'cycle' => 'right', 'color' => 'left']
		];

		$v3dCycleSides = [
			'LHS' => ['cycle' => 'left'],
			'RHS' => ['cycle' => 'right'],
		];

		$eventsXml = new \SimpleXMLElement(file_get_contents($this->workingDirectory . 'events.xml'));
		$timeseriesXml = new \SimpleXMLElement(file_get_contents($this->workingDirectory . 'timeseries.xml'));
		$normativeXml = new \SimpleXMLElement(file_get_contents($this->workingDirectory . 'normative.xml'));
		$metricsXml = new \SimpleXMLElement(file_get_contents($this->workingDirectory . 'metrics_per_trial.xml'));

		$excludedSignals = [
			'(?:(?:Left)|(?:Right)) Ankle Angles' => ['Z'],
			'(?:(?:Left)|(?:Right)) Elbow Angles' => ['Y'],
			'(?:(?:Left)|(?:Right)) Foot Pitch Angles' => ['Y', 'Z'],
			'(?:(?:Left)|(?:Right)) Foot Progression' => ['X', 'Y'],
			'(?:(?:Left)|(?:Right)) Hip Moment' => ['Z'],
			'(?:(?:Left)|(?:Right)) Hip Power' => ['Y', 'Z'],
			'(?:(?:Left)|(?:Right)) Knee Moment' => ['Z'],
			'(?:(?:Left)|(?:Right)) Ankle Moment' => ['Z'],
			'(?:(?:Left)|(?:Right)) Hip Power' => ['Y', 'Z'],
			'(?:(?:Left)|(?:Right)) Knee Power' => ['Y', 'Z'],
			'(?:(?:Left)|(?:Right)) Ankle Power' => ['Y', 'Z'],
		];
		
		$excludedEMGSignals = [
			'.*_(?:ACC|acc)_.*' => ['X', 'Y', 'Z']
		];

		foreach ($this->measurements as $m) {
			if ($m->fields['Measurement type'] === 'Static')
				continue;

			$normatives = [];
			$events = [];
			$series = [];
			$c3d = basename($m->name, '.qtm') . '.c3d';

			// Read events.
			foreach ($v3dEventNames as $signalName => $event) {
				$eventValues = array_map(function($n) { return round((float)$n, 3); }, explode(',', $eventsXml->xpath("/v3d/owner[@value='" . $c3d . "']/type/folder/name[@value='" . $signalName . "']/component/@data")[0][0]));
				//Remove events with the value 0 (caused by missing events resulting in "nodata" in Visual3D XML output)
				if (($key = array_search(0, $eventValues )) !== false) {
					unset($eventValues[$key]);
				}
				
				$events[] = ['name' => $event['name'], 'cycle' => $event['cycle'], 'color' => $event['color'], 'values' => $eventValues];
			}

			$linkModelBasedSignals = $timeseriesXml->xpath("/v3d/owner[@value='" . $c3d . "']/type[@value='LINK_MODEL_BASED']/folder/name");
			$derivedSignals        = $timeseriesXml->xpath("/v3d/owner[@value='" . $c3d . "']/type[@value='DERIVED']/folder/name");
			$emgProcessed          = $timeseriesXml->xpath("/v3d/owner[@value='" . $c3d . "']/type[@value='ANALOG']/folder[@value='EMG_PROCESSED']/name");
			$emgRaw                = $timeseriesXml->xpath("/v3d/owner[@value='" . $c3d . "']/type[@value='ANALOG']/folder[@value='EMG_RAW']/name");
			$metrics               = $metricsXml->xpath("/v3d/owner[@value='" . $c3d . "']/type[@value='METRIC']/folder/name");

			$series = $this->getSeriesFromXmlNode($linkModelBasedSignals, $excludedSignals);
			$series = array_merge($series, $this->getSeriesFromXmlNode($derivedSignals, $excludedSignals));
			$series = array_merge($series, $this->getSeriesFromXmlNode($emgProcessed, $excludedEMGSignals, null, '_%s', 'EMG_PROCESSED'));
			$series = array_merge($series, $this->getSeriesFromXmlNode($emgRaw, $excludedEMGSignals, null, '_%s_Raw', "EMG_RAW"));
			$metrics = $this->getScalarsFromXmlNode($metrics, null, 5, '_%s');

			// Set series indices.
			$serieIndexCounter = [];
			
			for ($seriesCount = 0; $seriesCount < count($series); $seriesCount++) {
				$currSerie = &$series[$seriesCount];

				if (!array_key_exists($currSerie['name'], $serieIndexCounter)) {
					$serieIndexCounter[$currSerie['name']] = 0;
				}
				else {
					$serieIndexCounter[$currSerie['name']]++;
				}
				
				$currSerie['index'] = $serieIndexCounter[$currSerie['name']];
			}

			// Get cycle time data
			$cycleTimes = [];
			
			foreach ($v3dCycleSides as $cycleSide => $cycleSideEvent) {
				$cycleData = explode(',', $eventsXml->xpath("/v3d/owner[@value='" . $c3d . "']/type/folder[@value='EVENTS_TIMES']/name[@value='" . $cycleSide . "']/component/@data")[0][0]);

				for ($cycleIndex = 0; $cycleIndex < count($cycleData) - 1; $cycleIndex++) {
					$currCycleTime = [
						'cycle' => $cycleSideEvent['cycle'],
						'index' => $cycleIndex,
						'start' => floatval($cycleData[$cycleIndex]),
						'end' => floatval($cycleData[$cycleIndex + 1])
					];

					$cycleTimes[] = $currCycleTime;
				}
			}
			
			$data = array_merge($series, $metrics);
			$m->fields['Events'] = json_encode($events);
			$m->fields['Analysis result'] = json_encode($data);
			$m->fields['Cycle times'] = json_encode($cycleTimes);
			$m->save();
		}
		
		$normativeLow   = $normativeXml->xpath("/v3d/owner/type/folder[@value='NORM_LOWER']/name");
		$normativeRange = $normativeXml->xpath("/v3d/owner/type/folder[@value='NORM_RANGE']/name");
		$normativeTsp   = $normativeXml->xpath("/v3d/owner/type/folder[@value='TSP_normatives']/name");

		// Find EMG sources (contain a signal suffixed with "_raw").
		$emgNormNames = [];
		$normSeriesNameNodes = $normativeXml->xpath("/v3d/owner/type/folder[@value='NORM_LOWER']/name/@value");
		foreach ($normSeriesNameNodes as $nameNode) {
			$currName = (string)$nameNode['value'];
			$endsWithRaw = preg_match('/(.+?)_raw$/', $currName, $nameParts);
			if ($endsWithRaw) {
				$emgNormNames[] = $nameParts[1];
			}
		}

		$emgExclusion = '';
		if (count($emgNormNames) > 0) {
			$emgExclusion = '(?!' . join('|', $emgNormNames) . ')';
		}

		// We're only plotting normatives for the right cycles so let's exclude 
		// all the left ones. Except for EMG, by excluding them by name.
		$excludedNormatives = array_merge($excludedSignals, [
			$emgExclusion . '(?:Left).*' => ['X', 'Y', 'Z'],
		]);

		$normatives = $this->getSeriesFromXmlNode($normativeLow, $excludedNormatives, null, '_%s_Norm_Low');
		$normatives = array_merge($normatives, $this->getSeriesFromXmlNode($normativeRange, $excludedNormatives, null, '_%s_Norm_Range'));
		$normatives = array_merge($normatives, $this->getScalarsFromXmlNode($normativeTsp, 'Y', 4, '_%s'));
		$normatives = array_merge($normatives, $this->getScalarsFromArray($mapNorms));

		$this->qtmData['subject']->fields['Normatives'] = json_encode($normatives);
		$this->qtmData['subject']->save();

		$staticSignals = $timeseriesXml->xpath("/v3d/owner[@value='Static.c3d']/type[@value='LINK_MODEL_BASED']/folder/name");
		$staticSeries = $this->getSeriesFromXmlNode($staticSignals, $excludedSignals);

		$this->qtmData['subsession']->fields['Static analysis result'] = json_encode($staticSeries);
		$this->qtmData['subsession']->save();
		
		$data = $qtmHttpService->get($dynamicMeasurementGuids, ['includeParents' => true]);
		file_put_contents($this->workingDirectory . 'data.json', json_encode($data));
	}
	
	private function getScalarsFromArray($scalars) {
		$result = [];

		foreach ($scalars as $key => $value)
			$result[] = ['name' => $key, 'type' => 'scalar', 'value' => $value];

		return $result;
	}

	private function getScalarsFromXmlNode($rootNode, $component=null, $significantFigures=4, $suffixPattern='_%s') {
		$scalars = [];

		foreach ($rootNode as $node) {
			if ($component === null)
				$component = 'X';

			$componentMap = ['X' => 0, 'Y' => 1, 'Z' => 2];
			$componentIndex = $componentMap[$component];

			$name = (string)$node['value'];
			$name = str_replace(' ', '_', $name);

			$value = (string)$node->component[$componentIndex]['data'];
			$values = null;

			$isArray = false;
			
			if (strpos($value, ',') !== false) {
				$values = explode(',', $value);
				
				foreach ($values as $key => $currScalar) {
					$values[$key] = $currScalar == 'nodata' ? null : ParseNumbers::toSignificantFigures($currScalar, $significantFigures);
				}
				
				$isArray = true;
			}
			else {
				$value = $value == 'nodata' ? null : ParseNumbers::toSignificantFigures($value, $significantFigures);
			}
			
			$cycle = null;
			preg_match('/(?P<cycle>(?:left)|(?:right))/i', $name, $matches);

			if (isset($matches['cycle'])) {
				$cycle = strtolower($matches['cycle']);
			}

			if (!$isArray) {
				$scalars[] = ['name' => $name, 'value' => $value, 'type' => 'scalar', 'cycle' => $cycle];
			}
			else {
				foreach ($values as $key => $currScalar) {
					$scalars[] = ['name' => $name, 'value' => $currScalar, 'type' => 'scalar', 'cycle' => $cycle, 'index' => $key];
				}
			}
		}

		return $scalars;
	}

	private function getSeriesFromXmlNode($rootNode, $excluded, $precision=null, $suffixPattern='_%s', $source=null) {
		$series = [];
		$skipped = [];
		$color = new Color();

		foreach ($rootNode as $signal) {
			$signalName = (string)$signal['value'];

			// Replace potential EMG prefix
			$emgPrefix ='emg_';
			if (strtolower(substr($signalName, 0, strlen($emgPrefix))) === $emgPrefix) {
				$signalName = substr($signalName, strlen($emgPrefix));
			} 

			// Capture all signals inside LINK_MODEL_BASED that begin with 'Left' or 'Right'.
			if (preg_match('/(?P<cycle>(?:Left)|(?:Right)|(?:L)|(?:R))(?: |_|-)([a-zA-Z_ ])+/', $signalName, $matches)) {
				if (isset($matches['cycle'])) {
					$cycle = $matches['cycle'];

					if($cycle === 'L')
						$cycle = 'left';

					if ($cycle === 'R')
						$cycle = 'right';
				}

				foreach ($signal->component as $c) {
					$continue = false;
					$component = (string)$c['value'];

					// Exclude any signal matching the patterns in the array with excluded signals.
					foreach ($excluded as $pattern => $components) {
						if (preg_match('/^' . $pattern . '(?P<norm_suffix>_[' . join($components, '') . ']_[A-Z]{3}_[A-Z]{3,4})?' . '$/', $signalName, $excludeMatches)) {
							if (in_array($component, $components) || isset($excludeMatches['norm_suffix'])) {
								$continue = true;
								$skipped[] = $signalName . (isset($excludeMatches['norm_suffix']) ? '' : ' (' . $component . ')');

								break;
							}
						}
					}

					if ($continue)
						continue;
					
					// Capture a potential component already present in the signal name.
					// If it exists it's probably a normative.
					if (preg_match('/^.+_(?P<component>[XYZ]).+/', $signalName, $componentMatches))
						$component = $componentMatches['component'];

					// Remove existing component if it exists. We will add it in the correct
					// place in the following steps.
					$name = preg_replace('/^(.+)_(?:[XYZ])(.+)/', '${1}${2}', $signalName);

					// Simplify final series name, eg by removing _RON_RHS etc from it.
					$name = preg_replace('/_[A-Z]{3}_[A-Z]{3,4}/', '', $name);
					$name = str_replace(' ', '_', $name) . sprintf($suffixPattern, $component);

					$series[] = [
						'type' => 'series',
						'cycle' => isset($cycle) ? strtolower($cycle) : 'both',
						'yValues' => ParseNumbers::toFloats((string)$c['data'], $precision),
						'name' =>  $name,
						'source' => $source
					];

					if ($this->verbose)
						printf("Including %-35.30s => %s\n", $signalName, end($series)['name']);
				}
			}
			else {
				// Signals not captured.
				$skipped[] = $signalName . "\n";
			}
		}
		
		if ($this->verbose) {
			foreach ($skipped as $signal) {
				echo $color('Skipping ')->yellow();
				echo $color($signal . "\n")->yellow();
			}
		}

		return $series;
	}	
}
?>