<?php
if (file_exists($template_directory .'settings.php')) 	
	require($template_directory . 'settings.php');

require($v3dScriptDirectory. '/EMG_prepare_variables.php');

// process meta data for Word report
include_once(dirname(__FILE__) . '/meta.php');

// do Visual3D preparations
include_once(dirname(__FILE__) . '/prepare_environment.php');
if (!empty($template_directory)) {
	$v3dPluginFolder = $template_directory . 'Scripts\\src\\Visual3D\\Plugins\\';
	
	if (file_exists($v3dPluginFolder)) {
		setV3DRegistryValue('Recent Folder', 'PluginPath', $v3dPluginFolder, 'REG_SZ');
	}
}

// Include php variables.
$metaCommandDirectory  	 = $template_directory . 'Scripts\\src\\Visual3D\\Plugins\\Meta-Commands\\';
$event_mode				 = $subsession['Event_mode'];
$model_used 			 = $subsession['Model_used'];
$include_alternative_pelvis = $subsession["Include_alternative_pelvis_segment"];
$weight					 = $session["Weight"];
$height					 = $session["Height"];
$height_w_units			 = number_format(round(trim($session["Height"]),2),2).' m';
$weight_w_units			 = number_format(round(trim($session["Weight"]),1),1).' kg';
$openfile				 = str_ireplace("_exp.cmz", ".cmz", $cmo_file);
$norm_file_short		 = str_replace($normativeDirectory, '', $normative_file);

//Model -----------
$leg_length_left		 = findNonZeroField($session,$subsession,"Leg_length_left");
$leg_length_right		 = findNonZeroField($session,$subsession,"Leg_length_right");
$knee_width_left		 = findNonZeroField($session,$subsession,"Knee_width_left");
$knee_width_right		 = findNonZeroField($session,$subsession,"Knee_width_right");
$ankle_width_left		 = findNonZeroField($session,$subsession,"Ankle_width_left");
$ankle_width_right		 = findNonZeroField($session,$subsession,"Ankle_width_right");

//Ensure CGM includes leg length, knee width and ankle width and if not terminate the script
if (strpos($subsession['Model_used'], 'CGM') !== false) {
	if ($leg_length_left == 0 || $leg_length_right == 0 || $knee_width_left == 0 || $knee_width_right == 0 || $ankle_width_left == 0 || $ankle_width_right == 0)
		$CGM_pipeline_breakpoint = 'TRUE';
}
else 
		$CGM_pipeline_breakpoint = 'FALSE';

if (strpos($event_mode, "Instrumented treadmill") !== false) {
	$instrumented_treadmill = 'TRUE';
	$force_threshold_str = $force_threshold_treadmill;
	$analog_filter_fq_str = $analog_filter_fq_treadmill;
	$fp_zero_end = 0;
}
else {
	$instrumented_treadmill = 'FALSE';
	$force_threshold_str = $force_threshold;
	$analog_filter_fq_str = $analog_filter_fq;
}

//set model files
if (strpos($model_used,'CAST') !== false && strpos($subsession["Type"],'Full body session') === false)
	$model_template = 'Lower_Body_CAST_gait';
elseif (strpos($model_used,'CAST') !== false && strpos($subsession["Type"],'Full body session') !== false) {
	$model_template = 'Lower_Body_CAST_gait';
	$append_model = 'Thorax_gait+Arms_gait';
}
elseif (strpos($model_used,'IOR') !== false && strpos($subsession["Type"],'Full body session') === false)
	$model_template = 'Lower_Body_IOR_gait';
elseif (strpos($model_used,'IOR') !== false && strpos($subsession["Type"],'Full body session') !== false) {
	$model_template = 'Lower_Body_IOR_gait';
	$append_model = 'Thorax_IOR_gait+Arms_IOR_gait';
}
elseif (strpos($model_used,'CGM') !== false && strpos($subsession["Type"],'Full body session') === false)
	$model_template = 'Lower_Body_CGM_gait';
elseif (strpos($model_used,'CGM') !== false && strpos($subsession["Type"],'Full body session') !== false) {
	$model_template = 'Lower_Body_CGM_gait';
	$append_model = 'Upper_Body_CGM_gait';
}

//Upper body
if(strpos($subsession["Type"],'Full body session') !== false) {
	$FB = true;
	$includes_thorax = "TRUE";
	$includes_arms = "TRUE";
	$full_body = "TRUE";
}
else {
	$FB = false;
	$includes_thorax = "FALSE";
	$includes_arms = "FALSE";
	$full_body = "FALSE";
}

//OFM
$includes_OFM = "FALSE";
$includes_RFM = "FALSE";

if(strcmp('Oxford', $subsession["Multisegment_foot"]) == 0)
	$includes_OFM = "TRUE";
elseif(strpos($subsession["Multisegment_foot"],'Rizzoli') !== false)
	$includes_RFM = "TRUE";

if(strpos($subsession["Pose_algorithm"],'Inverse kinematics') !== false)
	$pose_algorithm = "IK";
else
	$pose_algorithm = "6DOF";

if($auto_range)
	$auto_range_str = "TRUE";
else
	$auto_range_str = "FALSE";

//Foot normalization
if (strpos($subsession["Left_foot_normalised_to_static_trial"], 'True') !== false) {
	$left_foot_normalization = 0;
	$left_foot_normalized = 'TRUE';
}
else {
	$left_foot_normalization = "L_FM2::Z-L_FCC::Z"; 
	$left_foot_normalized = 'FALSE';
}

if (strpos($subsession["Right_foot_normalised_to_static_trial"], 'True') !== false) {
	$right_foot_normalization = 0;
	$right_foot_normalized = 'TRUE';
}
else {
	$right_foot_normalization = "R_FM2::Z-R_FCC::Z";
	$right_foot_normalized = 'FALSE';
}

if (strpos($left_foot_normalized, 'TRUE') !== false && strpos($right_foot_normalized, 'FALSE') !== false)
	$foot_norm_info = 'Left foot segment used for joint angle calculations has been normalised to foot position at static trial.';
elseif (strpos($left_foot_normalized, 'FALSE') !== false && strpos($right_foot_normalized, 'TRUE') !== false)
	$foot_norm_info = 'Right foot segment used for joint angle calculations has been normalised to foot position at static trial.';
else
	$foot_norm_info = 'Both left and right foot segments used for joint angle calculations have been normalised to foot position at static trial.';

//File managment -----------------
/* Open also static file so that it is available for plots.
	if both anterior and posterior exists and have been checked, use anterior
	if only anterior exists, use anterior (obviously)
	if anterior is unchecked or does not exist, use posterior
*/

$anterior_exists = false;
$posterior_exists = false;

