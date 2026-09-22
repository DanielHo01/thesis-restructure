<?php
namespace Qualisys\Gait\Pipeline;

use GuzzleHttp;
use GuzzleHttp\Post\PostFile;

use Qualisys\PafToolbelt\Util\Ffmpeg;
use Qualisys\PafToolbelt\Util\Ffprobe;
use Qualisys\PafToolbelt\Util\VideoEncoderOptions;
use Qualisys\PafToolbelt\Util\VideoEncoder;
use Qualisys\PafToolbelt\Models\Visual3dReport;

class CreateOnlineReportPipeline extends Pipeline {
	public function run() {
        $userSettings = $this->options['userSettings'];
        $versionData = $this->options['versionData'];
        $analysisFiles = [$this->workingDirectory . 'session_data.xml'];

        // Check if we have any available analysis files.
        $existingFileCount = 0;

        foreach ($analysisFiles as $file) {
            if (file_exists($file)) {
                $existingFileCount++;
            }
        }

        if ($existingFileCount === 0) {
            throw new \Exception('Please run the analysis before creating the web report.');
            return;
        }

        $report = new Visual3dReport($analysisFiles, false);

        $report->videoFiles = glob($this->workingDirectory . '/*.avi');
        $report->viewportDataFiles = glob($this->workingDirectory . '/*-3d-data.json');

        // Provide attachment folders paths
        $attachmentFolders = [$this->workingDirectory . '/Attachments'];
        
        // Apply measurement whitelist.
        $report->applyMeasurementWhitelist = true;

        // Get measurements.
        $measurements = $this->qtmData['measurements'];
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
        $report->processMeasurements($measurements, $measurementFields, function($original, $processed) {
            // Filter out unnecessary measurements.
            if ($original->fields['Measurement type'] === 'Static') {
                return null;
            }

            $processed['id'] = pathinfo($original->fields['Filename'], PATHINFO_FILENAME);
            return $processed;
        });

        // Process attachment folder files
        $report->processAttachments($attachmentFolders);
       
        $videoMetadata = [];

        // Encode videos.
        $ffmpeg = new Ffmpeg($this->templateDirectory . 'Assets/Programs/ffmpeg/bin/ffmpeg.exe');
        $ffprobe = new Ffprobe($this->templateDirectory . 'Assets/Programs/ffmpeg/bin/ffprobe.exe');

        $videoQualitySetting = intval($userSettings->get('Video quality'));

        if (empty($videoQualitySetting)) {
            $videoQualitySetting = 50;
        }
        else if ($videoQualitySetting < 1 || $videoQualitySetting > 100) {
            $videoQualitySetting = min(100, max(1, $videoQualitySetting));
        }

        $videoOptions = new VideoEncoderOptions();

        $videoOptions->codec = 'libx264 -profile:v baseline -level 3.0';
        $videoOptions->keepAspectRatio = true;
        $videoOptions->videoExtension = 'mp4';
        $videoOptions->fps = null;

        if ($videoQualitySetting < 30) {
            $bitrate = 300 + ($videoQualitySetting / 30) * 300;

            $videoOptions->maxSize = 480;
            $videoOptions->bitrate = $bitrate . 'k';
            $videoOptions->bufferSize = (2 * $bitrate) . 'k';
            $videoOptions->keyframeInterval = 25;
            $videoOptions->fps = 25;
        }
        else if ($videoQualitySetting >= 30 && $videoQualitySetting < 60) {
            $bitrate = 700 + (($videoQualitySetting - 30) / 30) * 300;
            
            $videoOptions->maxSize = 720;
            $videoOptions->bitrate = $bitrate . 'k';
            $videoOptions->bufferSize = (2 * $bitrate) . 'k';
            $videoOptions->keyframeInterval = 15;
        }
        else {
            $bitrate = 1200 + (($videoQualitySetting - 60) / 40) * 800;
            
            $videoOptions->maxSize = 960;
            $videoOptions->bitrate = $bitrate . 'k';
            $videoOptions->bufferSize = (2 * $bitrate) . 'k';
            $videoOptions->keyframeInterval = 5;
        }

        $encoder = new VideoEncoder($ffmpeg, $ffprobe);
        $cameraSettings = $userSettings->get('Cameras');

        foreach ($report->videosToEncode as $video) {
            $currVideoMetadata = [];
            $outputPath = $this->workingDirectory . $video['name'];
            $originalFilename = pathinfo($video['input'])['filename'];
            $parts = explode('_', $originalFilename);
            $cameraId = end($parts);

            $videoOptions->rotate = 0;

            if (!empty($cameraSettings[$cameraId])) {
                if (!empty($cameraSettings[$cameraId]['Rotation'])) {
                    $videoOptions->rotate = intval($cameraSettings[$cameraId]['Rotation']);
                }

                if (!empty($cameraSettings[$cameraId]['Group'])) {
                    $currVideoMetadata['group'] = $cameraSettings[$cameraId]['Group'];
                }
            }
            
            // Apply cropping.
            $videoOptions->startOffset = $video['startOffset'];
            $videoOptions->duration    = $video['duration'];
            $videoOptions->overwrite   = false;
            
            // Determine if the video should be overwritten by checking the difference in video durations and start times
            $outputPathWithExt = $outputPath . '.mp4';
            if (file_exists($outputPathWithExt)) {
                // Grab duration and metadata from existing video file
                $oldDuration = $ffmpeg->getDuration($outputPathWithExt);
                $metadata    = $ffmpeg->getMetadata($outputPathWithExt);
                $oldStart    = null;

                $durationDiff = abs($oldDuration - $videoOptions->duration);
                
                // Check if the metadata contains the start time
                if (isset($metadata['comment'])) {
                    $tmp = explode('start\=', $metadata['comment']);
                    
                    if (count($tmp) > 1) {
                        $oldStart = $tmp[1];
                    }
                }
                
                // Assume that the video should be re-encoded if
                // - duration has changed more than this threshold
                // - the start offset has changed since the video was encoded
                if ($durationDiff > 0.03 || $oldStart != $videoOptions->startOffset) {
                    $videoOptions->overwrite = true;
                }
            }
            
            $videoOptions->metadata = [
                'comment' => 'start=' . $videoOptions->startOffset
            ];

            $encodeResult = $encoder->encodeVideo($video['input'], $outputPath, $videoOptions);

            // Add to list of assets to upload
            // if ($encodeResult['result'] !== 0) {
                $report->assetsToUpload[] = $outputPath . '.mp4';
            // }

            if (!empty($encodeResult['encodeOptions'])) {
                if (!empty($encodeResult['encodeOptions']['outRes'])) {
                    $resolution = $encodeResult['encodeOptions']['outRes'];
                    $currVideoMetadata['width'] = $resolution[0];
                    $currVideoMetadata['height'] = $resolution[1];
                }
            }
            
            foreach ($report->reportData['measurements'] as &$currMeasurement) {
                if (!empty($currMeasurement['resources'])) {
                    // Find current video.
                    foreach ($currMeasurement['resources'] as &$res) {
                        if ($res['name'] === $video['name']) {
                            foreach ($currVideoMetadata as $key=>$metaData) {
                                $res[$key] = $metaData;
                            }

                            break;
                        }
                    }
                }
            }

            // Update measurement resource metadata.
            file_put_contents($this->workingDirectory . 'measurement.json', json_encode($report->reportData['measurements']));

            $videoMetadata[$video['name']] = $currVideoMetadata;
        };

        $generatedBy = [];

        if (!empty($versionData)) {
            if (!empty($versionData['Paf'])) {
                $pafData = $versionData['Paf'];

                $generatedBy[] = [
                    'type'    => 'paf',
                    'name'    => $pafData['PackageName'],
                    'version' => $pafData['Major'] . '.' . $pafData['Minor'] . '.' . $pafData['Patch'] . '+' . $pafData['Build']
                ];
            }
            
            if (!empty($versionData['Qtm'])) {
                $qtmData = $versionData['Qtm'];

                $generatedBy[] = [
                    'type'    => 'qtm',
                    'name'    => 'Qualisys Track Manager',
                    'version' => $qtmData['Major'] . '.' . $qtmData['Minor'] . '+' . $qtmData['Build']
                ];
            }
        }

        $report->reportData['metadata'] = array_merge($report->reportData['metadata'], [
            'isUsingStandardUnits' => true,
            'generatedBy' => $generatedBy,
            'customFields' => []
        ]);

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
            'Directory pattern', 
            'Event mode', 
            'External aid', 
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
            'Prothesis_Orthosis', 
            'Right foot normalised to static trial', 
            'Test condition', 
            'Test operator', 
            'Type', 
            'Weight'
        ];
        $customFieldsSource =  $this->qtmData['subsession']->fields;

