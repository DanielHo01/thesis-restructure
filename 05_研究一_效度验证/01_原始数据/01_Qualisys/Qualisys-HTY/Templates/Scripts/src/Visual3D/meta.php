<?php
namespace Qualisys\Gait;

require $pafToolbeltDirectory . 'src/util/dict2xml.php';

use function Qualisys\PafToolbelt\Util\dict2xml;

date_default_timezone_set('UTC');

$height = number_format(round(trim($session["Height"]),2),2).' m';
$weight = number_format(round(trim($session["Weight"]),1),1).' kg';

//Prepare normative data information for last report page
if ($session["Normative_data"] === 'Children (all)' || $session["Normative_data"] === 'Default') {
	$normative_text = 'Normative data consists of 41 healthy children (19 females and 22 males) walking at a self-selected speed. Mean and 1SD are presented for comparison.';
}
else if ($session["Normative_data"] === 'Adults') {
	$normative_text = 'Normative data consists of 18 healthy adults (10 females and 8 males) walking at a self-selected speed. Mean and 1SD are presented for comparison.';
}
else {
	$normative_text = $session['Normative_data'];
}

//Prepare string of file names for "All traces" page
$dynamic_file_names = 'Files shown: ';
foreach ($measurements as $m) {
	if (($m["Used"] === "True") && ($m["Measurement_type"] === "Dynamic")) {
		$dynamic_file_names .= $m['Filename'] . ' ';
		$path_parts = pathinfo($m["Filename"]);
		$filenames_array[] = $path_parts['filename'] . ".c3d";
//		$filenames_array[] = $working_directory . $path_parts['filename'] . ".c3d";
		$count++; //count number of measurent files
	}
}
$dynamic_file_names_c3d = implode (",",$filenames_array); //used for EMG to plot only dynamic files in Word comparison report

//Store information whether session includes EMG data
if ($includes_noraxon || $includes_mega_me6000 || $includes_delsys_trigno || $includes_myon || $includes_analog_EMG) {
	$includes_EMG = 'yes';
}
else {
	$includes_EMG = 'no';
}
/*
$file = $working_directory. 'variables.xml';
$search_string = '<Gait_';

for ($i=0; $i < Count($file); $i++) {
	if (!substr_count($file[$i],$search_string) == 0) {
	$count++;
	}
}
*/

function birth_to_age($date, $session, $format = 'Y-m-d') {
	return $age = \DateTime::createFromFormat($format, $date)
		->diff(\DateTime::createFromFormat($format, $session))
		->y;
}

//Array
//(
    //[Type] => Full body gait session
    //[ID] => 870101
    //[First_name] => Kerstin
    //[Last_name] => Larsson
    //[Date_of_birth] => 20130416
    //[Height] => 1.750000000
    //[Weight] => 58.000000000
    //[Clinical] => 
    //[Study] => 
    //[Directory_pattern] => $Creation date$
    //[Comments] => 
    //[Creation_date] => 20130416
    //[Creation_time] => 12:12:25
    //[Append_model] => Upper_body.mdh
    //[Includes_EMG] => False
    //[Testing_condition_1] => 
    //[Normative_data_file] => Women 16-65.vnd
    //[Model] => Lower_Body.mdh
//)

$meta = [
	'name'                     => $subject['First_name'] . ' ' . $subject['Last_name'],
	//'age'				       => $age,
	'height'			       => $height,
	'weight'			       => $weight,
	'age'                      => birth_to_age($subject['Date_of_birth'], $session['Creation_date'], 'Y-m-d'),
	'num_files'			       => $count,
	'includes_EMG'		       => $includes_EMG,
	'normative_text'	       => $normative_text,
	'creation_date_formatted'  => strftime('%d-%m-%Y', strtotime($session['Creation_date'])),
	'dynamic_file_names'       => $dynamic_file_names,
	'dynamic_file_names_c3d'   => $dynamic_file_names_c3d,
	'date_of_birth_formatted'  => strftime('%d-%m-%Y', strtotime($subject['Date_of_birth']))
];

$dest = $working_directory . '/meta.xml';
file_put_contents($dest, dict2xml($meta, 'meta', false));
?>