foreach($measurements as $m) {
	if(($m["Used"] === "True") && ($m["Measurement_type"] === "Static"))
		$path_parts = pathinfo($m["Filename"]);
		if (strpos($path_parts['filename'],'Anterior') !== false)
			$anterior_exists = true;
		if (strpos($path_parts['filename'],'Posterior') !== false)
			$posterior_exists = true;
}

if ($anterior_exists && $posterior_exists)
	$static_type = 'Anterior';
elseif ($anterior_exists && $posterior_exists === false)
	$static_type = 'Anterior';
elseif ($anterior_exists === false && $posterior_exists)
	$static_type = 'Posterior';

if($FB)
	$static_filename = 'Static FB '. $static_type . '*.c3d';
else
	$static_filename = 'Static LB '. $static_type . '*.c3d';

//create unique tag name for each file (used for mean EMG graphs)
$c3d_files_count = 0; //is used to define number of EMG graphs for instrumented treadmill

foreach($measurements as $m) {
	if(($m["Used"] === "True") && ($m["Measurement_type"] === "Dynamic")) {
		$path_parts = pathinfo($m["Filename"]);
		$filenames_arr[] = $path_parts['filename'] . ".c3d";
		$treadmill_speeds_arr[] = $m["Treadmill_speed"];
		$c3d_files_count++;
	}
}

$filenames = implode('+',$filenames_arr);
$treadmill_speeds = implode('+',$treadmill_speeds_arr);

//Report ----------------
if ($use_v3d_subject_info)
	$subject_info_page = 'TRUE';
else
	$subject_info_page = 'FALSE';

if(!empty($subject['First_name']))
	$first_name = $subject["First_name"];
else
	$first_name = trim('-');

if(!empty($subject['Last_name']))
	$last_name = $subject["Last_name"];
else
	$last_name = trim('-');

if(!empty($subject['Patient_ID']))
	$patient_ID = $subject["Patient_ID"];
else
	$patient_ID = trim('-');

if(!empty($subject['Creation_date']))
	$creation_date_patient= $subject["Creation_date"];
else
	$creation_date_patient = trim('-');

if(!empty($session['Creation_date']))
	$creation_date_session= $session["Creation_date"];
else
	$creation_date_session = trim('-');

if(!empty($subject['Date_of_birth']))
	$date_of_birth = $subject["Date_of_birth"];
else
	$date_of_birth = trim('-');

if (strpos($analysis_name, "Internal Compare sessions") === false) {
	$page_titles_array = array('Subject Information','Temporal and spatial data','Joint Angle Comparison','Joint Kinetics Comparison','Consistency Graphs - Kinematics','Consistency Graphs - Kinetics','Centre of Pressure','Centre of Pressure');
	if ($FB)
		array_push ($page_titles_array,"Upper Body Joint Angles Comparison","Consistency Graphs - Upper Body Joint Angles");

	$page_titles_list = implode('+',$page_titles_array);
}
else {
	$page_titles_array = array('Subject Information','Temporal and spatial data', 'Joint Angles Comparison','Joint moments Kinetics Comaprison','Consistency Graphs - Kinematics','Consistency Graphs - Kinetics');
	if ($FB)
		array_push ($page_titles_array,"Upper Body Kinematics");
	
	foreach ($page_titles_array as $page_title) {
		$page_title_full[] = $page_title . " - " .$test_condition_pre. " - " .$subsession["Test_condition"];
	}
	$page_titles = implode('+',$page_title_full);
}	

//Normatives -----------------
$normative_data = $subsession['Normative_data'];

if (file_exists($normative_file) || strpos($normative_file,'Custom') !== false)
	$normative_file_exists = 'TRUE';
else
	$normative_file_exists = 'FALSE';

if ($FB)
	$page_nums_annot = '3+4+9';
else
	$page_nums_annot = '3';
	
//EMG --------------
if ($includes_noraxon)
	$noraxon_exists = 'TRUE';
else
	$noraxon_exists = 'FALSE';

if ($includes_mega_me6000)
	$mega_me6000_exists = 'TRUE';
else
	$mega_me6000_exists = 'FALSE';

if ($includes_delsys_trigno)
	$delsys_trigno_exists = 'TRUE';
else
	$delsys_trigno_exists = 'FALSE';

if ($includes_myon)
	$myon_exists = 'TRUE';
else
	$myon_exists = 'FALSE';

if ($includes_analog_EMG)
	$analog_EMG_exists = 'TRUE';
else
	$analog_EMG_exists = 'FALSE';

if(file_exists($normativeDirectory .'EMG_normatives.vnd'))
	$normative_non_noraxon_file_exists = 'TRUE';
else
	$normative_non_noraxon_file_exists = 'FALSE';

if($includes_analog_EMG) 
	$EMG_unit_multiply = $EMG_unit_multiplicator; 
else 
	$EMG_unit_multiply = 1000000;

if (!empty($EMG_names_array_left)) {
	$EMG_names_array_left_exists = "TRUE";
	$EMG_graph_signals_name_left = implode('+',$EMG_names_array_left);
}
else
	$EMG_names_array_left_exists = "FALSE";

if (!empty($EMG_names_array_right)) {
	$EMG_names_array_right_exists = "TRUE";
	$EMG_graph_signals_name_right = implode('+',$EMG_names_array_right);
}
else
	$EMG_names_array_right_exists = "FALSE";

$EMG_names_list = implode('+',$EMG_names_array);

if($EMG_units_pct) {
	$EMG_units_pct = "TRUE";
	$EMG_units_pct_str = "TRUE";
}
else {
	$EMG_units_pct = "FALSE";
	$EMG_units_pct_str = "FALSE";
}

if($hide_messages)
	$hide_messages = "TRUE";
else
	$hide_messages = "FALSE";

if (!empty($EMG_names_array_left) || !empty($EMG_names_array_right)) {
	$EMG_valid_names = array_merge($EMG_names_array_right, $EMG_names_array_left); //make sure we only include EMG channels that are associated with a body side.
	$EMG_valid_names_list = implode('+',$EMG_valid_names);

	foreach ($EMG_valid_names as $EMG_signal_name) {
		$p2d_sig_name = preg_replace('/(^L |^Left |^L_|^Left_|^L-|^Left-|^R |^Right |^R_|^Right_|^R-|^Right-|^EMG_L_|^EMG L |^EMG_Left_|^EMG Left |^EMG_R_|^EMG R |^EMG_Right_|^EMG Right )/', '',$EMG_signal_name);
		$p2d_signal_names[] = str_replace('_',' ',$p2d_sig_name); //remove underscores, as EMG normative names should be without them
	}
	$p2d_signal_names_list = implode('+',$p2d_signal_names);
}
//-------------------

if (isset($test_condition_pre))
	$add_test_condition_pre = " - " .$test_condition_pre;

$test_condition = $subsession["Test_condition"];

