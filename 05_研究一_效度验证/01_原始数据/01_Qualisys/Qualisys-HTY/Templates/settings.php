<?php
/*==================================
  Script settings
  ==================================*/

// Set up paths.
$assetsDirectory         = $template_directory . 'Assets/';
$picturesDirectory       = $assetsDirectory . 'Pictures/';
$modelDirectory          = $assetsDirectory . 'Visual3D Models/';
$reportTemplateDirectory = $assetsDirectory . 'Visual3D Report Templates/';
$normativeDirectory      = $assetsDirectory . 'Visual3D Normatives/';
$wordTemplateDirectory   = $assetsDirectory . 'Word Templates/';
$v3dScriptDirectory      = $template_directory . 'Scripts/src/Visual3D/';
$pafToolbeltDirectory    = $template_directory . 'Scripts/vendor/qualisys/paf-toolbelt/';

// Visual3D report templates (without file paths)
$model_template 				= $subsession['Model'];
$report_template 				= 'Report_gait.rgt';
$comparative_report_template 	= 'Report_comparative_gait.rgt';
$report_template_OFM 			= 'Report_OxfordFM_gait.rgt'; //Visual3D report template to append Oxford foot data
$comparative_report_template_OFM = 'Report_comaparative_OxfordFM_gait.rgt'; //Visual3D report template to append Oxford foot data to comparison report
$report_template_RFM 			= 'Report_RizzoliFM_gait.rgt'; //Visual3D report template to append Oxford foot data
$use_v3d_subject_info			= false; //Choose what Subject info to use of the first page of V3D report. If 'false' V3D form is used, where user can manually fill in patient meta data. If 'true' meta data from PAF pane are used. Default is false.

// Normative data.
if (strpos($subsession['Normative_data'], 'Adults') !== false) {
	// Modify this line to specify the name of the Adult normative data file.
	$normative_file = $normativeDirectory . '18AdultsOH6DOF.vnd';
}
elseif (strpos($subsession['Normative_data'], 'Children (all)') !== false) {
	// Modify this line to specify the name of the Children normative data file.
	$normative_file = $normativeDirectory . '41kids_13ofm_modHFaxis.vnd';
}
elseif (strpos($subsession['Normative_data'], 'Default') !== false) {
	// Modify this line to specify the name of the Default normative data file.
	$normative_file = $normativeDirectory . '41kids_13ofm_modHFaxis.vnd';
}
else {
	$normative_file = $normativeDirectory . $subsession['Normative_data'] . '.vnd';
}

// Events.
$window_size_divisor       = 10;  // 10
$force_threshold           = 10;  // 10 - for instrumented treadmill use $force_threshold_treadmill
$force_threshold_treadmill = 50;  // Default is 50 use up to 80
$left_norm_event           = 61; // Sets position of Bottom TICKS annotations in Visual3D report on page 11 and 23 for LEFT normatives mean toe off event
$right_norm_event          = 60; // Sets position of Bottom TICKS annotations in Visual3D report on page 11 and 23 for RIGHT normatives mean toe off event

// Force data.
$fp_zero_start = 0; // Force/analog zeroing (if start and end are equal or event mode is set to instrumented treadmill, no zeroing will be performed
$fp_zero_end   = 20; // Force/analog zeroing (if start and end are equal or event mode is set to instrumented treadmill, no zeroing will be performed
	
//Filtering (set any of the following to '0' to disable gap fill/filtering)
$max_gap 					= 10;
$marker_filter_fq 			= 6;
$analog_filter_fq 			= 25;
$analog_filter_fq_graphs 	= 25;
$analog_filter_fq_treadmill = 30; // 30 for instrumented treadmill

// Centre of Pressure (COP).
$COP_start_shift = 0.02; // Set distance of calcaneus marker to bone since calcaneus marker is certain distance behind the actual bone. Default is 0.02 m.

//Instrumented treadmill
$treadmill_type = 'Treadmetrix'; //Options 'AMTI_tandem', 'Treadmetrix'

