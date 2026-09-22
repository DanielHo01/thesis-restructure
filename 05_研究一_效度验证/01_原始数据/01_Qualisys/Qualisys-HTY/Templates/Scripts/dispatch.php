<?php
ini_set('memory_limit', '1024M');

if (!extension_loaded('curl'))
	dl('php_curl.dll');

if (!extension_loaded('mbstring'))
	dl('php_mbstring.dll');

require $template_directory . '/Scripts/vendor/autoload.php';

use Qualisys\Gait\AnalysisDispatcher;

$qtmVars = [
	'workingDirectory'  => $working_directory,
	'templateDirectory' => $template_directory,
	'measurementGuids'  => $measurement_guids,
	'typeGuids'         => $type_guids,
	'cmoFile'           => isset($cmo_file) ? $cmo_file : null,
];

$dispatcher = new AnalysisDispatcher($qtmVars);
$dispatcher->dispatch($analysis_name);
?>