$page_number = 9; //This is first EMG page of single session report

if (strpos($analysis_name, "Internal Compare sessions") !== false) {
	$page_number = 7; //This is first EMG page in Visual3D Comparison report
	$selected_files = 'Dynamic_pre';
	$sd_line_style = 'Dot';
	$sd_line_bold = 'FALSE';
	
	if ($FB)
		$page_number++;
}
else {
	$selected_files = 'Dynamic';
	$sd_line_style = 'Solid';
	$sd_line_bold = 'TRUE';
	
	if ($FB)
		$page_number = $page_number + 2;
}

$first_EMG_page = $page_number;

if ($count_EMG_signals_left <= 4 || $count_EMG_signals_right <= 4)
	$last_EMG_page = $page_number+2;
else 
	$last_EMG_page = $page_number+5;

for ($i = $first_EMG_page; $i <= $last_EMG_page; $i++)
	$EMG_pages_nums_arr[] = $i;

$EMG_pages_nums_list = implode('+',$EMG_pages_nums_arr);

if (empty($EMG_names_array_right))
	$col_num = 2;
else
	$col_num = 1;

//COP ------------------
if ($event_mode == "Single forceplate") {
	$select_tags = "L+R";
	$select_tags_arr = array('Left','Right');
}
else {
	$select_tags = "Dynamic";
	$select_tags_arr = array('Dynamic');
}
$select_tags_list = $select_tags;
$select_tags_arr_list = implode('+',$select_tags_arr);

//--------------------
foreach ($select_tags_arr as $tag_name) {
	//define array of signals
	//use these exeptions to avoid warnings for nonexistante link model based signals for LB
	
	$link_model_signal_names_LB = array('Left GRF', 'Right GRF', 'Left Ankle Angles', 'Left Ankle Moment', 'Left Ankle Power', 'Left Foot Pitch Angles', 'Left Foot Progression', 'Left Hip Angles', 'Left Hip Moment', 'Left Hip Power', 'Left Knee Angles', 'Left Knee Moment', 'Left Knee Power', 'Left Pelvic Angles', 'Right Ankle Angles', 'Right Ankle Moment', 'Right Ankle Power', 'Right Foot Pitch Angles', 'Right Foot Progression', 'Right Hip Angles', 'Right Hip Moment', 'Right Hip Power', 'Right Knee Angles', 'Right Knee Moment', 'Right Knee Power', 'Right Pelvic Angles');
	$link_model_signal_names_UB = array('Left Elbow Angles', 'Left Shoulder Angles', 'Left Thorax Angles', 'Left Thorax_Lab Angles', 'Right Elbow Angles', 'Right Shoulder Angles', 'Right Thorax Angles', 'Right Thorax_Lab Angles');
	$link_model_signal_names_OFM = array('Left Forefoot Hindfoot Angle','Left Forefoot Tibia Angle','Left Hallux Forefoot Angle','Left Hindfoot Tibia Angle','Right Forefoot Hindfoot Angle','Right Forefoot Tibia Angle','Right Hallux Forefoot Angle','Right Hindfoot Tibia Angle');
	$link_model_signal_names_RFM = array('Left Forefoot Hindfoot Angle','Left Forefoot Midfoot Angle','Left Hallux Forefoot Angle','Left Hindfoot Midfoot Angle','Left Hindfoot Tibia Angle','Left Shank IORFoot Angle','Right Forefoot Hindfoot Angle','Right Forefoot Midfoot Angle','Right Hallux Forefoot Angle','Right Hindfoot Midfoot Angle','Right Hindfoot Tibia Angle','Right Shank IORFoot Angle');
	
	//if (strcmp('Left', $tag_name) == 0) //to avoid warning for nonexistant Right/Left GRF for Single FP
	//	unset($link_model_signal_names_LB[array_search('Right GRF', $link_model_signal_names_LB)]);
		
	//if (strcmp('Right', $tag_name) == 0)
	//	unset($link_model_signal_names_LB[array_search('Left GRF', $link_model_signal_names_LB)]);
		
	if (strpos($event_mode, 'No forceplate (using template file)') !== false || strpos($event_mode, 'No forceplate (automatic)') !== false) {
		unset($link_model_signal_names_LB[array_search('Right GRF', $link_model_signal_names_LB)]);
		unset($link_model_signal_names_LB[array_search('Left GRF', $link_model_signal_names_LB)]);
	}
	
	if(strpos($subsession["Type"],'Lower body session') !== false)
		$link_model_signal_names = $link_model_signal_names_LB;
		
	if(strpos($subsession["Type"],'Full body session') !== false)
		$link_model_signal_names = array_merge($link_model_signal_names_LB, $link_model_signal_names_UB);

	if(strpos($includes_OFM, "TRUE") !== false)
		$link_model_signal_names = array_merge($link_model_signal_names, $link_model_signal_names_OFM);

	if(strpos($includes_RFM, "TRUE") !== false)
		$link_model_signal_names = array_merge($link_model_signal_names, $link_model_signal_names_RFM);

	$event_seq_arr = array();
	
	//Prepare event sequence LINK MODEL BASED
	foreach($link_model_signal_names as $name) {
		if (strpos($name, "Left") !== false) {
			$event_seq_arr[] = 'LHS+LHS';
		
			if (stripos($signal, 'Angle') !== FALSE) 
				$event_sq_array[] = ('LHS+LHS'); 
			else
				$event_sq_array[] = ('LON+LHS'); 
		}
		else {
			$event_seq_arr[] = 'RHS+RHS';
			
			if (stripos($signal, 'Angle') !== FALSE) 
				$event_sq_array[] = ('RHS+RHS'); 
			else
				$event_sq_array[] = ('RON+RHS');
		}
	}

	$link_signal_names = implode("+", $link_model_signal_names);
	$event_seq = implode(",", $event_seq_arr);
	$event_sq = implode(', ', $event_sq_array);
}

//prepare timeseries export
if ($raw_EMG_as_overlay)
	$points = 101;
else
	$points = 1001;

