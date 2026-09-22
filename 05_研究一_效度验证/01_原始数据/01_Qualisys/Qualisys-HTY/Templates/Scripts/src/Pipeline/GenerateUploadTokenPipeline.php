<?php
namespace Qualisys\Gait\Pipeline;

use GuzzleHttp;

class GenerateUploadTokenPipeline extends Pipeline {
	public function run($tokenPath) {
        $userSettings = $this->options['userSettings'];
        $versionData = $this->options['versionData'];
        $analysisFiles = [$this->workingDirectory . 'session_data.xml'];

        $clientId = $versionData['UserName'];

		// Get upload token.
		$reportServerUrlEnv = getenv('PAF_GAIT_REPORT_SERVER_API_URL');
		$reportServerUrl = (empty($reportServerUrlEnv)) ? $userSettings->get('Report server url') : $reportServerUrlEnv;
	
		$tokenClient = new GuzzleHttp\Client(['base_url' => $reportServerUrl]);
		
		// Fix for potential SSL certificate issues.
		$tokenClient->setDefaultOption('verify', false);

		$tokenRes = $tokenClient->post('/api/v2/auth/upload/token', $postOptions = ['json' => ['clientId' => $clientId]]);
		$tokenResJson = $tokenRes->json();

		if (!$tokenResJson || $tokenResJson['success'] != true || empty($tokenResJson['jwt'])) {
			throw new \Exception('Unable to authorize the upload. Please check your internet connection and try again.');
			return;
		}

		$uploadToken = $tokenResJson['jwt'];

		$configFile = [
			'baseUrl'  => $reportServerUrl,
			'clientId' => $clientId,
			'token'    => $uploadToken
		];

		file_put_contents($tokenPath, json_encode($configFile));
    }
}
?>