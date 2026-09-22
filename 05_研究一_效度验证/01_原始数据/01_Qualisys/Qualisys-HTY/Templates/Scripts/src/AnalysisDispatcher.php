<?php
namespace Qualisys\Gait;

use function Stringy\create as s;
use Qualisys\PafToolbelt\Services\QtmService;
use Qualisys\PafToolbelt\Models\ReportData;
use Qualisys\PafToolbelt\Models\UserSettings;
use Qualisys\Gait\Model\GaitSession;
use Qualisys\Gait\Pipeline\Export3dViewerDataPipeline;
use Qualisys\Gait\Pipeline\GenerateReportDataPipeline;
use Qualisys\Gait\Pipeline\EncodeVideoPipeline;
use Qualisys\Gait\Pipeline\GenerateReportDescriptorPipeline;
use Qualisys\Gait\Pipeline\OpenCmoPipeline;
use Qualisys\Gait\Pipeline\CreateOnlineReportPipeline;
use Qualisys\Gait\Pipeline\CreateOfflineReportPipeline;
use Qualisys\Gait\Pipeline\GenerateUploadTokenPipeline;
use Qualisys\Gait\Analysis\EncodeVideoAnalysis;
use Qualisys\Gait\Analysis\OnlineReportAnalysis;

date_default_timezone_set('Europe/Stockholm');

class AnalysisDispatcher {

	public function __construct($qtmVars) {
		$this->qtmVars           = $qtmVars;
		$this->templateDirectory = $qtmVars['templateDirectory'];
		$this->workingDirectory  = $qtmVars['workingDirectory'];
		$this->measurementGuids  = is_array($qtmVars['measurementGuids']) ? $qtmVars['measurementGuids'] : [$qtmVars['measurementGuids']];
		$this->typeGuids         = $qtmVars['typeGuids'];
		$this->cmoFile           = $qtmVars['cmoFile'];
		$this->userSettings      = new UserSettings($this->templateDirectory . '..');

		$qtmService = QtmService::getInstance('http://localhost:7979');
		$this->qtmData = [
			'measurements' => []
		];
		
		foreach ($this->measurementGuids as $measurementName => $guid) {
			$this->qtmData['measurements'][$measurementName] = $qtmService->get($guid);
		}

		foreach ($this->typeGuids as $typeName => $guid) {
			$this->qtmData[(string) s($typeName)->camelize()] = $qtmService->get($guid);
		}

		$this->versionData = $qtmService->api->client->get('/api/v1/version/')->json();
		$this->tempUploadTokenPath = $this->workingDirectory . 'upload-token.json';
	}

	public function dispatch($analysisName) {
		$session       = new GaitSession($this->workingDirectory . 'session.xml');
		$screenshotDir = sys_get_temp_dir() . '/Visual3D screenshots/';

		switch (trim($analysisName)) {

			case 'Report only':
				echo new OpenCmoPipeline($session, $this->qtmVars, $this->qtmData);
				break;

			case 'Internal generate report descriptor':
				$pipeline = new GenerateReportDescriptorPipeline($session, $this->qtmVars, $this->qtmData);
				$pipeline->run($analysisName);
				break;

			case 'Generate Comparison report descriptor':
				$pl = new GenerateReportDescriptorPipeline($session, $this->qtmVars, $this->qtmData);
				$pl->run($analysisName);
				break;

			case 'Internal encode video (online)':
				$analysis = new EncodeVideoAnalysis($this->qtmVars, $this->qtmData, [
					'userSettings' => $this->userSettings,
					'version' => $this->versionData,
				]);
				$analysis->run();

				break;

			case 'Internal encode video (offline)':
				$pl = new EncodeVideoPipeline($session, $this->qtmVars, $this->qtmData, [
					'videos' => [
						'sagittal' => [
							'srcSuffix' => $this->userSettings->get('Sagittal video'),
							'startOffset' => $this->userSettings->get('Sagittal video offset'),
							'outputSuffix' => ' sagittal'
						],
						'frontal' => [
							'srcSuffix' => $this->userSettings->get('Frontal video'),
							'startOffset' => $this->userSettings->get('Frontal video offset'),
							'outputSuffix' => ' frontal'
						]
					],
				]);
				$pl->run();

				break;

			case 'Internal export marker data':
				$pl = new Export3dViewerDataPipeline($session, $this->qtmVars, $this->qtmData);
				$pl->run();
				break;

			case 'Internal generate json':
				$pl = new GenerateReportDataPipeline($session, $this->qtmVars, $this->qtmData);
				$pl->run();

				break;

			case 'Internal generate online web report':
				$analysis = new OnlineReportAnalysis($this->qtmVars, $this->qtmData, [
					'userSettings' => $this->userSettings,
					'version' => $this->versionData,
				]);
				$analysis->run();

				break;

			case 'Internal generate offline web report':
				$pl = new CreateOfflineReportPipeline($session, $this->qtmVars, $this->qtmData, [
					'userSettings' => $this->userSettings
				]);
				$pl->run();

				break;

			case 'Internal post process':
				if (file_exists($this->workingDirectory . 'report_id')) {
					$reportId = json_decode(file_get_contents($this->workingDirectory . 'report_id'));
					
					// Update session.xml with the new id.
					$session = simplexml_load_file($this->workingDirectory . 'session.xml');
					$fields = $session->xpath('Session/Fields');
					
					foreach ($fields as $field)
						$field->Web_report_id = $reportId;
					
					$session->saveXML($this->workingDirectory . 'session.xml');
					
					unlink($this->workingDirectory . 'report_id');
				}
				
				break;

			case 'Internal export variables':
				$measurements = [];
				$types = [];
				
				foreach ($this->measurementGuids as $qtmFile => $guid) {
					$measurements[] = $qtmFile . ':' . $guid;
				}

				foreach ($this->typeGuids as $type => $guid) {
					$types[] = $type . ':' . $guid;
				}
				
				$str = '"' . join($measurements, ',') . '" "' . join($types, ',') . '"';
				file_put_contents($this->workingDirectory . '/guids.txt', $str);
				
				break;

			case 'Internal Generate Upload Token':
				$pl = new GenerateUploadTokenPipeline($session, $this->qtmVars, $this->qtmData, [
					'userSettings' => $this->userSettings,
					'versionData' => $this->versionData,
				]);
				$pl->run($this->tempUploadTokenPath);

				break;

			case 'Internal Delete Upload Token':
				if (file_exists($this->tempUploadTokenPath) && !is_dir($this->tempUploadTokenPath)) {
					unlink($this->tempUploadTokenPath);
				}

				break;
				
			default:
				echo 'No analysis named \'' . trim($analysisName) . '\'.';
				break;
		}
	}
	
}
?>