if ($includes_noraxon || $includes_delsys_trigno || $includes_mega_me6000 || $includes_myon || $includes_analog_EMG) {
	// $EMG_names_array = array(); //for now we overwrite the list until we reached the last file and assume the same EMG channels were used in all files.
	// $EMG_type_array = array();
	// $EMG_folder_array = array();
	// $EMG_names_array_web = array(); 
	// $EMG_type_array_web = array();
	// $EMG_folder_array_web = array();
	// $EMG_exclude_seq_array = array();
	// $EMG_event_seq_array = array();
	// $EMG_points_array = array();
	// $EMG_points_array_web = array();
	// $EMG_names_norm_array = array();
	// $EMG_type_norm_array = array();
	// $EMG_folder_norm_array = array();
	// $EMG_points_norm_array = array();

	foreach($measurements as $m) {
		if (strcmp('True', $m["Used"]) == 0 and isset($m["Channels"]) and strpos($m["Type"],"Static") === false) {

			//for now we overwrite the list until we reached the last file and assume the same EMG channels were used in all files.
			$EMG_names_array = array(); 
			$EMG_type_array = array();
			$EMG_folder_array = array();
			$EMG_names_array_web = array(); 
			$EMG_type_array_web = array();
			$EMG_folder_array_web = array();
			$EMG_exclude_seq_array = array();
			$EMG_event_seq_array = array();
			$EMG_points_array = array();
			$EMG_points_array_web = array();
			$EMG_names_norm_array = array();
			$EMG_type_norm_array = array();
			$EMG_folder_norm_array = array();
			$EMG_points_norm_array = array();

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
					) {
						
					if (!in_array(trim($ch["Name"]), $EMG_names_array)) {
						if (stripos($ch["Name"], 'R ') === 0 || stripos($ch["Name"], 'R_') === 0 || stripos($ch["Name"], 'Right') === 0 || stripos($ch["Name"], 'EMG_R ') === 0 || stripos($ch["Name"], 'EMG_R_') === 0 || stripos($ch["Name"], 'EMG_Right') === 0) {
							$EMG_names_array[] = $ch["Name"] . "+" . $ch["Name"];
							$EMG_type_array[] = 'ANALOG+ANALOG';
							$EMG_folder_array[] = 'EMG_PROCESSED+EMG_RAW';
							$EMG_names_array_web[] = $ch["Name"];
							$EMG_type_array_web[] = 'ANALOG';
							$EMG_folder_array_web[] = 'EMG_RAW_web';
							$EMG_exclude_seq_array[] = ', ,';
						
							if(strpos($analysis_name, 'Word') !== false && (strpos($event_mode, 'Instrumented treadmill') !== false || !$auto_range))
								$EMG_event_seq_array[] = ('start+end, RHS+RHS');
							else 
								$EMG_event_seq_array[] = ('RHS+RHS, RHS+RHS');

							$EMG_points_array[] = '101+'.$points;
							
							$EMG_names_norm_array[] = $ch["Name"] . "+" . $ch["Name"] . '_raw+' . $ch["Name"] . "+" . $ch["Name"] . '_raw';
							$EMG_type_norm_array[] = 'DERIVED+DERIVED+DERIVED+DERIVED';
							$EMG_folder_norm_array[] = 'NORM_LOWER+NORM_LOWER+NORM_RANGE+NORM_RANGE';
							$EMG_points_norm_array[] = '101+'.$points.'+101+'.$points;
						}
						elseif  (stripos($ch["Name"], 'L ') === 0 || stripos($ch["Name"], 'L_') === 0 || stripos($ch["Name"], 'Left') === 0 || stripos($ch["Name"], 'EMG_L ') === 0 || stripos($ch["Name"], 'EMG_L_') === 0 || stripos($ch["Name"], 'EMG_Left') === 0) {
							if (!in_array(trim($ch["Name"]), $EMG_names_array))
								$EMG_names_array[] = $ch["Name"] . "+" . $ch["Name"];
							$EMG_type_array[] = 'ANALOG+ANALOG';
							$EMG_folder_array[] = 'EMG_PROCESSED+EMG_RAW';
							$EMG_names_array_web[] = $ch["Name"];
							$EMG_type_array_web[] = 'ANALOG';
							$EMG_folder_array_web[] = 'EMG_RAW_web';
							$EMG_exclude_seq_array[] = ', ,';
							
							if(strpos($analysis_name, 'Word') !== false && (strpos($event_mode, 'Instrumented treadmill') !== false || !$auto_range))
								$EMG_event_seq_array[] = ('start+end, LHS+LHS');
							else 
								$EMG_event_seq_array[] = ('LHS+LHS, LHS+LHS');

							$EMG_points_array[] = '101+'.$points;

							$EMG_names_norm_array[] = $ch["Name"] . "+" . $ch["Name"] . '_raw+' . $ch["Name"] . "+" . $ch["Name"] . '_raw';
							$EMG_type_norm_array[] = 'DERIVED+DERIVED+DERIVED+DERIVED';
							$EMG_folder_norm_array[] = 'NORM_LOWER+NORM_LOWER+NORM_RANGE+NORM_RANGE';
							$EMG_points_norm_array[] = '101+'.$points.'+101+'.$points;
						}
						else
							echo('!' . $ch["Name"] . ' is an invalid name for an EMG channel (must start with Left or Right to indicate side)\n');
					}
				}
			}
		}
	}

	$EMG_signal_names_exp = implode("+", $EMG_names_array);
	$EMG_type = implode("+", $EMG_type_array);
	$EMG_folder = implode("+", $EMG_folder_array);
	$EMG_points = implode('+', $EMG_points_array);

	$EMG_signal_names_web = implode("+", $EMG_names_array_web);
	$EMG_type_web = implode("+", $EMG_type_array_web);
	$EMG_folder_web = implode("+", $EMG_folder_array_web);

	$EMG_exclude_seq = implode(", ", $EMG_exclude_seq_array);
	$EMG_event_seq = implode(',', $EMG_event_seq_array);

	$EMG_names_norm = implode('+', $EMG_names_norm_array);
	$EMG_type_norm = implode('+', $EMG_type_norm_array);
	$EMG_folder_norm = implode('+', $EMG_folder_norm_array);
	$EMG_points_norm = implode('+', $EMG_points_norm_array);
}

//timeseries.xml export---------------------
if(!empty($EMG_type)) $sig_types .=  $EMG_type . '+';
if(strpos($includes_thorax,"TRUE") !== false) $sig_types .=  'LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+';
if(strpos($includes_arms,"TRUE") !== false) $sig_types .=  'LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+';
if(strcmp('Oxford', $subsession["Multisegment_foot"]) == 0) $sig_types .=  'LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+DERIVED+DERIVED+';
if(strcmp('Rizzoli', $subsession["Multisegment_foot"]) == 0) $sig_types .=  'LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+';
if(strpos($analysis_name, 'Word') !== false && (strpos($event_mode, 'Instrumented treadmill') !== false || !$auto_range))
	$sig_types .=  'LINK_MODEL_BASED+LINK_MODEL_BASED+';
else
	$sig_types .=  'DERIVED+DERIVED+';
$sig_types .=  'LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED';

