<?php
namespace Qualisys\Gait\Pipeline;

use Qualisys\Gait\Pipeline\ExportVideoPipeline;

class AnalysisPipeline extends Pipeline
{
	public function __toString()
	{
		// Set up variables expected by pipeline.v3s. 
		$xml_file           = $this->workingDirectory . 'session.xml';
		$template_directory = $this->templateDirectory;
		$working_directory  = $this->workingDirectory;
		$cmo_file           = $this->cmoFile;
		include($this->templateDirectory . 'template_xml.php');

		ob_start();
		include($this->templateDirectory . 'pipeline.v3s');
		$pipeline = ob_get_contents();
		ob_end_clean();
		$pipeline .= new ExportVideoPipeline($this->session, $this->qtmVars, [
			'openCmo'           => false,
			'sideViewDirection' => $this->settings['sideViewDirection'],
			'captureFramerate'  => $this->settings['captureFramerate'],
			'screenshotDir'     => $this->settings['screenshotDir'],
		]);
		return $pipeline;
	}
}
