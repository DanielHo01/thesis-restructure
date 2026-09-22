<?php

namespace Qualisys\Gait\Pipeline;

use Qualisys\PafToolbelt\Util\Ffmpeg;

class EncodeVideoPipeline extends Pipeline {

	public function run() {
		$ffmpeg              = new Ffmpeg($this->templateDirectory . 'Assets/Programs/ffmpeg/bin/ffmpeg.exe');
		$dynamicMeasurements = array_filter(array_map(function($m) { return $m->fields['Measurement type'] === 'Dynamic' ? $m : null; }, $this->qtmData['measurements']), function($m) { return null !== $m; });
	
		foreach ($dynamicMeasurements as $m) {
			$basename         = basename($m->name, '.qtm');
			$jsonFile		  = $this->workingDirectory . $basename . '-3d-data.json';
			
			if (!file_exists($jsonFile)) {
				continue;
			}
			
			$jsonStr          = file_get_contents($this->workingDirectory . $basename . '-3d-data.json');
			$data             = json_decode($jsonStr, true);
			$originalDuration = $data['uncroppedLength'];
			$newResources     = [];
			$resources        = $m->fields['Resources'];
			
			if (empty($resources)) {
				$resources = [];
			}

			$videos = $this->options['videos'];

			$videoBasename = $basename;

			$allVideoFiles = glob($this->workingDirectory . '*.avi');
			preg_match('/(.+)( -\D+)(\d+)/', $basename, $fileNameParts);

			if (count($fileNameParts) >= 4) {
				$searchPattern = '/.+(' . $fileNameParts[1] . '[^_]* ' . $fileNameParts[3] . ')\D.+\.avi/';
				
				// Find first match and figure out video base name from it
				foreach ($allVideoFiles as $videoFile) {
					if (preg_match($searchPattern, $videoFile, $baseNameMatches)) {
						if (count($baseNameMatches) >= 2) {
							$videoBasename = $baseNameMatches[1];
						}
						
						break;
					}
				}
			}
				
			// Set srcSuffix dynamically if set to 'default'.
			foreach (['sagittal', 'frontal'] as $i => $key) {
				if ($videos[$key]['srcSuffix'] === 'default') {
					$files = glob($this->workingDirectory . $videoBasename . '*.avi');
					
					// Take the first file for the sagittal video and the last for the frontal.
					$videos[$key]['srcSuffix'] = substr(basename($files[($i === 0 ? 0 : count($files) - 1)]), strlen($videoBasename));
				}
			}
			
			foreach ($videos as $key => $video) {
				$startOffset = $video['startOffset'] - $data['startTime'];
				$start       = $startOffset >= 0 ? 0 : -$startOffset;
				$duration    = $originalDuration - $start - max($video['startOffset'], $originalDuration - $data['endTime'], 0);
				$outputName  = $videoBasename . $video['outputSuffix'] . '.mp4';
				$overwrite   = false;
				
				if (file_exists($this->workingDirectory . $outputName)) {
					$oldDuration = $ffmpeg->getDuration($this->workingDirectory . $outputName);
					$metadata = $ffmpeg->getMetadata($this->workingDirectory . $outputName);
					$oldStart = null;
					
					if (isset($metadata['comment'])) {
						$tmp = explode('start\=', $metadata['comment']);
						
						if (count($tmp) > 1) {
							$oldStart = $tmp[1];
						}
					}
					
					$durationDiff = abs($oldDuration - $duration);
					echo $videoBasename . ': ' . "\n";
					echo 'old duration: ' . $oldDuration . "\n";
					echo 'new duration: ' . $duration . "\n";
					echo "\n\n";
					
					// Assume that the video should be re-encoded if
					// - duration has changed more than this threshold
					// - the start offset has changed since the video was encoded
					if ($durationDiff > 0.03 || $oldStart != $start) {
						$overwrite = true;
					}
				}
				
				if ($ffmpeg->webEncode([
					'input'            => $this->workingDirectory . $videoBasename . $video['srcSuffix'],
					'output'           => $this->workingDirectory . pathinfo($outputName, PATHINFO_FILENAME),
					'outFps'           => 50,
					'overwrite'        => $overwrite,
					'startTime'        => $start,
					'duration'         => $duration,
					'keyframeInterval' => 5,
					'bufsize'          => '4000k',
					'bitRate'          => '4000k',
					'metadata'         => ['comment' => 'start=' . $start]
				]) === 0) {
					$newResources[] = ['type' => 'video', 'src' => $outputName];
				}
			}

			// Replace any existing video resources with new ones.
			$resources = array_values(array_filter($resources, function($r) { return $r['type'] !== 'video'; }));
			$resources = array_merge($resources, $newResources);

			$m->fields['Resources'] = json_encode($resources);
			$m->save();
		}
	}
}
?>