if(!empty($EMG_signal_names_exp)) $sig_names .= $EMG_signal_names_exp . '+';
if(strpos($includes_thorax,"TRUE") !== false) $sig_names .= 'Right Thorax Angles+Left Thorax Angles+Right Thorax_Lab Angles+Left Thorax_Lab Angles+';
if(strpos($includes_arms,"TRUE") !== false) $sig_names .= 'Left Shoulder Angles+Right Shoulder Angles+Left Elbow Angles+Right Elbow Angles+';
if(strcmp('Oxford', $subsession["Multisegment_foot"]) == 0) $sig_names .= 'Left Forefoot Hindfoot Angle+Left Forefoot Tibia Angle+Left Hallux Forefoot Angle+Left Hindfoot Tibia Angle+Right Forefoot Hindfoot Angle+Right Forefoot Tibia Angle+Right Hallux Forefoot Angle+Right Hindfoot Tibia Angle+Left Arch Height+Right Arch Height+';
if(strcmp('Rizzoli', $subsession["Multisegment_foot"]) == 0) $sig_names .= 'Left Forefoot Hindfoot Angle+Left Forefoot Midfoot Angle+Left Hallux Forefoot Angle+Left Hindfoot Midfoot Angle+Left Hindfoot Tibia Angle+Left Shank IORFoot Angle+Right Forefoot Hindfoot Angle+Right Forefoot Midfoot Angle+Right Hallux Forefoot Angle+Right Hindfoot Midfoot Angle+Right Hindfoot Tibia Angle+Right Shank IORFoot Angle+Left F2G+Left F2Ps+Left F2Pt+Left MLA+Left S2F+Left S2G+Left S2V+Left ShaCal_Static+Left V2G+Right F2G+Right F2Ps+Right F2Pt+Right MLA+Right S2F+Right S2G+Right S2V+Right ShaCal_Static+Right V2G+';
$sig_names .= 'Left GRF+Right GRF+Left Foot Progression+Left Ankle Angles+Left Ankle Moment+Left Ankle Power+Left Foot Pitch Angles+Left Hip Angles+Left Hip Moment+Left Hip Power+Left Knee Angles+Left Knee Moment+Left Knee Power+Left Pelvic Angles+Right Ankle Angles+Right Foot Progression+Right Ankle Moment+Right Ankle Power+Right Foot Pitch Angles+Right Hip Angles+Right Hip Moment+Right Hip Power+Right Knee Angles+Right Knee Moment+Right Knee Power+Right Pelvic Angles';

if(!empty($EMG_folder)) $sig_folder .= $EMG_folder . '+';
if(strpos($analysis_name, 'Word') !== false && (strpos($event_mode, 'Instrumented treadmill') !== false || !$auto_range)) {
	if(strpos($includes_thorax,"TRUE") !== false) $sig_folder .= 'PROCESSED+PROCESSED+PROCESSED+PROCESSED+';
	if(strpos($includes_arms,"TRUE") !== false) $sig_folder .= 'PROCESSED+PROCESSED+PROCESSED+PROCESSED+';
	if(strcmp('Oxford', $subsession["Multisegment_foot"]) == 0) $sig_folder .= 'PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+ARCH_HEIGHT+ARCH_HEIGHT+';
	if(strcmp('Rizzoli', $subsession["Multisegment_foot"]) == 0) $sig_folder .= 'PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+';
	$sig_folder .= 'PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED';
}
else {
	if(strpos($includes_thorax,"TRUE") !== false) $sig_folder .= 'ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+';
	if(strpos($includes_arms,"TRUE") !== false) $sig_folder .= 'ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+';
	if(strcmp('Oxford', $subsession["Multisegment_foot"]) == 0) $sig_folder .= 'ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ARCH_HEIGHT+ARCH_HEIGHT+';
	if(strcmp('Rizzoli', $subsession["Multisegment_foot"]) == 0) $sig_folder .= 'ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+';
	$sig_folder .= 'FORCE_GRAPH+FORCE_GRAPH+ORIGINAL+PROCESSED+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+PROCESSED+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL';
} 

if(!empty($EMG_event_seq)) $ev_segence .= $EMG_event_seq . ", ";
if(strpos($analysis_name, 'Word') !== false && (strpos($event_mode, 'Instrumented treadmill') !== false || !$auto_range)) {
	if(strpos($includes_thorax,"TRUE") !== false) $ev_segence .= 'start+end, start+end, start+end, start+end, ';
	if(strpos($includes_arms,"TRUE") !== false) $ev_segence .= 'start+end, start+end, start+end, start+end, ';
	if(strcmp('Oxford', $subsession["Multisegment_foot"]) == 0) $ev_segence .= 'start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, ';
	if(strcmp('Rizzoli', $subsession["Multisegment_foot"]) == 0) $ev_segence .= 'start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end,';
	$ev_segence .= 'start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end, start+end';
}
else {
	if(strpos($includes_thorax,"TRUE") !== false) $ev_segence .= 'RHS+RHS, LHS+LHS, RHS+RHS, LHS+LHS, ';
	if(strpos($includes_arms,"TRUE") !== false) $ev_segence .= 'LHS+LHS, RHS+RHS, LHS+LHS, RHS+RHS, ';
	if(strcmp('Oxford', $subsession["Multisegment_foot"]) == 0) $ev_segence .= 'LHS+LHS, LHS+LHS, LHS+LHS, LHS+LHS, RHS+RHS, RHS+RHS, RHS+RHS, RHS+RHS, LHS+LHS, RHS+RHS, ';
	if(strcmp('Rizzoli', $subsession["Multisegment_foot"]) == 0) $ev_segence .= 'LHS+LHS, LHS+LHS, LHS+LHS, LHS+LHS, LHS+LHS, LHS+LHS, RHS+RHS, RHS+RHS, RHS+RHS, RHS+RHS, RHS+RHS, RHS+RHS, LHS+LHS, LHS+LHS, LHS+LHS, LHS+LHS, LHS+LHS, LHS+LHS, LHS+LHS, LHS+LHS, LHS+LHS, RHS+RHS, RHS+RHS, RHS+RHS, RHS+RHS, RHS+RHS, RHS+RHS, RHS+RHS, RHS+RHS, RHS+RHS, ';
	$ev_segence .= 'LON+LOFF, RON+ROFF, LHS+LHS, LHS+LHS, LON+LHS, LON+LHS, LHS+LHS, LHS+LHS, LON+LHS, LON+LHS, LHS+LHS, LON+LHS, LON+LHS, LHS+LHS, RHS+RHS, RHS+RHS, RON+RHS, RON+RHS, RHS+RHS, RHS+RHS, RON+RHS, RON+RHS, RHS+RHS, RON+RHS, RON+RHS, RHS+RHS';
}

if(!empty($EMG_exclude_seq)) $ev_exclude .= $EMG_exclude_seq . ',';
if(strpos($includes_thorax,"TRUE") !== false) $ev_exclude .= ', , , ,';
if(strpos($includes_arms,"TRUE") !== false) $ev_exclude .= ', , , ,';
if(strcmp('Oxford', $subsession["Multisegment_foot"]) == 0) $ev_exclude .= ', , , , , , , , , ,';
if(strcmp('Rizzoli', $subsession["Multisegment_foot"]) == 0) $ev_exclude .= ', , , , , , , , , , , , , , , , , , , , , , , , , , , , , ,';
$ev_exclude .= ', , , , , , , , , , , , , , , , , , , , , , , , , ,';