        foreach ($customFields as $field) {
            $report->reportData['metadata']['customFields'][] = [
                'id' => $field,
                'value' => $customFieldsSource[$field],
                'type' => 'string' // TODO: Use actual type
            ];
        }

        // Manually add data from subject & session.
        $report->reportData['metadata']['customFields'][] = [
            'id' => 'Date of birth',
            'value' => $this->qtmData['subject']->fields['Date of birth'],
            'type' => 'string'
        ];

        $report->reportData['metadata']['customFields'][] = [
            'id' => 'Sex',
            'value' => $this->qtmData['subject']->fields['Sex'],
            'type' => 'string'
        ];

        $report->reportData['metadata']['customFields'][] = [
            'id' => 'Gross Motor Function Classification',
            'value' => $this->qtmData['session']->fields['Gross Motor Function Classification'],
            'type' => 'string'
        ];

        $report->reportData['metadata']['customFields'][] = [
            'id' => 'Functional Mobility Scale',
            'value' => $this->qtmData['session']->fields['Functional Mobility Scale'],
            'type' => 'string'
        ];

        $report->reportData['clientId'] = $versionData['UserName'];
        $report->reportData['subject'] = [
            'id' => $this->qtmData['subject']->fields['Patient ID'] . '_' . $this->qtmData['subject']->fields['Date of birth'],
            'displayName' => $this->qtmData['subject']->fields['First name'] . ' ' . $this->qtmData['subject']->fields['Last name']
        ];

