<?php
namespace Qualisys\Gait\Analysis;

use Qualisys\PafToolbelt\Models\Analysis;
use Qualisys\PafToolbelt\Models\ResourceSet;
use Qualisys\PafToolbelt\Models\ReportData;
use Qualisys\PafToolbelt\Util\Ffmpeg;
use Qualisys\PafToolbelt\Util\Ffprobe;
use Qualisys\PafToolbelt\Util\VideoEncoderOptions;
use Qualisys\PafToolbelt\Util\VideoEncoder;

class EncodeVideoAnalysis extends Analysis {
	public function run() {
		// Set env var used by VideoResource.
		putenv('FFMPEG=' . $this->templateDirectory . 'Assets/Programs/ffmpeg/bin/ffmpeg.exe');

		$ffmpeg         = new Ffmpeg($this->templateDirectory . 'Assets/Programs/ffmpeg/bin/ffmpeg.exe');
		$ffprobe        = new Ffprobe($this->templateDirectory . 'Assets/Programs/ffmpeg/bin/ffprobe.exe');
        $encoder        = new VideoEncoder($ffmpeg, $ffprobe);
        $userSettings   = $this->options['userSettings'];
        $measurements   = array_filter($this->qtmData['measurements'], function($m) { return $m->fields['Measurement type'] !== 'Static'; });
		$resources      = new ResourceSet(['video' => glob($this->workingDirectory . '/*.avi')], $measurements, true);
		$cameraSettings = $userSettings->get('Cameras');

		// Set camera settings on video resource based on matching camera id.
		$resources->applyCameraSettings($userSettings->get('Cameras'));
			
        foreach ($resources->toArray() as $videoResource) {
			if ($videoResource->type !== 'video') {
				continue;
			}

			$videoOptions = $this->getVideoEncoderOptions();
			$videoOptions->rotate = $videoResource->rotation === null ? 0 : $videoResource->rotation;

			// Apply cropping.
			list($duration, $startOffset) = $this->getMeasurementCrop($videoResource->measurement);

			if (($duration === 0 || !empty($duration)) && ($startOffset === 0 || !empty($startOffset))) {
				$videoOptions->startOffset = $startOffset;
				$videoOptions->duration    = $duration;
			}
			else {
				$videoOptions->duration = $videoResource->duration;
			}
			
            // Determine if the video should be overwritten by checking the
            // difference in video durations and start times.
            if (file_exists($videoResource->outputPath)) {
                // Grab duration and metadata from existing video file.
                $oldDuration  = $ffmpeg->getDuration($videoResource->outputPath);
				$metadata     = $ffmpeg->getMetadata($videoResource->outputPath);
				$oldStart     = null;
                $durationDiff = abs($oldDuration - $videoOptions->duration);
                
                // Check if the metadata contains the start time.
                if (isset($metadata['comment'])) {
                    $tmp = explode('start\=', $metadata['comment']);
                    
                    if (count($tmp) > 1) {
                        $oldStart = floatval($tmp[1]);
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

			$encodeResult = $encoder->encodeVideoResource($videoResource, $videoOptions);
            $videoResource->encodeResult  = $encodeResult;
            $videoResource->encodeOptions = $videoOptions;
        };

        file_put_contents($this->workingDirectory . 'videos.json', json_encode($resources->toArray(), JSON_PRETTY_PRINT));
    }

	private function getMeasurementCrop($measurement) {
		foreach ($this->qtmData['measurements'] as $m) {
			if ($m->fields['Filename'] === $measurement) {
				$viewerFile = $this->workingDirectory . pathinfo($measurement, PATHINFO_FILENAME) . '-3d-data.json';

				if (file_exists($viewerFile)) {
					$viewerData       = json_decode(file_get_contents($viewerFile), true);
					$startTime        = $viewerData['startTime'];
					$endTime          = $viewerData['endTime'];
					$originalDuration = $viewerData['uncroppedLength'];
					$frameRate        = $viewerData['frameRate'];
					$duration         = (!empty($endTime) && !empty($startTime)) ? $endTime - $startTime: null;
					$startOffset      = $startTime;

					return [$duration, $startOffset];
				}
			}
		}
	}
	
	private function getVideoEncoderOptions() {
		$userSettings = $this->options['userSettings'];
        $videoQuality = min(100, max(1, intval($userSettings->get('Video quality', 50))));
        $videoOptions = new VideoEncoderOptions();

        $videoOptions->codec = 'libx264 -profile:v baseline -level 3.0';
        $videoOptions->keepAspectRatio = true;
        $videoOptions->videoExtension = 'mp4';
        $videoOptions->fps = null;

        if ($videoQuality < 30) {
            $bitrate = 300 + ($videoQuality / 30) * 300;

            $videoOptions->maxSize = 480;
            $videoOptions->bitrate = $bitrate . 'k';
            $videoOptions->bufferSize = (2 * $bitrate) . 'k';
            $videoOptions->keyframeInterval = 25;
            $videoOptions->fps = 25;
        }
        else if ($videoQuality >= 30 && $videoQuality < 60) {
            $bitrate = 700 + (($videoQuality - 30) / 30) * 300;
            
            $videoOptions->maxSize = 720;
            $videoOptions->bitrate = $bitrate . 'k';
            $videoOptions->bufferSize = (2 * $bitrate) . 'k';
            $videoOptions->keyframeInterval = 15;
        }
        else {
            $bitrate = 1200 + (($videoQuality - 60) / 40) * 800;
            
            $videoOptions->maxSize = 960;
            $videoOptions->bitrate = $bitrate . 'k';
            $videoOptions->bufferSize = (2 * $bitrate) . 'k';
            $videoOptions->keyframeInterval = 5;
		}
		
		return $videoOptions;
	}
}
?>