if(!empty($EMG_points)) $norm_points .= $EMG_points . '+';
if(strpos($includes_thorax,"TRUE") !== false) $norm_points .= '101+101+101+101+,';
if(strpos($includes_arms,"TRUE") !== false) $norm_points .= '101+101+101+101+,';
if(strcmp('Oxford', $subsession["Multisegment_foot"]) == 0) $norm_points .= '101+101+101+101+101+101+101+101+101+101+';
if(strcmp('Rizzoli', $subsession["Multisegment_foot"]) == 0) $norm_points .= '101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+';
$norm_points .= '101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101';

//Normative.xml export
if(!empty($EMG_type_norm)) $sig_type_n .= $EMG_type_norm . '+';
if(strpos($includes_thorax,"TRUE") !== false) $sig_type_n .= 'DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+';
if(strpos($includes_arms,"TRUE") !== false) $sig_type_n .= 'DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+';
if(strcmp('Oxford', $subsession["Multisegment_foot"]) == 0) $sig_type_n .= 'DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+';
$sig_type_n .= 'DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+';
$sig_type_n .= 'P2D+P2D+P2D+P2D+P2D';

if(!empty($EMG_names_norm)) $sig_names_n .= $EMG_names_norm . '+';
if(strpos($includes_thorax,"TRUE") !== false) $sig_names_n .= 'Right Thorax_Lab Angles_X_RHS_RHS+Right Thorax_Lab Angles_Y_RHS_RHS+Right Thorax_Lab Angles_Z_RHS_RHS+Right Thorax Angles_X_RHS_RHS+Right Thorax Angles_Y_RHS_RHS+Right Thorax Angles_Z_RHS_RHS+Left Thorax Angles_X_LHS_LHS+Left Thorax Angles_Y_LHS_LHS+Left Thorax Angles_Z_LHS_LHS+Right Thorax_Lab Angles_X_RHS_RHS+Right Thorax_Lab Angles_Y_RHS_RHS+Right Thorax_Lab Angles_Z_RHS_RHS+Right Thorax Angles_X_RHS_RHS+Right Thorax Angles_Y_RHS_RHS+Right Thorax Angles_Z_RHS_RHS+Left Thorax Angles_X_LHS_LHS+Left Thorax Angles_Y_LHS_LHS+Left Thorax Angles_Z_LHS_LHS+';
if(strpos($includes_arms,"TRUE") !== false) $sig_names_n .= 'Left Shoulder Angles_X_LHS_LHS+Left Shoulder Angles_Y_LHS_LHS+Right Shoulder Angles_X_RHS_RHS+Right Shoulder Angles_Y_RHS_RHS+Right Shoulder Angles_Z_RHS_RHS+Left Elbow Angles_X_LHS_LHS+Right Elbow Angles_X_RHS_RHS+Right Elbow Angles_Z_RHS_RHS+Left Shoulder Angles_X_LHS_LHS+Left Shoulder Angles_Y_LHS_LHS+Right Shoulder Angles_X_RHS_RHS+Right Shoulder Angles_Y_RHS_RHS+Right Shoulder Angles_Z_RHS_RHS+Left Elbow Angles_X_LHS_LHS+Right Elbow Angles_X_RHS_RHS+Right Elbow Angles_Z_RHS_RHS+';
if(strcmp('Oxford', $subsession["Multisegment_foot"]) == 0) $sig_names_n .= 'Right Hindfoot Tibia Angle_X_RHS_RHS+Right Forefoot Hindfoot Angle_X_RHS_RHS+Right Forefoot Tibia Angle_X_RHS_RHS+Right Hallux Forefoot Angle_X_RHS_RHS+Right Hindfoot Tibia Angle_Y_RHS_RHS+Right Forefoot Hindfoot Angle_Y_RHS_RHS+Right Forefoot Tibia Angle_Y_RHS_RHS+Right Hindfoot Tibia Angle_Z_RHS_RHS+Right Forefoot Hindfoot Angle_Z_RHS_RHS+Right Forefoot Tibia Angle_Z_RHS_RHS+Right Arch Height_X_RHS_RHS+Right Hindfoot Tibia Angle_X_RHS_RHS+Right Forefoot Hindfoot Angle_X_RHS_RHS+Right Forefoot Tibia Angle_X_RHS_RHS+Right Hallux Forefoot Angle_X_RHS_RHS+Right Hindfoot Tibia Angle_Y_RHS_RHS+Right Forefoot Hindfoot Angle_Y_RHS_RHS+Right Forefoot Tibia Angle_Y_RHS_RHS+Right Hindfoot Tibia Angle_Z_RHS_RHS+Right Forefoot Hindfoot Angle_Z_RHS_RHS+Right Forefoot Tibia Angle_Z_RHS_RHS+Right Arch Height_X_RHS_RHS+';
$sig_names_n .= 'Left Ankle Angles_X_LHS_LHS+Left Ankle Angles_Y_LHS_LHS+Left Ankle Moment_X_LON_LHS+Left Ankle Moment_Y_LON_LHS+Left Ankle Power_X_LON_LHS+Left Foot Pitch Angles_X_LHS_LHS+Left Foot Progression_Z_LHS_LHS+Left GRF_X_LON_LOFF+Left GRF_Y_LON_LOFF+Left GRF_Z_LON_LOFF+Left Hip Angles_X_LHS_LHS+Left Hip Angles_Y_LHS_LHS+Left Hip Angles_Z_LHS_LHS+Left Hip Moment_X_LON_LHS+Left Hip Moment_Y_LON_LHS+Left Hip Power_X_LON_LHS+Left Hip Power_Y_LON_LHS+Left Knee Angles_X_LHS_LHS+Left Knee Angles_Y_LHS_LHS+Left Knee Angles_Z_LHS_LHS+Left Knee Moment_X_LON_LHS+Left Knee Moment_Y_LON_LHS+Left Knee Power_X_LON_LHS+Left Pelvic Angles_X_LHS_LHS+Left Pelvic Angles_Y_LHS_LHS+Left Pelvic Angles_Z_LHS_LHS+Right Ankle Angles_X_RHS_RHS+Right Ankle Angles_Y_RHS_RHS+Right Ankle Moment_X_RON_RHS+Right Ankle Moment_Y_RON_RHS+Right Ankle Power_X_RON_RHS+Right Foot Pitch Angles_X_RHS_RHS+Right Foot Progression_Z_RHS_RHS+Right GRF_X_RON_ROFF+Right GRF_Y_RON_ROFF+Right GRF_Z_RON_ROFF+Right Hip Angles_X_RHS_RHS+Right Hip Angles_Y_RHS_RHS+Right Hip Angles_Z_RHS_RHS+Right Hip Moment_X_RON_RHS+Right Hip Moment_Y_RON_RHS+Right Hip Power_X_RON_RHS+Right Hip Power_Y_RON_RHS+Right Knee Angles_X_RHS_RHS+Right Knee Angles_Y_RHS_RHS+Right Knee Angles_Z_RHS_RHS+Right Knee Moment_X_RON_RHS+Right Knee Moment_Y_RON_RHS+Right Knee Power_X_RON_RHS+Right Pelvic Angles_X_RHS_RHS+Right Pelvic Angles_Y_RHS_RHS+Right Pelvic Angles_Z_RHS_RHS+Left Ankle Angles_X_LHS_LHS+Left Ankle Angles_Y_LHS_LHS+Left Ankle Moment_X_LON_LHS+Left Ankle Moment_Y_LON_LHS+Left Ankle Power_X_LON_LHS+Left Foot Pitch Angles_X_LHS_LHS+Left Foot Progression_Z_LHS_LHS+Left GRF_X_LON_LOFF+Left GRF_Y_LON_LOFF+Left GRF_Z_LON_LOFF+Left Hip Angles_X_LHS_LHS+Left Hip Angles_Y_LHS_LHS+Left Hip Angles_Z_LHS_LHS+Left Hip Moment_X_LON_LHS+Left Hip Moment_Y_LON_LHS+Left Hip Power_X_LON_LHS+Left Hip Power_Y_LON_LHS+Left Knee Angles_X_LHS_LHS+Left Knee Angles_Y_LHS_LHS+Left Knee Angles_Z_LHS_LHS+Left Knee Moment_X_LON_LHS+Left Knee Moment_Y_LON_LHS+Left Knee Power_X_LON_LHS+Left Pelvic Angles_X_LHS_LHS+Left Pelvic Angles_Y_LHS_LHS+Left Pelvic Angles_Z_LHS_LHS+Right Ankle Angles_X_RHS_RHS+Right Ankle Angles_Y_RHS_RHS+Right Ankle Moment_X_RON_RHS+Right Ankle Moment_Y_RON_RHS+Right Ankle Power_X_RON_RHS+Right Foot Pitch Angles_X_RHS_RHS+Right Foot Progression_Z_RHS_RHS+Right GRF_X_RON_ROFF+Right GRF_Y_RON_ROFF+Right GRF_Z_RON_ROFF+Right Hip Angles_X_RHS_RHS+Right Hip Angles_Y_RHS_RHS+Right Hip Angles_Z_RHS_RHS+Right Hip Moment_X_RON_RHS+Right Hip Moment_Y_RON_RHS+Right Hip Power_X_RON_RHS+Right Hip Power_Y_RON_RHS+Right Knee Angles_X_RHS_RHS+Right Knee Angles_Y_RHS_RHS+Right Knee Angles_Z_RHS_RHS+Right Knee Moment_X_RON_RHS+Right Knee Moment_Y_RON_RHS+Right Knee Power_X_RON_RHS+Right Pelvic Angles_X_RHS_RHS+Right Pelvic Angles_Y_RHS_RHS+Right Pelvic Angles_Z_RHS_RHS+';
$sig_names_n .= 'CadenceNorm+Left_Step_LengthNorm+Right_Step_LengthNorm+SpeedNorm+Stride_LengthNorm';

