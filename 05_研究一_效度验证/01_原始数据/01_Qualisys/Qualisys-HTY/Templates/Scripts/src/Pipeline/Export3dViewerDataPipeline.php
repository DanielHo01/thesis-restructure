<?php
namespace Qualisys\Gait\Pipeline;

use Qualisys\PafToolbelt\Util\ParseNumbers;

class Export3dViewerDataPipeline extends Pipeline {
	public function run() {
		$dynamicMeasurements = array_filter(array_map(function($m) { return $m->fields['Measurement type'] === 'Dynamic' ? $m : null; }, $this->qtmData['measurements']), function($m) { return null !== $m; });
	
		foreach ($dynamicMeasurements as $m) {
			$basename        = basename($m->name, '.qtm');
			$xml             = new \SimpleXMLElement(file_get_contents($this->workingDirectory . $basename . '-3d-data.xml'));
			$c3d             = $basename . '.c3d';
			$c3dXml          = $xml->xpath("/v3d/owner[@value='" . $c3d . "']")[0];
			$startTime       = (float)$c3dXml->xpath("type[@value='METRIC']/folder[@value='PROCESSED']/name[@value='startTime']/component/@data")[0];
			$endTime         = (float)$c3dXml->xpath("type[@value='METRIC']/folder[@value='PROCESSED']/name[@value='endTime']/component/@data")[0];
			$uncroppedLength = (float)$c3dXml->xpath("type[@value='METRIC']/folder[@value='PROCESSED']/name[@value='Uncropped Measurement Length']/component/@data")[0];
			$frameRate		 = (float)$c3dXml->xpath("type[@value='METRIC']/folder[@value='PROCESSED']/name[@value='Frame Rate']/component/@data")[0];
			$framesTotal     = (float)$c3dXml->xpath("type[@value='TARGET']/folder/name/component/@frames")[0];
			$components      = [];
			$labels          = [];
			$bones           = [];
			$frames          = [];
			$targetNames     = [];
			$segmentNames    = [];
			$segmentPosNames = [];
			$segmentRotNames = [];
			$fpNames         = [];
			$markers         = [];
			$segments        = [];
			$segmentPos      = [];
			$segmentRot      = [];
			$plates          = [];
			$force           = [];
			$data            = [];
			
			if(empty($c3dXml->xpath("type[@value='TARGET']/folder")[0]))
				continue;
			
			// Marker coordinates.
			foreach ($c3dXml->xpath("type[@value='TARGET']/folder")[0]->children() as $child) {
				$targetName = (string)$child['value'];
				$targetNames[] = $targetName;

				foreach ($child->children() as $kid) {
					$component = (string)$kid['value'];
					$data = (string)$kid['data'];
					$components[$targetName][$component] = ParseNumbers::toFloats($data);
				}
			}

			// Segment positions.
			foreach ($c3dXml->xpath("type[@value='DERIVED']/folder[@value='SEG_POS']")[0]->children() as $child) {
				$segmentPosName = (string)$child['value'];
				$segmentPosNames[] = $segmentPosName;

				foreach ($child->children() as $kid) {
					$component = (string)$kid['value'];
					$data = (string)$kid['data'];
					$segPosComponents[$segmentPosName][$component] = ParseNumbers::toFloats($data);
				}
			}

			// Segment rotations.
			foreach ($c3dXml->xpath("type[@value='DERIVED']/folder[@value='SEG_ROT']")[0]->children() as $child) {
				$segmentRotName = (string)$child['value'];
				$segmentRotNames[] = $segmentRotName;

				foreach ($child->children() as $kid) {
					$component = (string)$kid['value'];
					$data = (string)$kid['data'];
					$segRotComponents[$segmentRotName][$component] = ParseNumbers::toFloats($data);
				}
			}

			// Force data.
			if (!empty($c3dXml->xpath("type[@value='DERIVED']/folder[@value='FORCE']")[0])) {
				foreach ($c3dXml->xpath("type[@value='DERIVED']/folder[@value='FORCE']")[0]->children() as $child) {
					$fpName = (string)$child['value'];
					$fpNames[] = $fpName;

					foreach ($child->children() as $kid) {
						$component = (string)$kid['value'];
						$data = (string)$kid['data'];

						$fpForceComponents[$fpName][$component] = ParseNumbers::toFLoats($data);
					}
				}
			}
			
			for ($i = 0; $i <= $framesTotal - 1; $i++) {
				$markers = [];
				$segmentPos = [];
				$segmentRot = [];
				$forceFrames = [];
				$forceplates = [];

				foreach ($targetNames as $name) {
					$markers[] = [$components[$name]['X'][$i], $components[$name]['Y'][$i], $components[$name]['Z'][$i]];
				}

				foreach ($segmentPosNames as $name) {
					$segmentPos[] = [$segPosComponents[$name]['X'][$i], $segPosComponents[$name]['Y'][$i], $segPosComponents[$name]['Z'][$i]];
				}

				foreach ($segmentRotNames as $name) {
					$segmentRot[] = [$segRotComponents[$name]['X'][$i], $segRotComponents[$name]['Y'][$i], $segRotComponents[$name]['Z'][$i]];
				}

				foreach ($fpNames as $name) {
					$fpId = floatval(filter_var($name, FILTER_SANITIZE_NUMBER_INT));
					$dataName = str_replace('FP' . $fpId . '_FORCE', 'force', $name);
					$dataName = str_replace('FP' . $fpId . '_FREEMOMENT', 'moment', $dataName);
					$dataName = str_replace('FP' . $fpId . '_COP', 'position', $dataName);

					if (strpos($name, 'FP' . $fpId) !== false) {
						$forceFrames[$dataName] = [];

						foreach (['X', 'Y', 'Z'] as $c) 
							$forceFrames[$dataName][] = floatval($fpForceComponents[$name][$c][$i]);
							
						if (strpos($name, 'COP') === false)
							continue;
					}

					$forceplates[] = [
						'id' => $fpId,
						'forceCount' => 1,
						'forcenumber' => 2951,
						'data' => $forceFrames,
					];

					if (strpos($name, 'COP') !== false)
						$forceFrames = [];
				}

				$frames[] = [
					'frame' => $i + 1,
					'markers' => $markers,
					'segmentPos' => $segmentPos,
					'segmentRot' => $segmentRot,
					'force' => $forceplates
				];
			}

			// Marker names.
			foreach ($targetNames as $name)
				$labels[] = ['name' => $name ];

			// Bones
			if (strpos($this->session->subsessions[0]['Model_used'],'CAST') !== false)
			//if (in_array('L_TH1', $targetNames)) //CAST
				$bones = [['L_HEAD', 'SGL'], ['R_HEAD', 'SGL'], ['CV7', 'TV10'], ['R_SIA', 'L_SIA'], ['L_IAS', 'L_IPS'], ['L_IPS', 'R_IPS'], ['R_IPS', 'R_IAS'], ['L_USP', 'L_HM2'], ['L_RSP', 'L_USP'], ['L_USP', 'L_HLE'], ['L_HLE', 'L_SAE'], ['L_SAE', 'R_SAE'], ['R_SAE', 'R_HLE'], ['R_HLE', 'R_USP'], ['R_USP', 'R_RSP'], ['R_USP', 'R_HM2'], ['L_TH1', 'L_TH2'], ['L_TH2', 'L_TH3'], ['L_TH3', 'L_TH4'], ['L_SK1', 'L_SK2'], ['L_SK2', 'L_SK3'], ['L_SK3', 'L_SK4'], ['L_FCC', 'L_FM1'], ['L_FM1', 'L_FM2'], ['L_FM2', 'L_FM5'], ['R_TH1', 'R_TH2'], ['R_TH2', 'R_TH3'], ['R_TH3', 'R_TH4'], ['R_SK1', 'R_SK2'], ['R_SK2', 'R_SK3'], ['R_SK3', 'R_SK4'], ['R_FCC', 'R_FM1'], ['R_FM1', 'R_FM2'], ['R_FM2', 'R_FM5']];
			elseif (strpos($this->session->subsessions[0]['Model_used'],'CGM') !== false)
			//else if (in_array('L_FHD', $targetNames) && in_array('L_TIB',$targetNames)) //CGM
				$bones = [['L_FHD', 'L_BHD'], ['L_BHD', 'R_BHD'], ['R_BHD', 'R_FHD'], ['CV7', 'TV10'], ['SXS', 'SJN'], ['SJN', 'CV7'], ['R_SIA', 'L_SIA'], ['L_IAS', 'L_IPS'], ['L_IPS', 'R_IPS'], ['R_IPS', 'R_IAS'], ['L_USP', 'L_HM2'], ['L_RSP', 'L_USP'], ['L_USP', 'L_HLE'], ['L_HLE', 'L_SAE'], ['L_SAE', 'R_SAE'], ['R_SAE', 'R_HLE'], ['R_HLE', 'R_USP'], ['R_USP', 'R_RSP'], ['R_USP', 'R_HM2'], ['L_IAS', 'L_FLE'], ['L_FLE', 'L_FAL'], ['L_FCC', 'L_FM2'], ['L_FM2', 'L_FM5'], ['R_IAS', 'R_FLE'], ['R_FLE', 'R_FAL'], ['R_FCC', 'R_FM2'], ['R_FM2', 'R_FM5']];
			elseif (strpos($this->session->subsessions[0]['Model_used'],'IOR') !== false)
			//else if (in_array('SXS', $targetNames) && in_array('MAI', $targetNames)) //IOR
				$bones = [['L_HEAD', 'SGL'], ['R_HEAD', 'SGL'], ['SXS', 'SJN'], ['SJN', 'CV7'], ['CV7', 'TV2'], ['TV2', 'MAI'], ['MAI', 'LV1'], ['LV1', 'LV3'], ['LV3', 'LV5'], ['L_USP', 'L_HM2'], ['L_RSP', 'L_USP'], ['L_USP', 'L_HLE'], ['L_HLE', 'L_SAE'], ['L_SAE', 'R_SAE'], ['R_SAE', 'R_HLE'], ['R_HLE', 'R_USP'], ['R_USP', 'R_RSP'], ['R_USP', 'R_HM2'], ['L_IAS', 'L_IPS'], ['L_IPS', 'R_IPS'], ['R_IPS', 'R_IAS'], ['L_FTC', 'L_FLE'], ['L_FLE', 'L_FAX'], ['L_FAX', 'L_TTC'], ['L_TTC', 'L_FAL'], ['L_FAL', 'L_FCC'], ['L_FCC', 'L_FM1'], ['L_FM1', 'L_FM5'], ['R_FTC', 'R_FLE'], ['R_FLE', 'R_FAX'], ['R_FAX', 'R_TTC'], ['R_TTC', 'R_FAL'], ['R_FAL', 'R_FCC'], ['R_FCC', 'R_FM1'], ['R_FM1', 'R_FM5']];
			elseif (strpos($this->session->subsessions[0]['Model_used'],'WR') !== false) //Walter Reed
				$bones = [['FHD', 'LHD'],['FHD', 'RHD'],['LHD','BHD'],['RHD','BHD'],['C7','T10'],['C7','NOTCH'],['NOTCH','XIPH'],['LSHO','RSHO'],['LSHO','LELBL'],['LELBL','LWRU'],['LWRU','LWRR'],['RSHO','RELBL'],['RELBL','RWRU'],['RWRU','RWRR'],['LASIS','LPSIS'],['LPSIS','RPSIS'],['RPSIS','RASIS'],['LTH1','LTH2'],['LTH2','LTH3'],['LTH3','LTH4'],['LSK1','LSK2'],['LSK2','LSK3'],['LSK3','LSK4'],['LHEE','L5MT'],['L5MT','LTOE'],['RTH1','RTH2'],['RTH2','RTH3'],['RTH3','RTH4'],['RSK1','RSK2'],['RSK2','RSK3'],['RSK3','RSK4'],['RHEE','R5MT'],['R5MT','RTOE']];
			elseif (strpos($this->session->subsessions[0]['Model_used'],'SCHH') !== false) //Seattle Children's
				$bones = [['R_HGT','L_HGT'],['R_HGT','R_HLT'],['L_HGT','L_HLT'],['R_HGT','R_HLE'],['R_HLE','R_USP'],['R_USP','R_HM5'],['R_HM5','R_HM2'],['L_HGT','L_HLE'],['L_HLE','L_USP'],['L_USP','L_HM5'],['L_HM5','L_HM2'],['L_IAS','L_IPS'],['L_IPS','R_IPS'],['R_IPS','R_IAS'],['R_IAS','R_FLE'],['R_FLE','R_TIB'],['R_TIB','R_FAL'],['R_FCC','R_FM2'],['R_FM2','R_FM5'],['L_IAS','L_FLE'],['L_FLE','L_TIB'],['L_TIB','L_FAL'],['L_FCC','L_FM2'],['L_FM2','L_FM5']];
			else //CAST
				$bones = [['L_HEAD', 'SGL'], ['R_HEAD', 'SGL'], ['CV7', 'TV10'], ['R_SIA', 'L_SIA'], ['L_IAS', 'L_IPS'], ['L_IPS', 'R_IPS'], ['R_IPS', 'R_IAS'], ['L_USP', 'L_HM2'], ['L_RSP', 'L_USP'], ['L_USP', 'L_HLE'], ['L_HLE', 'L_SAE'], ['L_SAE', 'R_SAE'], ['R_SAE', 'R_HLE'], ['R_HLE', 'R_USP'], ['R_USP', 'R_RSP'], ['R_USP', 'R_HM2'], ['L_TH1', 'L_TH2'], ['L_TH2', 'L_TH3'], ['L_TH3', 'L_TH4'], ['L_SK1', 'L_SK2'], ['L_SK2', 'L_SK3'], ['L_SK3', 'L_SK4'], ['L_FCC', 'L_FM1'], ['L_FM1', 'L_FM2'], ['L_FM2', 'L_FM5'], ['R_TH1', 'R_TH2'], ['R_TH2', 'R_TH3'], ['R_TH3', 'R_TH4'], ['R_SK1', 'R_SK2'], ['R_SK2', 'R_SK3'], ['R_SK3', 'R_SK4'], ['R_FCC', 'R_FM2'], ['R_FM2', 'R_FM5']];
			
			// Segment lengths.
			foreach ($c3dXml->xpath("type[@value='METRIC']/folder[@value='SEG_LENGTH']")[0]->children() as $child) {
				$segmentName = (string)$child['value'];
				$segmentNames[] = $segmentName;

				foreach ($child->children() as $kid) {
					$data = (string)$kid['data'];
					$length[$segmentName] = $data;
				}
			}

			foreach ($segmentNames as $name) {
				$segmentLength = floatval($length[$name]);
				$segments[] = [
					'name' => $name,
					'length' => $segmentLength
				];
			}

			// Force plates.
			$forcePlates = [];
			if (!empty($c3dXml->xpath("type[@value='DERIVED']/folder[@value='FORCE']")[0])) {
				foreach ($c3dXml->xpath("type[@value='METRIC']/folder[@value='FORCE']")[0]->children() as $child) {
					$name = (string)$child['value'];

					if (strpos($name, 'CORNER') !== false) {
						$fpId = floatval(filter_var(explode('_', $name)[0], FILTER_SANITIZE_NUMBER_INT));
						$corner = [];

						if (empty($forcePlates[$fpId])) {
							$forcePlates[$fpId] = ['corners' => []];
						}

						foreach ($child->children() as $c) {
							$corner[] = (floatval($c['data']));
						}

						$forcePlates[$fpId]['corners'][] = $corner;
					}
					else {
						$fpId = floatval(filter_var($name, FILTER_SANITIZE_NUMBER_INT));
						if (empty($forcePlates[$fpId])) {
							$forcePlates[$fpId] = [];
						}
					}

					if (strpos($name, 'length_FP' . $fpId) !== false)
						$forcePlates[$fpId]['length'] = floatval($child->component['data']);

					if (strpos($name, 'width_FP' . $fpId) !== false)
						$forcePlates[$fpId]['width'] = floatval($child->component['data']);

					if (strpos($name, 'ORIGIN_FP' . $fpId) !== false) {
						$forcePlates[$fpId]['origin'] = [];

						foreach ($child->children() as $kid) {
							$component = (string)$kid['value'];
							$data = floatval($kid['data']);
							$forcePlates[$fpId]['origin'][$component] = $data;
						}
					}
				}
			
				foreach ($forcePlates as $id => $f) {
					$plate = [
						'id'     => $id,
						'name'   => '',
					];

					if (isset($f['corners'])) {
						$plate['corners'] = $f['corners'];
					}
					else if (isset($f['origin'])) {
						$plate['origin'] = [$f['origin']['X'], $f['origin']['Y'], $f['origin']['Z']];
						$plate['length'] = $f['length'];
						$plate['width'] = $f['width'];
					}

					$plates[] = $plate;
				}

				$force = [
					'lengthUnit' => 'mm',
					'forceUnit'  => 'F',
					'plates'     => $plates,
				];
			}

			// Build json.
			if (!empty($c3dXml->xpath("type[@value='DERIVED']/folder[@value='FORCE']")[0])) {
				$data = [
					'startTime'       => $startTime,
					'endTime'         => $endTime,
					'frameRate'       => $frameRate,
					'uncroppedLength' => $uncroppedLength,
					'labels'          => $labels,
					'bones'           => $bones,
					'force'           => $force,
					'segments'        => $segments,
					'frames'          => $frames,
				];
			}
			else {
				$data = [
					'startTime'       => $startTime,
					'endTime'         => $endTime,
					'frameRate'       => $frameRate,
					'uncroppedLength' => $uncroppedLength,
					'labels'          => $labels,
					'bones'           => $bones,
					'segments'        => $segments,
					'frames'          => $frames,
				];
			}

			$output = json_encode($data);
			$outputFilename = $basename . '-3d-data.json';
			file_put_contents($this->workingDirectory . $outputFilename, $output);

			$resources = $m->fields['Resources'];
			if (empty($resources)) {
				$resources = [];
			}
			
			// Replace any existing 3d data resources with new ones.
			$resources = array_values(array_filter($resources, function($r) { return $r['type'] !== '3d-data'; }));
			$resources[] = ['type' => '3d-data', 'src' => $outputFilename];

			$m->fields['Resources'] = json_encode($resources);
			$m->save();
		}
	}
}
?>
