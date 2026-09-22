<?php
//Prepare EMG information
$EMG_names_array = array(); //for now we overwrite the list until we reached the last file and assume the same EMG channels were used in all files.

foreach($measurements as $m) {
	//Skip files that are unused, contain no analog data 
	if ($m["Used"] === 'True' && isset($m["Channels"]) && $m["Measurement_type"] === 'Dynamic') {
		foreach($m["Channels"] as $ch) {
			if (
					(
						(
							(
								strcmp('Noraxon', $ch["Board"]) == 0
								|| strcmp('MEGA/ME6000', $ch["Board"]) == 0
								|| (strcmp('Delsys Trigno', $ch["Board"]) == 0 && strpos($ch["Name"], "_ACC_") === false)
								|| (strcmp('Cometa', $ch["Board"]) == 0 && strpos($ch["Name"], "_ACC_") === false && strpos($ch["Name"], "_GYRO_") === false && strpos($ch["Name"], "_MAG_") === false && strpos($ch["Name"], "_Q_") === false)
							)
							&& (strpos($ch["Name"], 'Sync') === false)
						)
						|| 
						(
							(
								strcmp('USB-2533', $ch["Board"]) == 0
								|| strcmp('PCI-DAS6402/16', $ch["Board"]) == 0
							) 
							&&
							(
								strpos($ch["Name"], "EMG_") !== false
								|| (
										substr($ch["Name"], 0, 1) == "R" 
										|| substr($ch["Name"], 0, 1) == "L"
									)
							)
						)
					)
					&& (!in_array(trim($ch["Name"]), $EMG_names_array))
			)
			$EMG_names_array[] = trim($ch["Name"]);
		}
	}
}

/*
foreach ($EMG_names_array as $key => $value) {
	echo "!Post - Key: $key; Value: $value\n";
}
*/

//Save EMG list to file
if(!empty($EMG_names_array)) {
	file_put_contents($working_directory ."EMG_signals.json",json_encode($EMG_names_array));
}
//Load signal names from pre session to array
if (strpos($analysis_name, "Compare") !== false) {
	if (file_exists($working_directory_array[1] .'\EMG_signals.json')) {
		$EMG_names_array = json_decode(file_get_contents($working_directory_array[1] .'\EMG_signals.json'), true);

/*		foreach ($EMG_names_array as $key => $value) {
			echo "!Pre - Key: $key; Value: $value\n";
		}
*/	}
	if 	(file_exists($working_directory_array[1] .'\EMG_signals.json') && file_exists($working_directory .'EMG_signals.json')) {
		$EMG_names_array_pre = json_decode(file_get_contents($working_directory_array[1] .'\EMG_signals.json'), true);
		$EMG_names_array_post = json_decode(file_get_contents($working_directory .'EMG_signals.json'), true);
		if ($EMG_names_array_post === $EMG_names_array_pre){
			$EMG_names_array = $EMG_names_array_post;
		}
		if ($EMG_names_array_post !== $EMG_names_array_pre){
			$EMG_names_array = array_merge($EMG_names_array_post,$EMG_names_array_pre);
		}
	}
}
/*
foreach ($EMG_names_array as $key => $value) {
	echo "!All - Key: $key; Value: $value\n";
}
*/
//Create string of all signal names
$EMG_signal_names = implode("+", $EMG_names_array);

//count number of channels and prepare variables for export of correct number of EMG signals 
$count_EMG_signals = substr_count($EMG_signal_names, '+');

$EMG_event_array = preg_replace('/(L |Left |L_|Left_|L-|Left-).*?\+/', 'LHS+LHS,', $EMG_names_array);
$EMG_event_array = preg_replace('/(R |Right |R_|Right_|R-|Right-).*?\+/', 'RHS+RHS,', $EMG_event_array);
$EMG_event_array = preg_replace('/(R |Right |R_|Right_|R-|Right-).*$/', 'RHS+RHS', $EMG_event_array);
$EMG_event_array = preg_replace('/(L |Left |L_|Left_|L-|Left-).*$/', 'LHS+LHS', $EMG_event_array);
$EMG_event_array = preg_replace('/^((?!(LON\+LHS)|(RON\+RHS)).)*$/', 'RHS+RHS',  $EMG_event_array);
$EMG_event_seq = implode(',', $EMG_event_array);

//create separate arrays for Left, Right
$EMG_names_array_l = preg_grep('/(^L |^Left |^L_|^Left_|^L-|^Left-|^EMG_L |^EMG_Left |^EMG_L_|^EMG_Left_|^EMG_L-|^EMG_Left-|^EMG L |^EMG Left |^EMG L_|^EMG Left_|^EMG L-|^EMG Left-)/i', $EMG_names_array);
$EMG_names_array_r = preg_grep('/(^R |^Right |^R_|^Right_|^R-|^Right-|^EMG_R |^EMG_Right |^EMG_R_|^EMG_Right_|^EMG_R-|^EMG_Right-|^EMG R |^EMG Right |^EMG R_|^EMG Right_|^EMG R-|^EMG Right-)/i', $EMG_names_array);

$EMG_names_array_left = array();
foreach ($EMG_names_array_l as $value) {
	$EMG_names_array_left[] = $value;
}
$EMG_names_array_right = array();
foreach ($EMG_names_array_r as $value) {
	$EMG_names_array_right[] = $value;
}

if (empty($EMG_names_array_left) || empty($EMG_names_array_right))
{
	echo '!No valid EMG signals found. Signals have to start with "EMG" for analog AD board or "Left "/"L " or "Right "/"R" for digital AD board\!';
	//$EMG_signal_names = '';
	//$EMG_names_array = array();
}


$count_EMG_signals_left = count($EMG_names_array_left);
$count_EMG_signals_right = count($EMG_names_array_right);

/*
	if (empty($EMG_names_array_left)) {
		echo "! No EMG signals start \"Left\" or \"L\".";
	}
	else {
		foreach ($EMG_names_array_left as $key => $value) {
			echo "!Key: $key; Value: $value\n";
		}
	}

	if (empty($EMG_names_array_right))	{
		echo "! No EMG signals start \"Right\" or \"R\".";
	}
	else {
		foreach ($EMG_names_array_right as $key2 => $value2) {
			echo "!Key: $key2; Value: $value2\n";
		}
	}
*/
?> 