        $projectSubType = $this->qtmData['subsession']->fields['Type'];
        
        $report->reportData['project'] = [
            'type' => 'Gait',
            'subtype' => $projectSubType
        ];

        file_put_contents($this->workingDirectory . 'report-data.json', json_encode($report->reportData));

        // Get upload token.
        $reportServerUrlEnv = getenv('PAF_GAIT_REPORT_SERVER_API_URL');
        $reportServerUrl = (empty($reportServerUrlEnv)) ? $userSettings->get('Report server url') : $reportServerUrlEnv;
       
        $tokenClient = new GuzzleHttp\Client(['base_url' => $reportServerUrl]);
        
        // Fix for potential SSL certificate issues.
        $tokenClient->setDefaultOption('verify', false);

        $tokenRes = $tokenClient->post('/api/v2/auth/upload/token', $postOptions = ['json' => ['clientId' => $versionData['UserName']]]);
        $tokenResJson = $tokenRes->json();

        if (!$tokenResJson || $tokenResJson['success'] != true || empty($tokenResJson['jwt'])) {
            throw new \Exception('Unable to authorize the upload. Please check your internet connection and try again.');
            return;
        }

        $uploadToken = $tokenResJson['jwt'];

        $client = new GuzzleHttp\Client([
            'base_url' => $reportServerUrl,
            'defaults' => [
                'headers' => [
                    'Authorization' => 'Bearer ' . $uploadToken
                ]
            ]
        ]);
        
        // Fix for potential SSL certificate issues.
        $client->setDefaultOption('verify', false);
        
        try {
            $res = $client->post('/api/v2/report/', $postOptions = ['json' => $report->reportData]);
            $reportId = $res->json()['id'];

            if (isset($report->assetsToUpload) && count($report->assetsToUpload) > 0) {
                // Prepare asset upload body (for multipart form).
                $filesBody = [];

                foreach ($report->assetsToUpload as $index=>$asset) {
                    if (is_array($asset) && $asset['path'] && $asset['filename']) {
                        // Upload file with specified filename.
                        $filesBody['file_' . $index] = new PostFile('file_' . $index, fopen($asset['path'], 'r'), $asset['filename']);
                    }
                    else {
                        $filesBody['file_' . $index] = fopen($asset, 'r');
                    }
                }
                
                // Upload external assets.
                $client->post('/api/v2/report/' . $reportId . '/resource', [
                    'body' => $filesBody
                ]);
            }

            $dest = $reportServerUrl . '/claim/' . $reportId;
            exec('explorer "' . $dest . '"');
        }
        catch(\Exception $e){
            throw new \Exception('No internet connection. Please check your internet connection and try again. ' . $e);
        }
    }
}
?>