// EMG variables.
// Note: signal processing is done in this order:  highpass > lowpass > rectify > RMS
$EMG_high_pass_filter		= 10; // Cutoff frequency. Default is 10, (set to '0' to disable)
$EMG_high_pass_bidir_pass	= 1; // Default is 1
$EMG_low_pass_filter    	= 0; // Cutoff frequency. Default is 0, (set to '0' to disable)
$EMG_low_pass_bidir_pass	= 1; // Default is 1
$EMG_rectify				= 0; // Default is 0 because RMS includes rectification, (set to '0' to disable)
$EMG_rms_window         	= 100; // Default is 100, units are milliseconds
$EMG_unit_multiplicator 	= 1000; // Default is 1000. Note: only for analog AD board!
$hide_messages          	= true; //For non-Noraxon EMG only! If normative EMG data (EMG_normatives.vnd) does not exist, this variable hides error and warning messages related to nonexistant EMG p2d signals, default is FALSE

// Report graph settings.
// GENERAL
$line_color_left               = 'red'; //Default is 'red'. Applies to Word and Web report. Options: 'red', 'green', 'blue', 'black'.
$line_color_right              = 'blue'; //Default is 'blue'. Applies to Word and Web report. Options: 'red', 'green', 'blue', 'black'.

// WORD REPORT
$auto_range 		  = false; // Default is FALSE. If FALSE, Word report shows mean value of all cycles within a trial. If TRUE, only cycle on force plates is shown in Word report. This feature applies only to Word report and only if event mode is set to Multiple forceplates. Feature has no effect on web report.
$signed_by_1_name     = 'edit settings.php to change'; //following variables modify examiners' names on the first page of the report
$signed_by_1_position = 'edit settings.php to change';
$signed_by_2_name     = 'edit settings.php to change';
$signed_by_2_position = 'edit settings.php to change';
$signed_by_3_name     = 'edit settings.php to change';
$signed_by_3_position = 'edit settings.php to change';
$signed_by_4_name     = 'edit settings.php to change';
$signed_by_4_position = 'edit settings.php to change';

$y_range_auto                  = false; //Default is FALSE. If FALSE, default explicit values are used, if TRUE, range for all graphs is set to signal min/max values.
$consistency_graphs_as_overlay = true; //Default is TRUE. Applies to Word comparison report only. If TRUE, right and left traces are placed at same graph. If FALSE, they are on separate pages.
$comparison_as_overlay         = true; //Default is TRUE. Applies to Word comparison report only. If TRUE, pre and post session curves are shown overlayed in same graphs and all traces graphs are omitted. If FALSE, pre and post sessions are placed at separate graphs.

$EMG_units_pct            = false; //Defaut is FALSE. Defines whether EMG use mV or % as units in graphs. If TRUE units are % else units are mV. To switch between units, session must be 're-analyzed'. Note: for comparison report make sure both sessions use same units.
$line_color_EMG_left      = 'red'; //Default is 'red'. Applies to Word report only (processed EMG). Options: 'red', 'green', 'blue', 'black'.
$line_color_EMG_right     = 'blue'; //Default is 'blue'. Applies to Word report only (processed EMG). Options: 'red', 'green', 'blue', 'black'.
$line_color_EMG_raw_left  = 'red'; //Default is 'red'. Applies to Word report only (raw EMG). Options: 'red', 'green', 'blue', 'black'.
$line_color_EMG_raw_right = 'blue'; //Default is 'blue'. Applies to Word report only (raw EMG). Options: 'red', 'green', 'blue', 'black'.
$plot_type_EMG            = 'each_trial'; //Defines whether each trial is ploted or mean of all trials, options: 'each_trial', 'mean_of_trials'. Default is 'each_trial'. Note: mean of raw EMS files is not calculated.
$raw_EMG_as_overlay       = false; //Default is FALSE. If TRUE, raw signals are added to graphs with processed EMG signals. Note: Applies only to Word report. Works only if $plot_type_EMG                                                                                  = 'each_trial'; Raw signals are resampled to 101 points.
$logo_height              = 30; // Default is 30. Specifies the height of the logo (in pixels) in the web report.

