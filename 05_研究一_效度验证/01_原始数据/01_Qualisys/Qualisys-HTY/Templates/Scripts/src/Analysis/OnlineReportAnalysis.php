<?php
namespace Qualisys\Gait\Analysis;

use Colors\Color;

use Qualisys\PafToolbelt\Models\Analysis;
use Qualisys\PafToolbelt\Models\PafNode;
use Qualisys\PafToolbelt\Models\Report;
use Qualisys\PafToolbelt\Models\ReportData;
use Qualisys\PafToolbelt\Models\ResourceSet;
use Qualisys\PafToolbelt\Services\ReportService;
use Qualisys\PafToolbelt\Util\Visual3dResultsParser;
use Qualisys\PafToolbelt\Util\LogEvent;
use Qualisys\PafToolbelt\Util\PafReportVersionUtil;

class OnlineReportAnalysis extends Analysis {
	public function run() {
		$userSettings        = $this->options['userSettings'];
		$analysisResultFiles = [$this->workingDirectory . 'session_data.xml'];
		$existingFileCount   = 0;

		foreach ($analysisResultFiles as $file) {
			if (file_exists($file)) {
				$existingFileCount++;
			}
		}

		if ($existingFileCount === 0) {
			throw new \Exception('Please run the analysis before creating the online report.');
		}

		// Escape square brackets or files might not be found.
		$workingDirectoryEscaped = str_replace(['[', ']'], ['\[', '\]'], $this->workingDirectory);
		$resourcePaths = [
			'3d'         => glob($workingDirectoryEscaped . '*-3d-data.json'),
			'video'      => glob($workingDirectoryEscaped . '*.mp4'),
			'attachment' => [$workingDirectoryEscaped . 'Attachments'],
		];
        $measurementFields = [
            'Affected side',
            'Ankle width left', 
            'Ankle width right', 
            'Case ID', 
            'Class', 
            'Comments', 
            'Creation date', 
            'Creation time', 
            'Diagnosis', 
            'Directory pattern', 
            'External aid', 
            'Filename', 
            'First name', 
            'Height', 
            'Knee width left', 
            'Knee width right', 
            'Last name', 
            'Leg length left', 
            'Leg length right', 
            'Measurement pattern', 
            'Measurement type', 
            'Patient ID', 
            'Personal aid', 
            'Prothesis_Orthosis', 
            'Type', 
            'Used', 
            'Weight'
        ];
        $customFields = [
            'Affected side',
            'Ankle width left', 
            'Ankle width right', 
            'Append model', 
            'Case ID', 
            'Class', 
            'Comments', 
            'Creation date', 
            'Creation time', 
            'Diagnosis', 
			'Secondary diagnosis',
            'Directory pattern', 
            'Event mode', 
            'External aid', 
			'External aid side',
            'Filename', 
            'First name', 
            'Height', 
            'Knee width left', 
            'Knee width right', 
            'Last name', 
            'Left foot normalised to static trial', 
            'Leg length left', 
            'Leg length right', 
            'Measurement pattern', 
            'Model', 
            'Model used', 
            'Multisegment foot', 
            'Patient ID', 
            'Personal aid', 
			'Personal aid side', 
            'Prothesis_Orthosis', 
            'Right foot normalised to static trial', 
            'Test condition', 
            'Test operator', 
            'Type', 
            'Weight',
			'Session date',
			'Sole delta left', 
			'Sole delta right',
			'Shoulder offset left', 
			'Shoulder offset right',
			'Elbow width left', 
			'Elbow width right',
			'Wrist width left', 
			'Wrist width right',
			'Hand thickness left', 
			'Hand thickness right',
			'Marker diameter',
			'Normative data',
			'CGM2 Model',
			'Head normalised to static trial',
			'Shoes',
			'Moment Projection',
        ];

		// Filter out static measurements.
		$filteredMeasurements = array_filter($this->qtmData['measurements'], function($m) {
			return $m->fields['Measurement type'] !== 'Static';
		});

		// Set env var used by VideoResource.
		putenv('FFMPEG=' . $this->templateDirectory . 'Assets/Programs/ffmpeg/bin/ffmpeg.exe');

		$resultFiles = [$this->workingDirectory . 'session_data.xml'];
		$resources = new ResourceSet($resourcePaths, $filteredMeasurements, true);

		// Set camera settings on video resource based on matching camera id.
		$resources->applyCameraSettings($userSettings->get('Cameras'));

		$reportParser = new Visual3dResultsParser($resultFiles);
		$resultData   = $reportParser->parse(false);
		$reportData   = new ReportData($resultData, $filteredMeasurements, $measurementFields, $resources);
		$report       = new Report($reportData, $resources);

		$report->data->setVersion($this->options['version']);
		$report->data->setUsingStandardUnits(true);
		$report->data->setCustomFields($customFields, $this->qtmData['subsession']->fields);
		
		$reportType = (!empty($this->options['reportType'])) ? $this->options['reportType'] : 'default';
		$reportVersionUtil = new PafReportVersionUtil($this->qtmData['subsession'], 'Web Report IDs', 'Update existing web report');

		// Read stored original report id from PAF field (if applicable)
		$originalReportId = $reportVersionUtil->getOriginalId($reportType);
		if (!empty($originalReportId)) {
			$report->data->setOriginalID($originalReportId);
		}

		// Manually add data from subject & session.
		$report->data->setCustomField([
			'id' => 'Date of birth',
			'value' => $this->qtmData['subject']->fields['Date of birth'],
			'type' => 'string'
		]);
		$report->data->setCustomField([
			'id' => 'Sex',
			'value' => $this->qtmData['subject']->fields['Sex'],
			'type' => 'string'
		]);
		$report->data->setCustomField([
			'id' => 'Height',
			'value' => $this->qtmData['session']->fields['Height'],
			'type' => 'string'
		]);
		$report->data->setCustomField([
			'id' => 'Weight',
			'value' => $this->qtmData['session']->fields['Weight'],
			'type' => 'string'
		]);
		$report->data->setCustomField([
			'id' => 'Diagnosis',
			'value' => $this->qtmData['session']->fields['Diagnosis'],
			'type' => 'string'
		]);
		$report->data->setCustomField([
			'id' => 'Gross Motor Function Classification',
			'value' => $this->qtmData['session']->fields['Gross Motor Function Classification'],
			'type' => 'string'
		]);
		$report->data->setCustomField([
			'id' => 'Functional Mobility Scale',
			'value' => $this->qtmData['session']->fields['Functional Mobility Scale'],
			'type' => 'string'
		]);

		$report->data->setClientId($this->options['version']['UserName']);

		$report->data->setSubject([
			'id' => $this->qtmData['subject']->fields['Patient ID'] . '_' . $this->qtmData['subject']->fields['Date of birth'],
			'displayName' => $this->qtmData['subject']->fields['First name'] . ' ' . $this->qtmData['subject']->fields['Last name']
		]);

        $moduleSubType = $this->qtmData['subsession']->fields['Type'];
		$report->data->setModule([
			'type' => 'Gait',
			'subtype' => $moduleSubType
		]);

		if ($userSettings->get('debug')) {
			$reportDataFile = $this->workingDirectory . 'report-data.json';
			$report->data->writeJson($reportDataFile);

			echo 'Report data written to ' . $reportDataFile . ".\n";
		}

		$reportApiUrlEnv = getenv('PAF_GAIT_REPORT_SERVER_API_URL');
		$reportApiUrl = empty($reportApiUrlEnv) ? $userSettings->get('Report server url') : $reportApiUrlEnv;

		if (empty($reportApiUrl)) {
			$reportApiUrl = 'https://report.qualisys.com';
		}
		
		$reportService = new ReportService($reportApiUrl);

		$reportService->addListener('report_service.save_report_failed', function(LogEvent $event) { echo $event->message . "\n"; });
		$reportService->addListener('report_service.connection_failed', function(LogEvent $event) { echo $event->message . "\n"; });
		$reportService->addListener('report_service.no_upload_token', function(LogEvent $event) { echo $event->message . "\n"; });

		$reportId = $reportService->saveReport($report);

		if (!empty($reportId)) {
			// Update original report id in PAF field (if applicable)
			$reportVersionUtil->updateReportId($reportId, $reportType);
		}

		if ($userSettings->get('debug')) {
			$c = new Color();

			if (!empty($reportId)) {
				echo $c('Saved report: ' . $reportId . ".\n")->green();
			}
		}
	}
}
?>