if(!empty($EMG_folder_norm)) $sig_folder_n .= $EMG_folder_norm . '+';
if(strpos($includes_thorax,"TRUE") !== false) $sig_folder_n .= 'NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+';
if(strpos($includes_arms,"TRUE") !== false) $sig_folder_n .= 'NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+';
if(strcmp('Oxford', $subsession["Multisegment_foot"]) == 0) $sig_folder_n .= 'NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+';
$sig_folder_n .= 'NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_LOWER+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+NORM_RANGE+';
$sig_folder_n .= 'TSP_normatives+TSP_normatives+TSP_normatives+TSP_normatives+TSP_normatives';

if(!empty($EMG_points_norm)) $norm_points_n .= $EMG_points_norm . '+';
if(strpos($includes_thorax,"TRUE") !== false) $norm_points_n .= '101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+';
if(strpos($includes_arms,"TRUE") !== false) $norm_points_n .= '101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+';
if(strcmp('Oxford', $subsession["Multisegment_foot"]) == 0) $norm_points_n .= '101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+';
$norm_points_n .= '101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+101+';
$norm_points_n .= '101+101+101+101+101';

//session_data.xml export
if(!empty($EMG_type_web)) $sig_types_web .=  $EMG_type_web . '+';
$sig_types_web .= 'EVENT_LABEL+EVENT_LABEL+EVENT_LABEL+EVENT_LABEL+EVENT_LABEL+EVENT_LABEL+EVENT_LABEL+EVENT_LABEL+EVENT_LABEL+EVENT_LABEL+
					EVENT_LABEL+EVENT_LABEL+EVENT_LABEL+EVENT_LABEL+

					DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+
					DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+DERIVED+

					LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+
					LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+
					LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+
					LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+
					LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+LINK_MODEL_BASED+
					LINK_MODEL_BASED+

					LINK_MODEL_BASED+LINK_MODEL_BASED+

					DERIVED+DERIVED+

					METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+
					METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+
					METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+
					
					METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+
					METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+METRIC+
					METRIC+METRIC+METRIC';