// MAP normatives.
$map_norms_adults = [
	'Overall_GPS_median'      => 4.6,
	'Overall_GPS_IQR'         => 0.7,
	'Left_GPS_median'         => 4.5,
	'Left_GPS_IQR'            => 0.7,
	'Right_GPS_median'        => 4.5,
	'Right_GPS_IQR'           => 0.7,
	'GVS_Pelvis_X_median'     => 2.1,
	'GVS_Pelvis_X_IQR'        => 1.1,
	'GVS_Pelvis_Y_median'     => 1.4,
	'GVS_Pelvis_Y_IQR'        => 0.5,
	'GVS_Pelvis_Z_median'     => 1.9,
	'GVS_Pelvis_Z_IQR'        => 0.9,
	'GVS_LHip_X_median'       => 4.2,
	'GVS_LHip_X_IQR'          => 1.3,
	'GVS_RHip_X_median'       => 4.2,
	'GVS_RHip_X_IQR'          => 1.3,
	'GVS_LHip_Y_median'       => 2.6,
	'GVS_LHip_Y_IQR'          => 0.7,
	'GVS_RHip_Y_median'       => 2.6,
	'GVS_RHip_Y_IQR'          => 0.7,
	'GVS_LHip_Z_median'       => 4.6,
	'GVS_LHip_Z_IQR'          => 2.2,
	'GVS_RHip_Z_median'       => 4.6,
	'GVS_RHip_Z_IQR'          => 2.2,
	'GVS_LKnee_X_median'      => 4.4,
	'GVS_LKnee_X_IQR'         => 1.5,
	'GVS_RKnee_X_median'      => 4.4,
	'GVS_RKnee_X_IQR'         => 1.5,
	'GVS_LAnkle_X_median'     => 3.7,
	'GVS_LAnkle_X_IQR'        => 1.5,
	'GVS_RAnkle_X_median'     => 3.7,
	'GVS_RAnkle_X_IQR'        => 1.5,
	'GVS_LFoot_Prog_Z_median' => 5.6,
	'GVS_LFoot_Prog_Z_IQR'    => 1.9,
	'GVS_RFoot_Prog_Z_median' => 5.6,
	'GVS_RFoot_Prog_Z_IQR'    => 1.9,
];

$map_norms_paeds = [
	'Overall_GPS_median'      => 4.6,
	'Overall_GPS_IQR'         => 0.9,
	'Left_GPS_median'         => 4.0,
	'Left_GPS_IQR'            => 0.7,
	'Right_GPS_median'        => 4.0,
	'Right_GPS_IQR'           => 0.7,
	'GVS_Pelvis_X_median'     => 3.0,
	'GVS_Pelvis_X_IQR'        => 1.8,
	'GVS_Pelvis_Y_median'     => 1.7,
	'GVS_Pelvis_Y_IQR'        => 0.6,
	'GVS_Pelvis_Z_median'     => 2.7,
	'GVS_Pelvis_Z_IQR'        => 0.8,
	'GVS_LHip_X_median'       => 4.5,
	'GVS_LHip_X_IQR'          => 1.6,
	'GVS_RHip_X_median'       => 4.5,
	'GVS_RHip_X_IQR'          => 1.6,
	'GVS_LHip_Y_median'       => 2.2,
	'GVS_LHip_Y_IQR'          => 0.7,
	'GVS_RHip_Y_median'       => 2.2,
	'GVS_RHip_Y_IQR'          => 0.7,
	'GVS_LHip_Z_median'       => 4.6,
	'GVS_LHip_Z_IQR'          => 0.8,
	'GVS_RHip_Z_median'       => 4.6,
	'GVS_RHip_Z_IQR'          => 0.8,
	'GVS_LKnee_X_median'      => 2.8,
	'GVS_LKnee_X_IQR'         => 0.9,
	'GVS_RKnee_X_median'      => 2.8,
	'GVS_RKnee_X_IQR'         => 0.9,
	'GVS_LAnkle_X_median'     => 3.8,
	'GVS_LAnkle_X_IQR'        => 1.1,
	'GVS_RAnkle_X_median'     => 3.8,
	'GVS_RAnkle_X_IQR'        => 1.1,
	'GVS_LFoot_Prog_Z_median' => 4.3,
	'GVS_LFoot_Prog_Z_IQR'    => 1.7,
	'GVS_RFoot_Prog_Z_median' => 4.3,
	'GVS_RFoot_Prog_Z_IQR'    => 1.7,
];
?>
