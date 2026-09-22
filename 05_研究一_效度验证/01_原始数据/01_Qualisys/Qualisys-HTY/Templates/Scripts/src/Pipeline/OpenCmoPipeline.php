<?php
namespace Qualisys\Gait\Pipeline;

class OpenCmoPipeline extends Pipeline
{
	public function __toString()
	{
		$fields = $this->session->fields;
		return "File_Open\n"
			. '/FILE_NAME=' . $this->workingDirectory . 'Report_' . $fields['ID'] . '_' . utf8_decode($fields['Name']) . '_' . $fields['Creation_date'] . ".r3w\n"
			. ";\n"
			
			. "Switch_to_Report_Mode\n"
			. ";\n";
	}
}
