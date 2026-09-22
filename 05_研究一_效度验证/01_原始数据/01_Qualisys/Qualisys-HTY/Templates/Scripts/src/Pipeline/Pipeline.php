<?php
namespace Qualisys\Gait\Pipeline;

abstract class Pipeline {
	public function __construct($session, $qtmVars, $qtmData, $options = null) {
		$this->session           = $session;
		$this->qtmVars           = $qtmVars;
		$this->templateDirectory = $qtmVars['templateDirectory'];
		$this->workingDirectory  = $qtmVars['workingDirectory'];
		$this->measurementGuids  = $qtmVars['measurementGuids'];
		$this->typeGuids         = $qtmVars['typeGuids'];
		$this->cmoFile           = $qtmVars['cmoFile'];
		$this->qtmData           = $qtmData;
		$this->measurements      = $qtmData['measurements'];
		$this->options           = $options;
	}
}
?>