if(!empty($EMG_signal_names_web)) $sig_names_web .= $EMG_signal_names_web . '+';
$sig_names_web .= 'end+LHS+LMS+LOFF+LON+LTO+RANGEEND+RANGESTART+RHS+RMS+
				ROFF+RON+RTO+start+

				Right F2G+Right F2Ps+Right F2Pt+Right MLA+Right S2F+Right S2V+Right V2G+Right S2G+Left F2G+Left F2Ps+
				Left F2Pt+Left MLA+Left S2F+Left S2G+Left S2V+Left V2G+

				Left Ankle Angles+Left Ankle Angles_CGM+Left Ankle Moment+Left Ankle Power+Left Elbow Angles+Left Foot Pitch Angles+Left Foot Progression+Left GRF+Left Hip Angles+Left Hip Moment+
				Left Hip Power+Left Knee Angles+Left Knee Moment+Left Knee Power+Left Pelvic Angles+Left Shoulder Angles+Left Thorax Angles+Left Thorax_Lab Angles+PelvisPos+Right Ankle Angles+
				Right Ankle Angles_CGM+Right Ankle Moment+Right Ankle Power+Right Elbow Angles+Right Foot Pitch Angles+Right Foot Progression+Right GRF+Right Hip Angles+Right Hip Moment+Right Hip Power+
				Right Knee Angles+Right Knee Moment+Right Knee Power+Right Pelvic Angles+Right Shoulder Angles+Right Thorax Angles+Right Thorax_Lab Angles+Left Forefoot Hindfoot Angle+Left Forefoot Tibia Angle+Left Hallux Forefoot Angle+
				Left Hindfoot Tibia Angle+Right Forefoot Hindfoot Angle+Right Forefoot Tibia Angle+Right Hallux Forefoot Angle+Right Hindfoot Tibia Angle+Left Forefoot Midfoot Angle+Left Hindfoot Midfoot Angle+Left Shank IORFoot Angle+Right Forefoot Midfoot Angle+Right Hindfoot Midfoot Angle+
				Right Shank IORFoot Angle+

				Left Ankle Angles+Right Ankle Angles+

				Left Arch Height+Right Arch Height+

				Left_GPS_ln_mean+Overall_GPS_ln_mean+Right_GPS_ln_mean+Left Ankle Angles_X_gvs_ln_mean+Left Foot Progression_Z_gvs_ln_mean+Left Hip Angles_X_gvs_ln_mean+Left Hip Angles_Y_gvs_ln_mean+Left Hip Angles_Z_gvs_ln_mean+Left Knee Angles_X_gvs_ln_mean+Left Pelvic Angles_X_gvs_ln_mean+
				Left Pelvic Angles_Y_gvs_ln_mean+Left Pelvic Angles_Z_gvs_ln_mean+Right Ankle Angles_X_gvs_ln_mean+Right Foot Progression_Z_gvs_ln_mean+Right Hip Angles_X_gvs_ln_mean+Right Hip Angles_Y_gvs_ln_mean+Right Hip Angles_Z_gvs_ln_mean+Right Knee Angles_X_gvs_ln_mean+Right Pelvic Angles_X_gvs_ln_mean+Right Pelvic Angles_Y_gvs_ln_mean+
				Right Pelvic Angles_Z_gvs_ln_mean+

				Uncropped Measurement Length+Uncropped Measurement Frames+Cropped Measurement Start Frame+Cropped Measurement End Frame+Frame_rate+Analog_rate+
				
				Cadence+Cycle_Time+Left_Cycle_Time+Left_Initial_Double_Limb_Support_Time+Left_Stance_Time+Left_Stance_Time_Pct+Left_Step_Length+Left_Step_Time+Left_Stride_Length+Left_Swing_Time+
				Right_Cycle_Time+Right_Initial_Double_Limb_Support_Time+Right_Stance_Time+Right_Stance_Time_Pct+Right_Step_Length+Right_Step_Time+Right_Stride_Length+Right_Swing_Time+Right_Terminal_Double_Limb_Support_Time+Speed+
				Statures_Per_Second+Stride_Length+Stride_Width';

if(!empty($EMG_folder_web)) $sig_folder_web .= $EMG_folder_web . '+';
$sig_folder_web .= 'ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+
				ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+

				RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+
				RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+RIZZOLI+

				ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+
				ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+
				ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+
				ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+
				ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+ORIGINAL+
				ORIGINAL+

				PROCESSED+PROCESSED+

				ARCH_HEIGHT+ARCH_HEIGHT+

				MAP+MAP+MAP+MAP+MAP+MAP+MAP+MAP+MAP+MAP+
				MAP+MAP+MAP+MAP+MAP+MAP+MAP+MAP+MAP+MAP+
				MAP+

				PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+PROCESSED+
				
				TMPD+TMPD+TMPD+TMPD+TMPD+TMPD+TMPD+TMPD+TMPD+TMPD+
				TMPD+TMPD+TMPD+TMPD+TMPD+TMPD+TMPD+TMPD+TMPD+TMPD+
				TMPD+TMPD+TMPD';

//3d viewer export
$CAST_marker_list_arr = array('L_HEAD','R_HEAD','SGL','CV7','L_FAR','L_FCC','L_FM1','L_FM2','L_FM5','L_HLE','L_HUM','L_IAS','L_IPS','L_RSP','L_SAE','L_SIA','L_SK1','L_SK2','L_SK3','L_SK4','L_TH1','L_TH2','L_TH3','L_TH4','L_USP','L_HM2','R_FAR','R_FCC','R_FM1','R_FM2','R_FM5','R_HLE','R_HUM','R_IAS','R_IPS','R_RSP','R_SAE','R_SIA','R_SK1','R_SK2','R_SK3','R_SK4','R_TH1','R_TH2','R_TH3','R_TH4','R_USP','R_HM2','TV10');
$IOR_marker_list_arr = array('L_HEAD','R_HEAD','SGL','CV7','L_FAL','L_FAR','L_FAX','L_FCC','L_FLE','L_FM1','L_FM5','L_FTC','L_HLE','L_HUM','L_IAS','L_IPS','L_RSP','L_SAE','L_TTC','L_USP','L_HM2','LV1','LV3','LV5','MAI','R_FAL','R_FAR','R_FAX','R_FCC','R_FLE','R_FM1','R_FM5','R_FTC','R_HLE','R_HUM','R_IAS','R_IPS','R_RSP','R_SAE','R_TTC','R_USP','R_HM2','SJN','SXS','TV2');
$CGM_marker_list_arr = array('L_FHD','L_BHD','R_FHD','R_BHD','SGL','CV7','L_FAL','L_FCC','L_FLE','L_FM2','L_FM5','L_FME','L_HLE','L_HM2','L_IAS','L_IPS','L_RSP','L_SAE','L_TAM','L_THI','L_TIB','L_TTC','L_USP','R_FAL','R_FCC','R_FLE','R_FM2','R_FM5','R_FME','R_HLE','R_HM2','R_IAS','R_IPS','R_RSP','R_SAE','R_SIA','R_TAM','R_THI','R_TIB','R_TTC','R_USP','SJN','SXS','TV10');

if(strpos($subsession["Type"],'CAST') !== false)
	$marker_list_arr = $CAST_marker_list_arr;
elseif(strpos($subsession["Type"],'IOR') !== false)
	$marker_list_arr = $IOR_marker_list_arr;
elseif(strpos($subsession["Type"],'CGM') !== false)
	$marker_list_arr = $CGM_marker_list_arr;

foreach($marker_list_arr as $list) {
	$sig_type_3dv .= "TARGET+";
	$sig_folder_3dv .= "UNITS_MM+";
}

$marker_list_3dv = implode('+',$marker_list_arr);

// Functions -----
function findNonZeroField($session, $subsession, $param_name) {
	if ($session[$param_name] > 0)
		$out = $session[$param_name];
	elseif ($subsession[$param_name] > 0)
		$out = $subsession[$param_name];
	else 
		$out = 0;
	
	return $out;
}

?> 