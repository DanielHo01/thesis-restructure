<?php
namespace Qualisys\Gait\Pipeline;

use Qualisys\Gait\Pipeline\Pipeline;

class GenerateReportDescriptorPipeline extends Pipeline {
	public function run($analysisName) {
		$xml_file = $this->workingDirectory. 'session.xml';
		$template_directory = $this->templateDirectory;
		$working_directory = $this->workingDirectory;

		include($this->templateDirectory. 'template_xml.php');
		include($this->templateDirectory. 'settings.php');

		//file_put_contents($this->templateDirectory . 'log.txt', print_r(get_defined_vars(), false));

		if (strpos($analysisName, 'Comparison') !== false) {
			// Repeat some code from pipeline_compare.v3s.
			$working_directory_array= file($this->workingDirectory . 'comparison_folders.txt', FILE_IGNORE_NEW_LINES);

			$xml                  = $this->workingDirectory . 'meta_pre.xml';
			$meta_pre             = simplexml_load_file($xml);
			$EMG_pre_exists       = (string) $meta_pre->includes_EMG;
			$dynamic_files_pre    = (string) $meta_pre->dynamic_file_names_c3d;
			$filenames_array_pre  = explode(',', $dynamic_files_pre);

			$xml                  = $this->workingDirectory . 'meta.xml';
			$meta_post            = simplexml_load_file($xml);
			$EMG_post_exists      = (string) $meta_post->includes_EMG;
			$dynamic_files_post   = (string) $meta_post->dynamic_file_names_c3d;
			$filenames_array_post = explode(',', $dynamic_files_post);

			// Define variables for each trial and comparison.
			$pre_post               = ['' , '_post'];
			$suffix                 = '_MEAN';
		}
		else {
			$pre_post = [''];
			$suffix   = '';
		}

		if ($EMG_units_pct)
			$EMG_units = '%';
		else
			$EMG_units = 'mV';

		if ($this->session->fields['Normative_data'] === 'Adults')
			$map_norms = $map_norms_adults;
		else if ($this->session->fields['Normative_data'] === 'Children (all)')
			$map_norms = $map_norms_paeds;
		else
			$map_norms = $map_norms_paeds;

		// Create an array of default y-ranges.
		$y_range_arr_expl = [
			'Pelvic_Angles_X_max' => '30',
			'Pelvic_Angles_X_min' => '0',
			'Pelvic_Angles_X_major' => '10',
			'Pelvic_Angles_Y_max' => '15',
			'Pelvic_Angles_Y_min' => '-15',
			'Pelvic_Angles_Y_major' => '7.5',
			'Pelvic_Angles_Z_max' => '30',
			'Pelvic_Angles_Z_min' => '-30',
			'Pelvic_Angles_Z_major' => '15',
			'Hip_Angles_X_max' => '70',
			'Hip_Angles_X_min' => '-20',
			'Hip_Angles_X_major' => '30',
			'Hip_Angles_Y_max' => '30',
			'Hip_Angles_Y_min' => '-30',
			'Hip_Angles_Y_major' => '15',
			'Hip_Angles_Z_max' => '30',
			'Hip_Angles_Z_min' => '-30',
			'Hip_Angles_Z_major' => '15',
			'Knee_Angles_X_max' => '75',
			'Knee_Angles_X_min' => '-15',
			'Knee_Angles_X_major' => '30',
			'Knee_Angles_Y_max' => '30',
			'Knee_Angles_Y_min' => '-30',
			'Knee_Angles_Y_major' => '15',
			'Knee_Angles_Z_max' => '30',
			'Knee_Angles_Z_min' => '-50',
			'Knee_Angles_Z_major' => '20',
			'Ankle_Angles_X_max' => '30',
			'Ankle_Angles_X_min' => '-30',
			'Ankle_Angles_X_major' => '15',
			'Ankle_Angles_Y_max' => '30',
			'Ankle_Angles_Y_min' => '-30',
			'Ankle_Angles_Y_major' => '15',
			'Foot_Pitch_Angles_X_max' => '30',
			'Foot_Pitch_Angles_X_min' => '-60',
			'Foot_Pitch_Angles_X_major' => '15',
			'Foot_Progression_Z_max' => '30',
			'Foot_Progression_Z_min' => '-30',
			'Foot_Progression_Z_major' => '15',
			'Thorax_Lab_Angles_X_max' => '20',
			'Thorax_Lab_Angles_X_min' => '-20',
			'Thorax_Lab_Angles_X_major' => '10',
			'Thorax_Lab_Angles_Y_max' => '15',
			'Thorax_Lab_Angles_Y_min' => '-15',
			'Thorax_Lab_Angles_Y_major' => '7.5',
			'Thorax_Lab_Angles_Z_max' => '30',
			'Thorax_Lab_Angles_Z_min' => '-30',
			'Thorax_Lab_Angles_Z_major' => '15',
			'Thorax_Angles_X_max' => '20',
			'Thorax_Angles_X_min' => '-40',
			'Thorax_Angles_X_major' => '10',
			'Thorax_Angles_Y_max' => '20',
			'Thorax_Angles_Y_min' => '-20',
			'Thorax_Angles_Y_major' => '10',
			'Thorax_Angles_Z_max' => '30',
			'Thorax_Angles_Z_min' => '-30',
			'Thorax_Angles_Z_major' => '15',
			'Shoulder_Angles_X_max' => '40',
			'Shoulder_Angles_X_min' => '-50',
			'Shoulder_Angles_X_major' => '30',
			'Shoulder_Angles_Y_max' => '20',
			'Shoulder_Angles_Y_min' => '-40',
			'Shoulder_Angles_Y_major' => '15',
			'Shoulder_Angles_Z_max' => '80',
			'Shoulder_Angles_Z_min' => '-40',
			'Shoulder_Angles_Z_major' => '30',
			'Elbow_Angles_X_max' => '90',
			'Elbow_Angles_X_min' => '0',
			'Elbow_Angles_X_major' => '30',
			'Elbow_Angles_Z_max' => '200',
			'Elbow_Angles_Z_min' => '0',
			'Elbow_Angles_Z_major' => '50',
			'Hip_Moment_X_max' => '1.5',
			'Hip_Moment_X_min' => '-1.5',
			'Hip_Moment_X_major' => '0.5',
			'Knee_Moment_X_max' => '1.5',
			'Knee_Moment_X_min' => '-1',
			'Knee_Moment_X_major' => '0.5',
			'Ankle_Moment_X_max' => '2',
			'Ankle_Moment_X_min' => '-0.5',
			'Ankle_Moment_X_major' => '0.5',
			'Hip_Power_X_max' => '2',
			'Hip_Power_X_min' => '-2',
			'Hip_Power_X_major' => '1',
			'Knee_Power_X_max' => '2',
			'Knee_Power_X_min' => '-2',
			'Knee_Power_X_major' => '1',
			'Ankle_Power_X_max' => '5',
			'Ankle_Power_X_min' => '-2',
			'Ankle_Power_X_major' => '1',
			'Hip_Moment_Y_max' => '1.25',
			'Hip_Moment_Y_min' => '-0.5',
			'Hip_Moment_Y_major' => '0.25',
			'Knee_Moment_Y_max' => '0.75',
			'Knee_Moment_Y_min' => '-0.5',
			'Knee_Moment_Y_major' => '0.25',
			'Ankle_Moment_Y_max' => '0.5',
			'Ankle_Moment_Y_min' => '-0.5',
			'Ankle_Moment_Y_major' => '0.2',
			'Hip_Power_Y_max' => '3',
			'Hip_Power_Y_min' => '-3',
			'Hip_Power_Y_major' => '1.5',
			'GRF_Y_max' => '0.3',
			'GRF_Y_min' => '-0.3',
			'GRF_Y_major' => '0.15',
			'GRF_X_max' => '0.15',
			'GRF_X_min' => '-0.10',
			'GRF_X_major' => '0.05',
			'GRF_Z_max' => '2',
			'GRF_Z_min' => '0',
			'GRF_Z_major' => '0.5',
			'Forefoot_Hindfoot_Angle_X_max' => '20',
			'Forefoot_Hindfoot_Angle_X_min' => '-20',
			'Forefoot_Hindfoot_Angle_X_major' => '10',
			'Forefoot_Hindfoot_Angle_Y_max' => '30',
			'Forefoot_Hindfoot_Angle_Y_min' => '-20',
			'Forefoot_Hindfoot_Angle_Y_major' => '10',
			'Forefoot_Hindfoot_Angle_Z_max' => '20',
			'Forefoot_Hindfoot_Angle_Z_min' => '-30',
			'Forefoot_Hindfoot_Angle_Z_major' => '10',
			'Forefoot_Tibia_Angle_X_max' => '30',
			'Forefoot_Tibia_Angle_X_min' => '-40',
			'Forefoot_Tibia_Angle_X_major' => '10',
			'Forefoot_Tibia_Angle_Y_max' => '30',
			'Forefoot_Tibia_Angle_Y_min' => '-20',
			'Forefoot_Tibia_Angle_Z_major' => '10',
			'Forefoot_Tibia_Angle_Z_max' => '30',
			'Forefoot_Tibia_Angle_Z_min' => '-30',
			'Forefoot_Tibia_Angle_Z_major' => '15',
			'Hallux_Forefoot_Angle_X_max' => '40',
			'Hallux_Forefoot_Angle_X_min' => '-20',
			'Hallux_Forefoot_Angle_X_major' => '15',
			'Hallux_Forefoot_Angle_Y_max' => '20',
			'Hallux_Forefoot_Angle_Y_min' => '-20',
			'Hallux_Forefoot_Angle_Y_major' => '10',
			'Hallux_Forefoot_Angle_Z_max' => '20',
			'Hallux_Forefoot_Angle_Z_min' => '-20',
			'Hallux_Forefoot_Angle_Z_major' => '10',
			'Hindfoot_Tibia_Angle_X_max' => '25',
			'Hindfoot_Tibia_Angle_X_min' => '-20',
			'Hindfoot_Tibia_Angle_X_major' => '10',
			'Hindfoot_Tibia_Angle_Y_max' => '20',
			'Hindfoot_Tibia_Angle_Y_min' => '-20',
			'Hindfoot_Tibia_Angle_Y_major' => '10',
			'Hindfoot_Tibia_Angle_Z_max' => '30',
			'Hindfoot_Tibia_Angle_Z_min' => '-20',
			'Hindfoot_Tibia_Angle_Z_major' => '10',
			'Arch_Height_X_max' => '25',
			'Arch_Height_X_min' => '5',
			'Arch_Height_X_major' => '5',
			'Shank_IORFoot_Angle_X_max' => '20',
			'Shank_IORFoot_Angle_X_min' => '-40',
			'Shank_IORFoot_Angle_X_major' => '10',
			'Shank_IORFoot_Angle_Y_max' => '10',
			'Shank_IORFoot_Angle_Y_min' => '-30',
			'Shank_IORFoot_Angle_Y_major' => '10',
			'Shank_IORFoot_Angle_Z_max' => '10',
			'Shank_IORFoot_Angle_Z_min' => '-30',
			'Shank_IORFoot_Angle_Z_major' => '10',
			'Hindfoot_Midfoot_Angle_X_max' => '120',
			'Hindfoot_Midfoot_Angle_X_min' => '40',
			'Hindfoot_Midfoot_Angle_X_major' => '20',
			'Hindfoot_Midfoot_Angle_Y_max' => '10',
			'Hindfoot_Midfoot_Angle_Y_min' => '-30',
			'Hindfoot_Midfoot_Angle_Y_major' => '10',
			'Hindfoot_Midfoot_Angle_Z_max' => '20',
			'Hindfoot_Midfoot_Angle_Z_min' => '-20',
			'Hindfoot_Midfoot_Angle_Z_major' => '10',
			'Forefoot_Midfoot_Angle_X_max' => '40',
			'Forefoot_Midfoot_Angle_X_min' => '-100',
			'Forefoot_Midfoot_Angle_X_major' => '35',
			'Forefoot_Midfoot_Angle_Y_max' => '10',
			'Forefoot_Midfoot_Angle_Y_min' => '-30',
			'Forefoot_Midfoot_Angle_Y_major' => '10',
			'Forefoot_Midfoot_Angle_Z_max' => '10',
			'Forefoot_Midfoot_Angle_Z_min' => '-30',
			'Forefoot_Midfoot_Angle_Z_major' => '10',
			'F2G_X_max' => '105',
			'F2G_X_min' => '-10',
			'F2G_X_major' => '15',
			'S2G_X_max' => '100',
			'S2G_X_min' => '-50',
			'S2G_X_major' => '30',
			'S2F_X_max' => '20',
			'S2F_X_min' => '-20',
			'S2F_X_major' => '10',
			'V2G_X_max' => '100',
			'V2G_X_min' => '-50',
			'V2G_X_major' => '30',
			'S2V_X_max' => '20',
			'S2V_X_min' => '-20',
			'S2V_X_major' => '10',
			'F2Ps_X_max' => '100',
			'F2Ps_X_min' => '-50',
			'F2Ps_X_major' => '30',
			'F2Pt_X_max' => '20',
			'F2Pt_X_min' => '-20',
			'F2Pt_X_major' => '10',
			'MLA_X_max' => '250',
			'MLA_X_min' => '100',
			'MLA_X_major' => '30',
		];

		// Remove upper body angles for LB session from $y_range_arr_expl array.
		if (strpos($this->session->subsessions[0]['Type'],'Lower body session') !== false) {
			$keys = array_keys($y_range_arr_expl);

			foreach ($keys as $key) {
				if (preg_match('/Elbow.*$/', $key) || preg_match('/Shoulder.*$/', $key) || preg_match('/Thorax.*$/', $key)) {
					unset($y_range_arr_expl[$key]);
				}
			}
		}

		// Array of filenames (used later to index timeseries).
		$xml = simplexml_load_file($this->workingDirectory . 'timeseries.xml');

		foreach($xml->children() as $child) {
			$filenames[] = (string)$child['value'];
		}

		// Read list of link model based signal names from file.
		foreach($xml->owner[0]->children() as $child) {
			$type = (string)$child['value'];
			
			if ($type == 'LINK_MODEL_BASED') {
				foreach($child->folder[0]->children() as $name)
						$link_model_signal_names[] = (string)$name['value'];
						
				if (isset($child->folder[1])) {
					foreach($child->folder[1]->children() as $name)
						$link_model_signal_names[] = (string)$name['value'];
				}

				if (strpos($this->session->subsessions[0]['Event_mode'],'Instrumented treadmill') === false && $auto_range) {
					foreach($child->folder[1]->children() as $name) 
							$link_model_signal_names[] = (string)$name['value'];
				}
			}
		}

		array_push($link_model_signal_names , 'GRF');

		if (isset($this->session->subsessions[0]['Multisegment_foot']) && strcmp('Oxford', $this->session->subsessions[0]['Multisegment_foot']) == 0)
			array_push($link_model_signal_names , 'Arch Height');

		if (isset($this->session->subsessions[0]['Multisegment_foot']) && strcmp('Rizzoli', $this->session->subsessions[0]['Multisegment_foot']) == 0)
			array_push($link_model_signal_names , 'Left F2G','Left F2Ps','Left F2Pt','Left MLA','Left S2F','Left S2G','Left S2V','Left ShaCal_Static','Left V2G','Right F2G','Right F2Ps','Right F2Pt','Right MLA','Right S2F','Right S2G','Right S2V','Right ShaCal_Static','Right V2G');

		$link_model_signal_names_short = str_replace('Left ' , '' , $link_model_signal_names);
		$link_model_signal_names_short = str_replace('Right ' , '' , $link_model_signal_names_short);
		$link_model_signal_names_short = array_unique($link_model_signal_names_short);

		// Create array (same format as $y_range_arr_expl).
		$y_range_arr_auto = [];
		$graph_min_max_file = $this->workingDirectory. 'min_max.xml';
		$xml = simplexml_load_file($graph_min_max_file);

		foreach ($xml->owner->type->folder->children() as $signal_name) {
			$sig_name = (string)$signal_name['value'];
			$sig_name = str_replace('Left ' , '' , $sig_name); //remove side
			$sig_name = str_replace('Right ' , '' , $sig_name); //remove side

			if (strpos($sig_name , '_max_X_MAX') !== false) {
				$y = $this->getGraphMinMax($xml , $graph_min_max_file , $sig_name); // use function to find max value for signal
				$sig_name_un = str_replace(' ', '_', $sig_name); //add underscore to signal names
				$sig_name_un = str_replace('_max_X_MAX', '', $sig_name_un); //remove suffix
				$y_range_arr_auto[$sig_name_un . '_X_max'] = $y[1];
			}
			else if (strpos($sig_name , '_max_Y_MAX') !== false) {
				$y = $this->getGraphMinMax($xml , $graph_min_max_file , $sig_name); // use function to find max value for signal
				$sig_name_un = str_replace(' ', '_', $sig_name); //add underscore to signal names
				$sig_name_un = str_replace('_max_Y_MAX', '', $sig_name_un); //remove suffix
				$y_range_arr_auto[$sig_name_un . '_Y_max'] = $y[1];
			}
			else if (strpos($sig_name , '_max_Z_MAX') !== false) {
				$y = $this->getGraphMinMax($xml , $graph_min_max_file , $sig_name); // use function to find max value for signal
				$sig_name_un = str_replace(' ', '_', $sig_name); //add underscore to signal names
				$sig_name_un = str_replace('_max_Z_MAX', '', $sig_name_un); //remove suffix
				$y_range_arr_auto[$sig_name_un . '_Z_max'] = $y[1];
			}
			else if (strpos($sig_name , '_min_X_MIN') !== false) {
				$y = $this->getGraphMinMax($xml , $graph_min_max_file , $sig_name); // use function to find max value for signal
				$sig_name_un = str_replace(' ', '_', $sig_name); //add underscore to signal names
				$sig_name_un = str_replace('_min_X_MIN', '', $sig_name_un); //remove suffix
				$y_range_arr_auto[$sig_name_un . '_X_min'] = $y[0];
			}
			else if (strpos($sig_name , '_min_Y_MIN') !== false) {
				$y = $this->getGraphMinMax($xml , $graph_min_max_file , $sig_name); // use function to find max value for signal
				$sig_name_un = str_replace(' ', '_', $sig_name); //add underscore to signal names
				$sig_name_un = str_replace('_min_Y_MIN', '', $sig_name_un); //remove suffix
				$y_range_arr_auto[$sig_name_un . '_Y_min'] = $y[0];
			}
			else if (strpos($sig_name , '_min_Z_MIN') !== false) {
				$y = $this->getGraphMinMax($xml , $graph_min_max_file , $sig_name); // use function to find max value for signal
				$sig_name_un = str_replace(' ', '_', $sig_name); //add underscore to signal names
				$sig_name_un = str_replace('_min_Z_MIN', '', $sig_name_un); //remove suffix
				$y_range_arr_auto[$sig_name_un . '_Z_min'] = $y[0];
			}
		}

		/*
		foreach ($y_range_arr_auto as $key => $value) {
			echo "<!Key: $key; Value: $value\n>";
		}
		*/

		// Select array based on $y_range_auto in Settings.php.
		if ($y_range_auto)
			$y_range = $y_range_arr_auto;
		else
			$y_range = $y_range_arr_expl;

		$plot_section0 = 'true';  //Page 1 title
		$plot_section1 = 'true';  //Page 1 title- personal data single
		$plot_section2 = 'true';  //Page 1 personal data two sessions + 
		$plot_section3 = 'true';  //Page Page 1 Findings +signature + note on page 2
		$plot_section4 = 'true';  //Page 2 orientation - single session 
		$plot_section5 = 'true';  //Page 2 orientation - two sessions 
		$plot_section6 = 'true';  //Page 2 Quality + page 3 evidence + pages 6 title
		$plot_section6ls = 'true';  //Page 6 legend signle
		$plot_section6ld = 'true';  //Page 6 legend two
		$plot_section6g = 'true';  //Page 6 graphs
		$plot_section7 = 'true';  //Page 7 title
		$plot_section7ls = 'true';  //Page 7 legend sigle
		$plot_section7ld = 'true';  //Page 7 legend dooulble
		$plot_section7g = 'true';  //Page 7 graphs
		$plot_section8 = 'true';  //Page 8 title
		$plot_section8ls = 'true';  //Page 8 legend sigle
		$plot_section8ld = 'true';  //Page 8 legend double
		$plot_section8g = 'true';  //Page 8 graphs
		$plot_section9 = 'true';  //Page 9 title
		$plot_section9ls = 'true';  //Page 9 legend single
		$plot_section9ld = 'true';  //Page 9 legend double
		$plot_section9g = 'true';  //Page 9 graphs
		$plot_section10 = 'true'; //Page 10 title
		$plot_section10ls = 'true'; //Page 10 legend single
		$plot_section10ld = 'true'; //Page 10 legend double
		$plot_section10g = 'true'; //Page 10 graphs
		$plot_section11 = 'true'; //Page 11 title
		$plot_section11ls = 'true'; //Page 11 legend single
		$plot_section11ld = 'true'; //Page 11 legend double
		$plot_section11g = 'true'; //Page 11 graphs
		$plot_section12 = 'true'; //Page 12  UB title
		$plot_section12ls = 'true'; //Page 12 legend sigle
		$plot_section12ld = 'true'; //Page 12 legend double
		$plot_section12g = 'true'; //Page 12 graphs
		$plot_section13 = 'true'; //Page 13 title
		$plot_section13ls = 'true'; //Page 13 legend sigle
		$plot_section13ld = 'true'; //Page 13 legend double
		$plot_section13g = 'true'; //Page 13 graphs
		$plot_section14 = 'true'; //Page 14 title
		$plot_section14ls = 'true'; //Page 14 legend single
		$plot_section14ld = 'true'; //Page 14 legend double
		$plot_section14g = 'true'; //Page 14 graphs
		$plot_section15 = 'false';//Page 15 Forces pre - (PAGE REMOVED)
		$plot_section16 = 'true';   //Page 16 OFM pre title
		$plot_section16ls = 'true'; //Page 16 OFM pre legend single
		$plot_section16ld = 'true'; //Page 16 OFM pre legend double
		$plot_section16g = 'true';  //Page 16 OFM pre graphs
		$plot_section17 = 'true';   //Page 17 OFM pre All traces (Left/L+R) title
		$plot_section17ls = 'true'; //Page 17 OFM pre All traces (Left/L+R) legend sigle
		$plot_section17ld = 'true'; //Page 17 OFM pre All traces (Left/L+R) legend doudle
		$plot_section17g = 'true';  //Page 17 OFM pre All traces (Left/L+R) graphs
		$plot_section18 = 'true';   //Page 18 OFM pre All traces (Right) title
		$plot_section18ls = 'true'; //Page 18 OFM pre All traces (Right) legend sigle
		$plot_section18ld = 'true'; //Page 18 OFM pre All traces (Right) legend double
		$plot_section18g = 'true';  //Page 18 OFM pre All traces (Right) graphs
		$plot_section19 = 'true';   //Page 19 RFM joint angles pre title
		$plot_section19ls = 'true'; //Page 19 RFM joint angles pre legend single
		$plot_section19ld = 'true'; //Page 19 RFM joint angles pre legend double
		$plot_section19g = 'true';  //Page 19 RFM joint angles pre graphs
		$plot_section20 = 'true';   //Page 20 RFM planar angles pre title
		$plot_section20ls = 'true'; //Page 20 RFM planar angles pre legend single
		$plot_section20ld = 'true'; //Page 20 RFM planar angles pre legend double
		$plot_section20g = 'true';  //Page 20 RFM planar angles pre graphs
		$plot_section21 = 'true';   //Page 21 RFM pre joint angles All traces (Left/L+R) title
		$plot_section21ls = 'true'; //Page 21 RFM pre joint angles All traces (Left/L+R) legend sigle
		$plot_section21ld = 'true'; //Page 21 RFM pre joint angles All traces (Left/L+R) legend doudle
		$plot_section21g = 'true';  //Page 21 RFM pre joint angles All traces (Left/L+R) graphs
		$plot_section22 = 'true';   //Page 22 RFM planar angles pre All traces (Left/L+R) title
		$plot_section22ls = 'true'; //Page 22 RFM planar angles pre All traces (Left/L+R) legend sigle
		$plot_section22ld = 'true'; //Page 22 RFM planar angles pre All traces (Left/L+R) legend doudle
		$plot_section22g = 'true';  //Page 22 RFM planar angles pre All traces (Left/L+R) graphs
		$plot_section23 = 'true';   //Page 23 RFM pre joint angles All traces (Right) title
		$plot_section23ls = 'true'; //Page 23 RFM pre joint angles All traces (Right) legend sigle
		$plot_section23ld = 'true'; //Page 23 RFM pre joint angles All traces (Right) legend double
		$plot_section23g = 'true';  //Page 23 RFM pre joint angles All traces (Right) graphs
		$plot_section24 = 'true';   //Page 24 RFM pre planar angles All traces (Right) title
		$plot_section24ls = 'true'; //Page 24 RFM pre planar angles All traces (Right) legend sigle
		$plot_section24ld = 'true'; //Page 24 RFM pre planar angles All traces (Right) legend double
		$plot_section24g = 'true';  //Page 24 RFM pre planar angles All traces (Right) graphs
		$plot_section25 = 'true'; //Page 25 post session LB angles title
		$plot_section26 = 'true'; //Page 26 post session kinetics title
		$plot_section27 = 'true'; //Page 27 post session LB angles (Left/L+R) title
		$plot_section28 = 'true'; //Page 28 post session LB angles (Right) title
		$plot_section29 = 'true'; //Page 29 post session kinetics (Left/L+R) title
		$plot_section30 = 'true'; //Page 30 post session kinetics (Right) title
		$plot_section31 = 'true'; //Page 31 Upper body angles post session  title
		$plot_section32 = 'true'; //Page 32 Upper body angles post session  title
		$plot_section33 = 'true'; //Page 33 Upper body angles post session (Right) title
		$plot_section34 = 'false';//Page 34 Forces post session (PAGE REMOVED)
		$plot_section35 = 'true'; //Page 35 OFM post session title
		$plot_section36 = 'true'; //Page 36 OFM post session title
		$plot_section37 = 'true'; //Page 37 OFM post session (Right) title
		$plot_section38 = 'true'; //Page 38 RFM post session title
		$plot_section39 = 'true'; //Page 39 RFM post session title
		$plot_section40 = 'true'; //Page 40 RFM post session (Right) title
		$plot_section41 = 'true'; //Page 41 TSP - picture 
		$plot_section42 = 'true'; //Page 41 TSP - pre session  
		$plot_section43 = 'true'; //Page 41 TSP - post sessions/single
		$plot_section44 = 'true'; //Page 42 MAP - title  
		$plot_section45 = 'true'; //Page 42 MAP - pre session
		$plot_section46 = 'true'; //Page 42 MAP - post sessions/single
		$plot_section47 = 'true'; //Page 43 EMG filtered - pre
		$plot_section48 = 'true'; //Page 44 EMG raw - pre
		$plot_section49 = 'true'; //Page 45 EMG filtered - post
		$plot_section50 = 'true'; //Page 46 EMG raw - post
		$plot_section51 = 'true'; //Page 47,48    

		if (strpos($analysisName, 'Comparison') === false) {
			$plot_section2 = 'false';  //
			$plot_section5 = 'false';  //
			$plot_section6ld = 'false';  //
			$plot_section7ld = 'false';  //
			$plot_section8ld = 'false';  //
			$plot_section9ld = 'false';  //
			$plot_section10ld = 'false';  //
			$plot_section11ld = 'false';  //
			$plot_section12ld = 'false';  //
			$plot_section13ld = 'false';  //
			$plot_section14ld = 'false';  //
			$plot_section16ld = 'false';  //
			$plot_section17ld = 'false';  //
			$plot_section18ld = 'false';  //
			$plot_section19ld = 'false';  //
			$plot_section20ld = 'false';  //
			$plot_section21ld = 'false';  //
			$plot_section22ld = 'false';  //
			$plot_section23ld = 'false';  //
			$plot_section24ld = 'false';  //
			$plot_section25 = 'false'; //
			$plot_section26 = 'false'; //
			$plot_section27 = 'false'; //
			$plot_section28 = 'false'; //
			$plot_section29 = 'false'; //
			$plot_section30 = 'false'; //
			$plot_section31 = 'false'; //
			$plot_section32 = 'false'; //
			$plot_section33 = 'false'; //
			$plot_section34 = 'false'; //
			$plot_section35 = 'false'; //
			$plot_section36 = 'false'; //
			$plot_section37 = 'false'; //
			$plot_section38 = 'false'; //
			$plot_section39 = 'false'; //
			$plot_section40 = 'false'; //
			$plot_section42 = 'false'; //
			$plot_section45 = 'false'; //
		}

		if (strpos($analysisName, 'Comparison') !== false) {
			$plot_section1 = 'false'; //Page 1 title- personal data pre
			$plot_section4 = 'false'; //Page 2 orientation - single session 
			$plot_section6ls = 'false';  //
			$plot_section7ls = 'false';  //
			$plot_section8ls = 'false';  //
			$plot_section9ls = 'false';  //
			$plot_section10ls = 'false';  //
			$plot_section11ls = 'false';  //
			$plot_section12ls = 'false';  //
			$plot_section13ls = 'false';  //
			$plot_section14ls = 'false';  //
			$plot_section16ls = 'false';  //
			$plot_section17ls = 'false';  //
			$plot_section18ls = 'false';  //
			$plot_section19ls = 'false';  //
			$plot_section20ls = 'false';  //
			$plot_section21ls = 'false';  //
			$plot_section22ls = 'false';  //
			$plot_section23ls = 'false';  //
			$plot_section24ls = 'false';  //
		}

		// Hide kinetics pages if force data do not exist.
		if (strpos($this->session->subsessions[0]['Event_mode'],'No forceplate') !== false) {
			$plot_section7 = 'false';
			$plot_section7ls = 'false';
			$plot_section7ld = 'false';
			$plot_section7g = 'false';
			$plot_section10 = 'false';
			$plot_section10ls = 'false';
			$plot_section10ld = 'false';
			$plot_section10g = 'false';
			$plot_section11 = 'false';
			$plot_section11ls = 'false';
			$plot_section11ld = 'false';
			$plot_section11g = 'false';
			$plot_section26 = 'false'; //
		}

		// Hide all traces graphs when comparing as overlay.
		if (strpos($analysisName, 'Comparison') !== false && $comparison_as_overlay) {
			$plot_section8 = 'false'; //Page 
			$plot_section8ls = 'false'; //Page 
			$plot_section8ld = 'false'; //Page 
			$plot_section8g = 'false'; //Page 
			$plot_section9 = 'false'; //Page 
			$plot_section9ls = 'false'; //Page 
			$plot_section9ld = 'false'; //Page 
			$plot_section9g = 'false'; //Page 
			$plot_section10 = 'false'; //Page 
			$plot_section10ls = 'false'; //Page 
			$plot_section10ld = 'false'; //Page 
			$plot_section10g = 'false'; //Page 
			$plot_section11 = 'false'; //Page 
			$plot_section11ls = 'false'; //Page 
			$plot_section11ld = 'false'; //Page 
			$plot_section11g = 'false'; //Page 
			$plot_section13 = 'false'; //Page 
			$plot_section13ls = 'false'; //Page 
			$plot_section13ld = 'false'; //Page 
			$plot_section13g = 'false'; //Page 
			$plot_section14 = 'false'; //Page 
			$plot_section14ls = 'false'; //Page 
			$plot_section14ld = 'false'; //Page 
			$plot_section14g = 'false'; //Page 
			$plot_section17 = 'false'; //Page 
			$plot_section17ls = 'false'; //Page 
			$plot_section17ld = 'false'; //Page 
			$plot_section17g = 'false'; //Page 
			$plot_section18 = 'false'; //Page 
			$plot_section18ls = 'false'; //Page 
			$plot_section18ld = 'false'; //Page 
			$plot_section18g = 'false'; //Page 
			$plot_section21 = 'false'; //Page 
			$plot_section21ls = 'false'; //Page 
			$plot_section21ld = 'false'; //Page 
			$plot_section21g = 'false'; //Page 
			$plot_section22 = 'false'; //Page 
			$plot_section22ls = 'false'; //Page 
			$plot_section22ld = 'false'; //Page 
			$plot_section22g = 'false'; //Page 
			$plot_section23 = 'false'; //Page 
			$plot_section23ls = 'false'; //Page 
			$plot_section23ld = 'false'; //Page 
			$plot_section23g = 'false'; //Page 
			$plot_section24 = 'false'; //Page 
			$plot_section24ls = 'false'; //Page 
			$plot_section24ld = 'false'; //Page 
			$plot_section24g = 'false'; //Page 
			$plot_section25 = 'false'; //Page 
			$plot_section26 = 'false'; //Page 
			$plot_section27 = 'false'; //Page 
			$plot_section28 = 'false'; //Page 
			$plot_section29 = 'false'; //Page 
			$plot_section30 = 'false'; //Page 
			$plot_section31 = 'false'; //Page 
			$plot_section32 = 'false'; //Page 
			$plot_section33 = 'false'; //Page 
			$plot_section34 = 'false'; //Page 
			$plot_section35 = 'false'; //Page 
			$plot_section36 = 'false'; //Page 
			$plot_section37 = 'false'; //Page 
			$plot_section38 = 'false'; //Page 
			$plot_section39 = 'false'; //Page 
			$plot_section40 = 'false'; //Page 
		}

		// Hide Right all traces graphs when they are combined with left ones.
		if ($consistency_graphs_as_overlay) {
			$plot_section9 = 'false';  //Page 
			$plot_section9ls = 'false';  //Page 
			$plot_section9ld = 'false';  //Page 
			$plot_section9g = 'false';  //Page 
			$plot_section11 = 'false';  //Page 
			$plot_section11ls = 'false';  //Page 
			$plot_section11ld = 'false';  //Page 
			$plot_section11g = 'false';  //Page 
			$plot_section14 = 'false'; //Page 
			$plot_section14ls = 'false'; //Page 
			$plot_section14ld = 'false'; //Page 
			$plot_section14g = 'false'; //Page 
			$plot_section18 = 'false'; //Page 
			$plot_section18ls = 'false'; //Page 
			$plot_section18ld = 'false'; //Page 
			$plot_section18g = 'false'; //Page 
			$plot_section23 = 'false'; //Page 
			$plot_section23ls = 'false'; //Page 
			$plot_section23ld = 'false'; //Page 
			$plot_section23g = 'false'; //Page 
			$plot_section24 = 'false'; //Page 
			$plot_section24ls = 'false'; //Page 
			$plot_section24ld = 'false'; //Page 
			$plot_section24g = 'false'; //Page 
			$plot_section28 = 'false'; //Page 
			$plot_section30 = 'false'; //Page 
			$plot_section33 = 'false'; //Page 
			$plot_section37 = 'false'; //Page 
			$plot_section40 = 'false'; //Page 
		}

		// Remove upper body pages if using lower body marker set.
		if (strpos($this->session->subsessions[0]['Type'],'Lower body session') !== false) {
			$plot_section12 = 'false'; 
			$plot_section12ls = 'false'; 
			$plot_section12ld = 'false'; 
			$plot_section12g = 'false'; 
			$plot_section13 = 'false';
			$plot_section13ls = 'false';
			$plot_section13ld = 'false';
			$plot_section13g = 'false';
			$plot_section14 = 'false';
			$plot_section14ls = 'false';
			$plot_section14ld = 'false';
			$plot_section14g = 'false';
			$plot_section31 = 'false'; 
			$plot_section32 = 'false'; 
			$plot_section33 = 'false'; 
		}

		// Remove MAP page if normative file does not exists.
		if (strpos($this->session->fields['Normative_data'], 'Custom') !== false || file_exists($normative_file) === false) {
			$plot_section44 = 'false'; //Page 
			$plot_section45 = 'false'; //Page 
			$plot_section46 = 'false'; //Page 
		}

		//Hide OFM pages if OFM foot is not used
		if (strcmp('Oxford', $this->session->subsessions[0]['Multisegment_foot']) !== 0) {
			$plot_section16 = 'false';// OFM
			$plot_section16ls = 'false';// OFM
			$plot_section16ld = 'false';// OFM
			$plot_section16g = 'false';// OFM
			$plot_section17 = 'false';// OFM
			$plot_section17ls = 'false';// OFM
			$plot_section17ld = 'false';// OFM
			$plot_section17g = 'false';// OFM
			$plot_section18 = 'false';// OFM
			$plot_section18ls = 'false';// OFM
			$plot_section18ld = 'false';// OFM
			$plot_section18g = 'false';// OFM
			$plot_section35 = 'false';
			$plot_section36 = 'false';
			$plot_section37 = 'false'; //Page 
		}
		
		//Hide RFM pages if RFM foot is not used
		if (strcmp('Rizzoli', $this->session->subsessions[0]['Multisegment_foot']) !== 0) {
			$plot_section19 = 'false';// OFM
			$plot_section19ls = 'false';// OFM
			$plot_section19ld = 'false';// OFM
			$plot_section19g = 'false';// OFM
			$plot_section20 = 'false';// OFM
			$plot_section20ls = 'false';// OFM
			$plot_section20ld = 'false';// OFM
			$plot_section20g = 'false';// OFM
			$plot_section21 = 'false';// OFM
			$plot_section21ls = 'false';// OFM
			$plot_section21ld = 'false';// OFM
			$plot_section21g = 'false';// OFM
			$plot_section22 = 'false';// OFM
			$plot_section22ls = 'false';// OFM
			$plot_section22ld = 'false';// OFM
			$plot_section22g = 'false';// OFM
			$plot_section23 = 'false';// OFM
			$plot_section23ls = 'false';// OFM
			$plot_section23ld = 'false';// OFM
			$plot_section23g = 'false';// OFM
			$plot_section24 = 'false';// OFM
			$plot_section24ls = 'false';// OFM
			$plot_section24ld = 'false';// OFM
			$plot_section24g = 'false';// OFM
			$plot_section38 = 'false';
			$plot_section39 = 'false'; //Page 
			$plot_section40 = 'false'; //Page 
		}

		// Remove MAP page if only 1 trial exists (works only for single session, two session will print empty table).
		//if (strpos($analysisName, 'Comparison') === false && $this->session->countDynamicTrials == 1) {
			//$plot_section33 = 'false'; //Page 
			//$plot_section34 = 'false'; //Page 
			//$plot_section35 = 'false'; //Page 
		//}

		// Handle EMG pages.
		if (strpos($analysisName, 'Comparison') === false && !$includes_noraxon && !$includes_delsys_trigno && !$includes_mega_me6000 && !$includes_myon && !$includes_analog_EMG) {
			$plot_section47 = 'false';
			$plot_section48 = 'false';
			$plot_section49 = 'false';
			$plot_section50 = 'false';
		}
		else if (strpos($analysisName, 'Comparison') === false && ($includes_noraxon || $includes_delsys_trigno || $includes_mega_me6000 || $includes_myon || $includes_analog_EMG)) {
			$plot_section49 = 'false'; //Page 
			$plot_section50 = 'false'; //Page 
		}
		else if (strpos($analysisName, 'Comparison') !== false && $EMG_pre_exists == 'no' && $EMG_post_exists == 'no') {
			$plot_section47 = 'false';
			$plot_section48 = 'false';
			$plot_section49 = 'false';
			$plot_section50 = 'false';
		}
		else if (strpos($analysisName, 'Comparison') !== false && $EMG_pre_exists == 'no') {
			$plot_section47 = 'false'; //Page 16 EMG - pre
			$plot_section48 = 'false'; //Page 17 EMG - pre 
		}
		else if (strpos($analysisName, 'Comparison') !== false && $EMG_post_exists == 'no') {
			$plot_section49 = 'false'; //Page 18 EMG - post
			$plot_section50 = 'false'; //Page 19 EMG - post
		}

		if ($plot_type_EMG == 'mean_of_trials' || $raw_EMG_as_overlay) {
			$plot_section48 = 'false'; //Page 17 EMG 
			$plot_section50 = 'false'; //Page 19 EMG 
		}

		// For comparison report convert additional text files to XML.
		if (strpos($analysisName, 'Comparison') !== false) {
			$convert_files ="
				<file action=\"addToWord\" dest=\"\$data\" format=\"xml\" name=\"session_pre\" src=\"\$data\"/>
				<file action=\"addToWord\" dest=\"\$data\" format=\"xml\" name=\"meta_pre\" src=\"\$data\"/>
				<file action=\"addToWord\" dest=\"\$data\" format=\"xml\" name=\"metrics_pre\" src=\"\$data\"/>
			";
			$meta_data_pre ="
				<text tag=\"examination_date_pre\" xpath=\"meta/creation_date_formatted\" src=\"meta_pre.xml\"/>
				<text tag=\"examination_date_post\" xpath=\"meta/creation_date_formatted\" src=\"meta.xml\"/>
				<text tag=\"GMFCS_pre\" xpath=\"Subject/Session/Fields/Gross_Motor_Function_Classification\" src=\"session_pre.xml\"/>
				<text tag=\"FMS_pre\" xpath=\"Subject/Session/Fields/Functional_Mobility_Scale\" src=\"session_pre.xml\"/>
				<text tag=\"test_condition_pre\" xpath=\"Subject/Session/Subsession/Fields/Test_condition\" src=\"session_pre.xml\"/>
				<text tag=\"test_condition_post\" xpath=\"Subject/Session/Subsession/Fields/Test_condition\" src=\"session.xml\"/>
			";
		}
		else {
			$meta_data_pre ="
				<text tag=\"examination_date_pre\" xpath=\"meta/creation_date_formatted\" src=\"meta.xml\"/>
				<text tag=\"test_condition_pre\" xpath=\"Subject/Session/Subsession/Fields/Test_condition\" src=\"session.xml\"/>
				<text tag=\"test_condition_post\" xpath=\"Subject/Session/Subsession/Fields/Test_condition\" src=\"session.xml\"/>
			";
		}

		if ($consistency_graphs_as_overlay) {
			$page_title_LB_kinematics = "Consistency Graphs – Lower Body Kinematics";
			$page_title_kinetics = "Consistency Graphs – Kinetics";
			$page_title_UB_kinematics = "Consistency Graphs – Upper Body Kinematics";
			$page_title_OFM_kinematics = "Consistency Graphs – Oxford Foot Kinematics";
			$page_title_RFM_kinematics = "Consistency Graphs – Rizzoli Foot Kinematics";
			$page_title_RFM_kinematics2 = "Consistency Graphs – Rizzoli Foot Kinematics";
		}
		else {
			$page_title_LB_kinematics = "Left Lower body Joint Angles - All Traces ";
			$page_title_kinetics = "Left Joint Kinetics – All Traces";
			$page_title_UB_kinematics = "Left Upper Body Joint Angles - All Traces ";
			$page_title_OFM_kinematics = "Left Oxford Foot Angles - All Traces ";
			$page_title_RFM_kinematics = "Left Rizzoli Foot Joint Angles - All Traces ";
			$page_title_RFM_kinematics2 = "Left Rizzoli Foot Planar Angles - All Traces ";
		}

		if ($this->session->subsessions[0]['Right_foot_normalised_to_static_trial'] === 'False' && $this->session->subsessions[0]['Left_foot_normalised_to_static_trial'] === 'True' ) {
			$foot_normalized_comment = 'Left foot segment used for joint angle calculations has been normalised to foot position at static trial . ';
		}
		else if ($this->session->subsessions[0]['Right_foot_normalised_to_static_trial'] === 'True' && $this->session->subsessions[0]['Left_foot_normalised_to_static_trial'] === 'False' ) {
			$foot_normalized_comment = 'Right foot segment used for joint angle calculations has been normalised to foot position at static trial . ';
		}
		else if ($this->session->subsessions[0]['Right_foot_normalised_to_static_trial'] === 'True' && $this->session->subsessions[0]['Left_foot_normalised_to_static_trial'] === 'True' ) {
			$foot_normalized_comment = 'Left and right foot segments used for joint angle calculations have been normalised to foot position at static trial . ';
		}
		else {
			$foot_normalized_comment = '';
		}
		
		ob_start();?>
<?php
		$xml = '<?xml version="1.0" ?>' . "\n" . ob_get_clean();
		
		$dest ="<objects>
		<sections>
			<section id=\"0\" include=\"" . $plot_section0."\"/>
			<section id=\"1\" include=\"" . $plot_section1."\"/>
			<section id=\"2\" include=\"" . $plot_section2."\"/>
			<section id=\"3\" include=\"" . $plot_section3."\"/>
			<section id=\"4\" include=\"" . $plot_section4."\"/>
			<section id=\"5\" include=\"" . $plot_section5."\"/>
			<section id=\"6\" include=\"" . $plot_section6."\"/>
			<section id=\"7\" include=\"" . $plot_section6ls."\"/>
			<section id=\"8\" include=\"" . $plot_section6ld."\"/>
			<section id=\"9\" include=\"" . $plot_section6g."\"/>
			<section id=\"10\" include=\"" . $plot_section7."\"/>
			<section id=\"11\" include=\"" . $plot_section7ls."\"/>
			<section id=\"12\" include=\"" . $plot_section7ld."\"/>
			<section id=\"13\" include=\"" . $plot_section7g."\"/>
			<section id=\"14\" include=\"" . $plot_section8."\"/>
			<section id=\"15\" include=\"" . $plot_section8ls."\"/>
			<section id=\"16\" include=\"" . $plot_section8ld."\"/>
			<section id=\"17\" include=\"" . $plot_section8g."\"/>
			<section id=\"18\" include=\"" . $plot_section9."\"/>
			<section id=\"19\" include=\"" . $plot_section9ls."\"/>
			<section id=\"20\" include=\"" . $plot_section9ld."\"/>
			<section id=\"21\" include=\"" . $plot_section9g."\"/>
			<section id=\"22\" include=\"" . $plot_section10."\"/>
			<section id=\"23\" include=\"" . $plot_section10ls."\"/>
			<section id=\"24\" include=\"" . $plot_section10ld."\"/>
			<section id=\"25\" include=\"" . $plot_section10g."\"/>
			<section id=\"26\" include=\"" . $plot_section11."\"/>
			<section id=\"27\" include=\"" . $plot_section11ls."\"/>
			<section id=\"28\" include=\"" . $plot_section11ld."\"/>
			<section id=\"29\" include=\"" . $plot_section11g."\"/>
			<section id=\"30\" include=\"" . $plot_section12."\"/>
			<section id=\"31\" include=\"" . $plot_section12ls."\"/>
			<section id=\"32\" include=\"" . $plot_section12ld."\"/>
			<section id=\"33\" include=\"" . $plot_section12g."\"/>
			<section id=\"34\" include=\"" . $plot_section13."\"/>
			<section id=\"35\" include=\"" . $plot_section13ls."\"/>
			<section id=\"36\" include=\"" . $plot_section13ld."\"/>
			<section id=\"37\" include=\"" . $plot_section13g."\"/>
			<section id=\"38\" include=\"" . $plot_section14."\"/>
			<section id=\"39\" include=\"" . $plot_section14ls."\"/>
			<section id=\"40\" include=\"" . $plot_section14ld."\"/>
			<section id=\"41\" include=\"" . $plot_section14g."\"/>
			<section id=\"42\" include=\"" . $plot_section15."\"/>
			<section id=\"43\" include=\"" . $plot_section16."\"/>
			<section id=\"44\" include=\"" . $plot_section16ls."\"/>
			<section id=\"45\" include=\"" . $plot_section16ld."\"/>
			<section id=\"46\" include=\"" . $plot_section16g."\"/>
			<section id=\"47\" include=\"" . $plot_section17."\"/>
			<section id=\"48\" include=\"" . $plot_section17ls."\"/>
			<section id=\"49\" include=\"" . $plot_section17ld."\"/>
			<section id=\"50\" include=\"" . $plot_section17g."\"/>
			<section id=\"51\" include=\"" . $plot_section18."\"/>
			<section id=\"52\" include=\"" . $plot_section18ls."\"/>
			<section id=\"53\" include=\"" . $plot_section18ld."\"/>
			<section id=\"54\" include=\"" . $plot_section18g."\"/>
			<section id=\"55\" include=\"" . $plot_section19."\"/>
			<section id=\"56\" include=\"" . $plot_section19ls."\"/>
			<section id=\"57\" include=\"" . $plot_section19ld."\"/>
			<section id=\"58\" include=\"" . $plot_section19g."\"/>
			<section id=\"59\" include=\"" . $plot_section20."\"/>
			<section id=\"60\" include=\"" . $plot_section20ls."\"/>
			<section id=\"61\" include=\"" . $plot_section20ld."\"/>
			<section id=\"62\" include=\"" . $plot_section20g."\"/>
			<section id=\"63\" include=\"" . $plot_section21."\"/>
			<section id=\"64\" include=\"" . $plot_section21ls."\"/>
			<section id=\"65\" include=\"" . $plot_section21ld."\"/>
			<section id=\"66\" include=\"" . $plot_section21g."\"/>
			<section id=\"67\" include=\"" . $plot_section22."\"/>
			<section id=\"68\" include=\"" . $plot_section22ls."\"/>
			<section id=\"69\" include=\"" . $plot_section22ld."\"/>
			<section id=\"70\" include=\"" . $plot_section22g."\"/>
			<section id=\"71\" include=\"" . $plot_section23."\"/>
			<section id=\"72\" include=\"" . $plot_section23ls."\"/>
			<section id=\"73\" include=\"" . $plot_section23ld."\"/>
			<section id=\"74\" include=\"" . $plot_section23g."\"/>
			<section id=\"75\" include=\"" . $plot_section24."\"/>
			<section id=\"76\" include=\"" . $plot_section24ls."\"/>
			<section id=\"77\" include=\"" . $plot_section24ld."\"/>
			<section id=\"78\" include=\"" . $plot_section24g."\"/>
			<section id=\"79\" include=\"" . $plot_section25."\"/>
			<section id=\"80\" include=\"" . $plot_section26."\"/>
			<section id=\"81\" include=\"" . $plot_section27."\"/>
			<section id=\"82\" include=\"" . $plot_section28."\"/>
			<section id=\"83\" include=\"" . $plot_section29."\"/>
			<section id=\"84\" include=\"" . $plot_section30."\"/>
			<section id=\"85\" include=\"" . $plot_section31."\"/>
			<section id=\"86\" include=\"" . $plot_section32."\"/>
			<section id=\"87\" include=\"" . $plot_section33."\"/>
			<section id=\"88\" include=\"" . $plot_section34."\"/>
			<section id=\"89\" include=\"" . $plot_section35."\"/>
			<section id=\"90\" include=\"" . $plot_section36."\"/>
			<section id=\"91\" include=\"" . $plot_section37."\"/>
			<section id=\"92\" include=\"" . $plot_section38."\"/>
			<section id=\"93\" include=\"" . $plot_section39."\"/>
			<section id=\"94\" include=\"" . $plot_section40."\"/>
			<section id=\"95\" include=\"" . $plot_section41."\"/>
			<section id=\"96\" include=\"" . $plot_section42."\"/>
			<section id=\"97\" include=\"" . $plot_section43."\"/>
			<section id=\"98\" include=\"" . $plot_section44."\"/>
			<section id=\"99\" include=\"" . $plot_section45."\"/>
			<section id=\"100\" include=\"" . $plot_section46."\"/>
			<section id=\"101\" include=\"" . $plot_section47."\"/>
			<section id=\"102\" include=\"" . $plot_section48."\"/>
			<section id=\"103\" include=\"" . $plot_section49."\"/>
			<section id=\"104\" include=\"" . $plot_section50."\"/>
			<section id=\"105\" include=\"" . $plot_section51."\"/>
		</sections>
		
		<files>
			<file action=\"addToWord\" dest=\"\$data\" format=\"xml\" name=\"session\" src=\"\$data\"/>
			<file action=\"addToWord\" dest=\"\$data\" format=\"xml\" name=\"meta\" src=\"\$data\"/>
			<file action=\"addToWord\" dest=\"\$data\" format=\"xml\" name=\"metrics\" src=\"\$data\"/>
			".$convert_files."
		</files>

		<items>
			<picture tag=\"\$logo1\" type=\"picture\" src=\"" . $picturesDirectory . "logo1.png\" />
			<picture tag=\"\$signature1\" type=\"picture\" src=\"" . $picturesDirectory . "signature1.png\" />
			<picture tag=\"\$signature2\" type=\"picture\" src=\"" . $picturesDirectory . "signature2.png\" />
			<picture tag=\"\$signature3\" type=\"picture\" src=\"" . $picturesDirectory . "signature3.png\" />
			<picture tag=\"\$signature4\" type=\"picture\" src=\"" . $picturesDirectory . "signature4.png\" />
			<text tag=\"signed_by_1_name\" value=\"$signed_by_1_name\"/>
			<text tag=\"signed_by_1_position\" value=\"$signed_by_1_position\"/>
			<text tag=\"signed_by_2_name\" value=\"$signed_by_2_name\"/>
			<text tag=\"signed_by_2_position\" value=\"$signed_by_2_position\"/>
			<text tag=\"signed_by_3_name\" value=\"$signed_by_3_name\"/>
			<text tag=\"signed_by_3_position\" value=\"$signed_by_3_position\"/>
			<text tag=\"signed_by_4_name\" value=\"$signed_by_4_name\"/>
			<text tag=\"signed_by_4_position\" value=\"$signed_by_4_position\"/>
			<text tag=\"LB_page_title\" value=\"$page_title_LB_kinematics\"/>
			<text tag=\"Kinetics_page_title\" value=\"$page_title_kinetics\"/>
			<text tag=\"UB_page_title\" value=\"$page_title_UB_kinematics\"/>
			<text tag=\"OFM_page_title\" value=\"$page_title_OFM_kinematics\"/>
			<text tag=\"RFM_page_title\" value=\"$page_title_RFM_kinematics\"/>
			<text tag=\"RFM_page_title2\" value=\"$page_title_RFM_kinematics2\"/>
			<text tag=\"foot_normalized_comment\" value=\"$foot_normalized_comment\"/>
			" . $meta_data_pre . "
		";

		$xml .= $dest;
		$events = new \SimpleXMLElement(file_get_contents($this->workingDirectory . 'events.xml'));

		foreach ($filenames as $key => $filename) {
			if (strpos($filename, 'Static') === false) {
				$lto_l[]         = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'LHS_to_LTO_pct_MEAN\']/component/@data')[0] * 100;
				$rto_r[]         = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'RHS_to_RTO_pct_MEAN\']/component/@data')[0] * 100;
				$lhs[]           = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'LHS_to_RHS_pct_MEAN\']/component/@data')[0] * 100;
				$rhs[]           = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'RHS_to_LHS_pct_MEAN\']/component/@data')[0] * 100;
				$lto_r[]         = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'RHS_to_LTO_pct_MEAN\']/component/@data')[0] * 100;
				$rto_l[]         = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'LHS_to_RTO_pct_MEAN\']/component/@data')[0] * 100;
				$lto_l_emg_raw[] = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'LHS_to_LTO_pct_MEAN\']/component/@data')[0] * 1000;
				$rto_r_emg_raw[] = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'RHS_to_RTO_pct_MEAN\']/component/@data')[0] * 1000;
				$lhs_emg_raw[]   = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'LHS_to_RHS_pct_MEAN\']/component/@data')[0] * 1000;
				$rhs_emg_raw[]   = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'RHS_to_LHS_pct_MEAN\']/component/@data')[0] * 1000;
				$lto_r_emg_raw[] = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'RHS_to_LTO_pct_MEAN\']/component/@data')[0] * 1000;
				$rto_l_emg_raw[] = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'LHS_to_RTO_pct_MEAN\']/component/@data')[0] * 1000;
			}
		}

		//Remove events with the value 0 (caused by missing events resulting in "nodata" in Visual3D XML output)
		$lto_l = $this->removeValueFromArray(0, $lto_l);
		$rto_r = $this->removeValueFromArray(0, $rto_r);
		$lhs = $this->removeValueFromArray(0, $lhs);
		$rhs = $this->removeValueFromArray(0, $rhs);
		$lto_r = $this->removeValueFromArray(0, $lto_r);
		$rto_l = $this->removeValueFromArray(0, $rto_l);

		$events	      = new \SimpleXMLElement(file_get_contents($this->workingDirectory . 'events_global.xml'));
		$lto_l_global = (float)$events->xpath('/v3d/owner/type/folder/name[@value=\'LHS_to_LTO_pct_MEAN\']/component/@data')[0] * 100;
		$rto_r_global = (float)$events->xpath('/v3d/owner/type/folder/name[@value=\'RHS_to_RTO_pct_MEAN\']/component/@data')[0] * 100;
		$lhs_global	  = (float)$events->xpath('/v3d/owner/type/folder/name[@value=\'LHS_to_RHS_pct_MEAN\']/component/@data')[0] * 100;
		$rhs_global	  = (float)$events->xpath('/v3d/owner/type/folder/name[@value=\'RHS_to_LHS_pct_MEAN\']/component/@data')[0] * 100;
		$lto_r_global = (float)$events->xpath('/v3d/owner/type/folder/name[@value=\'RHS_to_LTO_pct_MEAN\']/component/@data')[0] * 100;
		$rto_l_global = (float)$events->xpath('/v3d/owner/type/folder/name[@value=\'LHS_to_RTO_pct_MEAN\']/component/@data')[0] * 100;

		if (strpos($analysisName, 'Comparison') !== false) {
			$events = new \SimpleXMLElement(file_get_contents($this->workingDirectory . 'events_pre.xml'));
			
			foreach ($filenames_array_pre as $key => $filename) {
				$lto_l_pre[]         = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'LHS_to_LTO_pct_MEAN\']/component/@data')[0] * 100;
				$rto_r_pre[]         = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'RHS_to_RTO_pct_MEAN\']/component/@data')[0] * 100;
				$lhs_pre[]           = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'LHS_to_RHS_pct_MEAN\']/component/@data')[0] * 100;
				$rhs_pre[]           = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'RHS_to_LHS_pct_MEAN\']/component/@data')[0] * 100;
				$lto_r_pre[]         = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'RHS_to_LTO_pct_MEAN\']/component/@data')[0] * 100;
				$rto_l_pre[]         = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'LHS_to_RTO_pct_MEAN\']/component/@data')[0] * 100;
				$lto_l_emg_raw_pre[] = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'LHS_to_LTO_pct_MEAN\']/component/@data')[0] * 1000;
				$rto_r_emg_raw_pre[] = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'RHS_to_RTO_pct_MEAN\']/component/@data')[0] * 1000;
				$lhs_emg_raw_pre[]   = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'LHS_to_RHS_pct_MEAN\']/component/@data')[0] * 1000;
				$rhs_emg_raw_pre[]   = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'RHS_to_LHS_pct_MEAN\']/component/@data')[0] * 1000;
				$lto_r_emg_raw_pre[] = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'RHS_to_LTO_pct_MEAN\']/component/@data')[0] * 1000;
				$rto_l_emg_raw_pre[] = (float)$events->xpath('/v3d/owner[@value=\'' . $filename.'\']/type/folder/name[@value=\'LHS_to_RTO_pct_MEAN\']/component/@data')[0] * 1000;
			}

			$events           = new \SimpleXMLElement(file_get_contents($this->workingDirectory . 'events_global_pre.xml'));
			$lto_l_global_pre = (float)$events->xpath('/v3d/owner/type/folder/name[@value=\'LHS_to_LTO_pct_MEAN\']/component/@data')[0] * 100;
			$rto_r_global_pre = (float)$events->xpath('/v3d/owner/type/folder/name[@value=\'RHS_to_RTO_pct_MEAN\']/component/@data')[0] * 100;
			$lhs_global_pre   = (float)$events->xpath('/v3d/owner/type/folder/name[@value=\'LHS_to_RHS_pct_MEAN\']/component/@data')[0] * 100;
			$rhs_global_pre   = (float)$events->xpath('/v3d/owner/type/folder/name[@value=\'RHS_to_LHS_pct_MEAN\']/component/@data')[0] * 100;
			$lto_r_global_pre = (float)$events->xpath('/v3d/owner/type/folder/name[@value=\'RHS_to_LTO_pct_MEAN\']/component/@data')[0] * 100;
			$rto_l_global_pre = (float)$events->xpath('/v3d/owner/type/folder/name[@value=\'LHS_to_RTO_pct_MEAN\']/component/@data')[0] * 100;
		}

		foreach ($link_model_signal_names_short as $signal) {
			// Defines default format of y-axis numbers.
			$y_axis_format = '0.0';

			// Add underscore to signal names.
			$signal_un = str_replace(' ', '_', $signal);

			//define normative events
			if (strstr($signal_un, 'GRF')) {
				$norm_range = 'RON_ROFF';
				$y_axis_format = '0.00';
			}
			else if (strstr($signal_un, 'Moment') || strstr($signal_un, 'Power')) {
				$norm_range = 'RON_RHS';
			}
			else {
				$norm_range = 'RHS_RHS';
			}
			
			//set number of horizontal ticks
			$majorunit_x = $y_range[$signal_un.'_X_major'];
			$majorunit_y = $y_range[$signal_un.'_Y_major'];
			$majorunit_z = $y_range[$signal_un.'_Z_major'];

			// Set min and max value for small ticks (event markers) - depend on y-range.
			$tick_max_x = $y_range[$signal_un.'_X_max'];
			$tick_min_x = $y_range[$signal_un.'_X_max'] - ($y_range[$signal_un.'_X_max'] - $y_range[$signal_un.'_X_min']) / 10;
			$tick_max_y = $y_range[$signal_un.'_Y_max'];
			$tick_min_y = $y_range[$signal_un.'_Y_max'] - ($y_range[$signal_un.'_Y_max'] - $y_range[$signal_un.'_Y_min']) / 10;
			$tick_max_z = $y_range[$signal_un.'_Z_max'];
			$tick_min_z = $y_range[$signal_un.'_Z_max'] - ($y_range[$signal_un.'_Z_max'] - $y_range[$signal_un.'_Z_min']) / 10;
			
			// X label.
			if (strpos($signal, 'Pelvic') !== false || strpos($signal, 'Thorax') !== false) {
				$upper_x = 'Fwd';
				$lower_x = 'Bwd';
			}
			else if ((strpos($signal, 'Ankle') !== false && strpos($signal, 'Angle') !== false) || strpos($signal, 'IORFoot') !== false || strpos($signal, 'Hindfoot') !== false || strpos($signal, 'Midfoot') !== false || strpos($signal, 'Forefoot') !== false) {
				$upper_x = 'Dorsi';
				$lower_x = 'Plantar';
			}
			else if (strpos($signal, 'Ankle') !== false && strpos($signal, 'Moment') !== false) {
				$upper_x = 'Plantar';
				$lower_x = 'Dorsi';
			}
			else if (strpos($signal, 'Moment') !== false) {
				$upper_x = 'Ext';
				$lower_x = 'Flex';
			}
			else if (strpos($signal, 'Power') !== false) {
				$upper_x = 'Generation';
				$lower_x = 'Absorption';
			}
			else if (strpos($signal, 'GRF') !== false) {
				$upper_x = 'Medial';
				$lower_x = 'Lateral';
			}
			else if (strpos($signal, 'Pitch') !== false) {
				$upper_x = 'Toe up';
				$lower_x = 'Toe down';
			}
			else if (strpos($signal, 'Arch Height') !== false || strpos($signal, 'MLA') !== false) {
				$upper_x = '';
				$lower_x = '';
			}
			else if (strpos($signal, '2G') !== false) {
				$upper_x = 'Dorsi';
				$lower_x = 'Plantar';
			}
			else if (strpos($signal, 'F2Ps') !== false) {
				$upper_x = 'Pitch+ ';
				$lower_x = 'Pitch-';
			}
			else if (strpos($signal, '2V') !== false  || strpos($signal, '2F') !== false) {
				$upper_x = 'Div';
				$lower_x = 'Conv';
			}
			else if (strpos($signal, 'F2Pt') !== false) {
				$upper_x = 'Abd';
				$lower_x = 'Add';
			}
			else {
				$upper_x = 'Flex';
				$lower_x = 'Ext';
			}
			//y label
			if (strpos($signal, 'Pelvic') !== false || strpos($signal, 'Thorax') !== false) {
				$upper_y = 'Up';
				$lower_y = 'Down';
			}
			else if ((strpos($signal, 'Ankle') !== false && strpos($signal, 'Angle') !== false) || strpos($signal, 'IORFoot') !== false || strpos($signal, 'Hindfoot') !== false || strpos($signal, 'Midfoot') !== false || strpos($signal, 'Forefoot') !== false) {
				$upper_y = 'Inversion';
				$lower_y = 'Eversion';
			}
			else if (strpos($signal, 'Ankle') !== false && strpos($signal, 'Moment') !== false) {
				$upper_y = 'Eversion';
				$lower_y = 'Inversion';
			}
			else if (strpos($signal, 'Moment') !== false && strpos($signal, 'Knee') !== false) {
				$upper_y = 'Valgus';
				$lower_y = 'Varus';
			}
			else if (strpos($signal, 'Moment') !== false) {
				$upper_y = 'Abd';
				$lower_y = 'Add';
			}
			else if (strpos($signal, 'Power') !== false) {
				$upper_y = 'Generation';
				$lower_y = 'Absorption';
			}
			else if (strpos($signal, 'GRF') !== false) {
				$upper_y = 'Ant';
				$lower_y = 'Post';
			}
			else {
				$upper_y = 'Add';
				$lower_y = 'Abd';
			}
			//z label
			if (strpos($signal, 'GRF') !== false) {
				$upper_z = ' ';
				$lower_z = ' ';
			}
			else if (strpos($signal, 'IORFoot') !== false || strpos($signal, 'Hindfoot') !== false || strpos($signal, 'Midfoot') !== false || strpos($signal, 'Forefoot') !== false) {
				$upper_z = 'Add';
				$lower_z = 'Abd';
			}
			else {
				$upper_z = 'Int';
				$lower_z = 'Ext';
			}

			//y axis units
			if (strpos($signal, 'Moment') !== false) {
				$y_axis_units = 'Nm/kg';
			}
			else if(strpos($signal, 'Power') !== false) {
				$y_axis_units = 'W/kg';
			}
			else if(strpos($signal, 'GRF') !== false) {
				$y_axis_units = 'N/kg';
			}
			else {
				$y_axis_units = 'degrees';
			}
				
			// Mean graphs and comparison graphs ----------------------------
			foreach ($pre_post as $condition) {
				$line_style = '';
				
				if (strpos($analysisName, 'Comparison') === false) {
					$timeseries_means = "timeseries_means.xml:v3d/*/type[@value='P2D']/folder[@value='TIMESERIES']";
				}
				else if (strpos($analysisName, 'Comparison') !== false && strpos($condition, '_post') === false) {
					$timeseries_means = "timeseries_means_pre.xml:v3d/*/type[@value='P2D']/folder[@value='TIMESERIES']";
					
					if ($comparison_as_overlay)
					{
						$timeseries_means_overlay_x = "
							<series name=\"Left "  . $signal . "\" template=\"" . $line_color_left  . "_line\" y-xpath=\"timeseries_means.xml:v3d/*/type[@value='P2D']/folder[@value='TIMESERIES']/name[@value='Left "  . $signal . "_MEAN']/component[@value='X']/@data\"/>
							<series name=\"Right " . $signal . "\" template=\"" . $line_color_right . "_line\" y-xpath=\"timeseries_means.xml:v3d/*/type[@value='P2D']/folder[@value='TIMESERIES']/name[@value='Right " . $signal . "_MEAN']/component[@value='X']/@data\"/>
						";			
						$timeseries_means_overlay_y = "
							<series name=\"Left "  . $signal . "\" template=\"" . $line_color_left  . "_line\" y-xpath=\"timeseries_means.xml:v3d/*/type[@value='P2D']/folder[@value='TIMESERIES']/name[@value='Left "  . $signal . "_MEAN']/component[@value='Y']/@data\"/>
							<series name=\"Right " . $signal . "\" template=\"" . $line_color_right . "_line\" y-xpath=\"timeseries_means.xml:v3d/*/type[@value='P2D']/folder[@value='TIMESERIES']/name[@value='Right " . $signal . "_MEAN']/component[@value='Y']/@data\"/>
						";			
						$timeseries_means_overlay_z = "
							<series name=\"Left "  . $signal . "\" template=\"" . $line_color_left  . "_line\" y-xpath=\"timeseries_means.xml:v3d/*/type[@value='P2D']/folder[@value='TIMESERIES']/name[@value='Left "  . $signal . "_MEAN']/component[@value='Z']/@data\"/>
							<series name=\"Right " . $signal . "\" template=\"" . $line_color_right . "_line\" y-xpath=\"timeseries_means.xml:v3d/*/type[@value='P2D']/folder[@value='TIMESERIES']/name[@value='Right " . $signal . "_MEAN']/component[@value='Z']/@data\"/>
						";	
						
						$line_style = '_dotted';
					}
				}
				else if (strpos($analysisName, 'Comparison') !== false && strpos($condition, '_post') !== false) {
					$timeseries_means = "timeseries_means.xml:v3d/*/type[@value='P2D']/folder[@value='TIMESERIES']";
				}
				
				// Do not plot events in GRF graphs.
				if (strpos($signal, 'GRF') === false) {
					//Word events
					$plot_events_x= "
						\t\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_left  . "_line_thin\" x-values=\"" . $lto_r_global . "," . $lto_r_global . "\" y-values=\""              . $tick_min_x . "," . $tick_max_x . "\"/>
						\t\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_right  . "_line_thin\" x-values=\"" . $rhs_global   . "," . $rhs_global   . "\" y-values=\""              . $tick_min_x . "," . $tick_max_x . "\"/>
						\t\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_right . "_line_thin\" x-values=\"" . $rto_r_global . "," . $rto_r_global . "\" y-values=\"-5000,5000\"/>
						\t\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_right . "_line_thin\" x-values=\"" . $rto_l_global . "," . $rto_l_global . "\" y-values=\""              . $tick_min_x . "," . $tick_max_x . "\"/>
						\t\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_left . "_line_thin\" x-values=\"" . $lhs_global   . "," . $lhs_global   . "\" y-values=\""              . $tick_min_x . "," . $tick_max_x . "\"/>
						\t\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_left  . "_line_thin\" x-values=\"" . $lto_l_global . "," . $lto_l_global . "\" y-values=\"-5000,5000\"/>
					";
					$plot_events_y= "
						\t\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_left  . "_line_thin\" x-values=\"" . $lto_r_global . "," . $lto_r_global . "\" y-values=\""              . $tick_min_y . "," . $tick_max_y . "\"/>
						\t\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_right  . "_line_thin\" x-values=\"" . $rhs_global   . "," . $rhs_global   . "\" y-values=\""              . $tick_min_y . "," . $tick_max_y . "\"/>
						\t\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_right . "_line_thin\" x-values=\"" . $rto_r_global . "," . $rto_r_global . "\" y-values=\"-5000,5000\"/>
						\t\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_right . "_line_thin\" x-values=\"" . $rto_l_global . "," . $rto_l_global . "\" y-values=\""              . $tick_min_y . "," . $tick_max_y . "\"/>
						\t\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_left . "_line_thin\" x-values=\"" . $lhs_global   . "," . $lhs_global   . "\" y-values=\""              . $tick_min_y . "," . $tick_max_y . "\"/>
						\t\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_left  . "_line_thin\" x-values=\"" . $lto_l_global . "," . $lto_l_global . "\" y-values=\"-5000,5000\"/>
					";
					$plot_events_z= "
						\t\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_left  . "_line_thin\" x-values=\"" . $lto_r_global . "," . $lto_r_global . "\" y-values=\""              . $tick_min_z . "," . $tick_max_z . "\"/>
						\t\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_right  . "_line_thin\" x-values=\"" . $rhs_global   . "," . $rhs_global   . "\" y-values=\""              . $tick_min_z . "," . $tick_max_z . "\"/>
						\t\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_right . "_line_thin\" x-values=\"" . $rto_r_global . "," . $rto_r_global . "\" y-values=\"-5000,5000\"/>
						\t\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_right . "_line_thin\" x-values=\"" . $rto_l_global . "," . $rto_l_global . "\" y-values=\""              . $tick_min_z . "," . $tick_max_z . "\"/>
						\t\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_left . "_line_thin\" x-values=\"" . $lhs_global   . "," . $lhs_global   . "\" y-values=\""              . $tick_min_z . "," . $tick_max_z . "\"/>
						\t\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_left  . "_line_thin\" x-values=\"" . $lto_l_global . "," . $lto_l_global . "\" y-values=\"-5000,5000\"/>
					";
					
					if (strpos($analysisName, "Comparison") !== false && $comparison_as_overlay)
					{
						$plot_events_overlay_x= "
							\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_left  . "_line_thin_dotted\" x-values=\"" . $lto_r_global_pre . "," . $lto_r_global_pre . "\" y-values=\""              . $tick_min_x . "," . $tick_max_x . "\"/>
							\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_right  . "_line_thin_dotted\" x-values=\"" . $rhs_global_pre   . "," . $rhs_global_pre   . "\" y-values=\""              . $tick_min_x . "," . $tick_max_x . "\"/>
							\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_right . "_line_thin_dotted\" x-values=\"" . $rto_r_global_pre . "," . $rto_r_global_pre . "\" y-values=\"-5000,5000\"/>
							\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_right . "_line_thin_dotted\" x-values=\"" . $rto_l_global_pre . "," . $rto_l_global_pre . "\" y-values=\""              . $tick_min_x . "," . $tick_max_x . "\"/>
							\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_left . "_line_thin_dotted\" x-values=\"" . $lhs_global_pre   . "," . $lhs_global_pre   . "\" y-values=\""              . $tick_min_x . "," . $tick_max_x . "\"/>
							\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_left  . "_line_thin_dotted\" x-values=\"" . $lto_l_global_pre . "," . $lto_l_global_pre . "\" y-values=\"-5000,5000\"/>
						";
						$plot_events_overlay_y= "
							\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_left  . "_line_thin_dotted\" x-values=\"" . $lto_r_global_pre . "," . $lto_r_global_pre . "\" y-values=\""              . $tick_min_y . "," . $tick_max_y . "\"/>
							\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_right  . "_line_thin_dotted\" x-values=\"" . $rhs_global_pre   . "," . $rhs_global_pre   . "\" y-values=\""              . $tick_min_y . "," . $tick_max_y . "\"/>
							\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_right . "_line_thin_dotted\" x-values=\"" . $rto_r_global_pre . "," . $rto_r_global_pre . "\" y-values=\"-5000,5000\"/>
							\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_right . "_line_thin_dotted\" x-values=\"" . $rto_l_global_pre . "," . $rto_l_global_pre . "\" y-values=\""              . $tick_min_y . "," . $tick_max_y . "\"/>
							\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_left . "_line_thin_dotted\" x-values=\"" . $lhs_global_pre   . "," . $lhs_global_pre   . "\" y-values=\""              . $tick_min_y . "," . $tick_max_y . "\"/>
							\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_left  . "_line_thin_dotted\" x-values=\"" . $lto_l_global_pre . "," . $lto_l_global_pre . "\" y-values=\"-5000,5000\"/>
						";
						$plot_events_overlay_z= "
							\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_left  . "_line_thin_dotted\" x-values=\"" . $lto_r_global_pre . "," . $lto_r_global_pre . "\" y-values=\""              . $tick_min_z . "," . $tick_max_z . "\"/>
							\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_right  . "_line_thin_dotted\" x-values=\"" . $rhs_global_pre   . "," . $rhs_global_pre   . "\" y-values=\""              . $tick_min_z . "," . $tick_max_z . "\"/>
							\t<series name=\"Right " . $signal . " Event\" template=\"" . $line_color_right . "_line_thin_dotted\" x-values=\"" . $rto_r_global_pre . "," . $rto_r_global_pre . "\" y-values=\"-5000,5000\"/>
							\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_right . "_line_thin_dotted\" x-values=\"" . $rto_l_global_pre . "," . $rto_l_global_pre . "\" y-values=\""              . $tick_min_z . "," . $tick_max_z . "\"/>
							\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_left . "_line_thin_dotted\" x-values=\"" . $lhs_global_pre   . "," . $lhs_global_pre   . "\" y-values=\""              . $tick_min_z . "," . $tick_max_z . "\"/>
							\t<series name=\"Left "  . $signal . " Event\" template=\"" . $line_color_left  . "_line_thin_dotted\" x-values=\"" . $lto_l_global_pre . "," . $lto_l_global_pre . "\" y-values=\"-5000,5000\"/>
						";
					}
				}
				else {
					$plot_events_x         = '';
					$plot_events_y         = '';
					$plot_events_z         = '';
					$plot_events_overlay_x = '';
					$plot_events_overlay_y = '';
					$plot_events_overlay_z = '';
				}
				
				$plot = "
					<chart tag=\"\$" . $signal_un."_X" . $condition."_MEAN\" wordtemplate=\"" . $wordTemplateDirectory . "line_area.crtx\">
						<x-axis max=\"100\" min=\"0\"/>
						<y-axis format=\"" . $y_axis_format."\" majorunit=\"" . $majorunit_x."\" max=\"" . $y_range[$signal_un.'_X_max']."\" min=\"" . $y_range[$signal_un.'_X_min']."\"/>
						<label tag=\"\$upper\" text=\"" . $upper_x."\"/>
						<label tag=\"\$lower\" text=\"" . $lower_x."\"/>
						<label tag=\"\$units\" text=\"$y_axis_units\"/>
						<data>
							<seriesgroup name=\"lines\">
								<series name=\"Left " . $signal."\" template=\"" . $line_color_left."_line" . $line_style."\" y-xpath=\"" . $timeseries_means."/name[@value='Left " . $signal."_MEAN']/component[@value='X']/@data\"/>
								<series name=\"Right " . $signal."\" template=\"" . $line_color_right."_line" . $line_style."\" y-xpath=\"" . $timeseries_means."/name[@value='Right " . $signal."_MEAN']/component[@value='X']/@data\"/>
								" . $timeseries_means_overlay_x . "
							</seriesgroup>
							<seriesgroup name=\"normatives\">
								<series name=\"Right " . $signal." Normal Low\" template=\"normal_low\" y-xpath=\"normative.xml:v3d/owner[@value='GLOBAL']/type[@value='DERIVED']/folder[@value='NORM_LOWER']/name[@value='Right " . $signal."_X_" . $norm_range."']/component[@value='X']/@data\"/>
								<series name=\"Right " . $signal." Normal High\" template=\"normal_high\" y-xpath=\"normative.xml:v3d/owner[@value='GLOBAL']/type[@value='DERIVED']/folder[@value='NORM_RANGE']/name[@value='Right " . $signal."_X_" . $norm_range."']/component[@value='X']/@data\"/>
							</seriesgroup>
							<seriesgroup name=\"events\">
								" . $plot_events_x."
								" . $plot_events_overlay_x."
							</seriesgroup>
						</data>
					</chart>
					<chart tag=\"\$" . $signal_un."_Y" . $condition."_MEAN\" wordtemplate=\"" . $wordTemplateDirectory . "line_area.crtx\">
						<x-axis max=\"100\" min=\"0\"/>
						<y-axis format=\"" . $y_axis_format."\" majorunit=\"" . $majorunit_y."\" max=\"" . $y_range[$signal_un.'_Y_max']."\" min=\"" . $y_range[$signal_un.'_Y_min']."\"/>
						<label tag=\"\$upper\" text=\"" . $upper_y."\"/>
						<label tag=\"\$lower\" text=\"" . $lower_y."\"/>
						<label tag=\"\$units\" text=\"$y_axis_units\"/>
						<data>
							<seriesgroup name=\"lines\">
								<series name=\"Left " . $signal."\" template=\"" . $line_color_left."_line" . $line_style."\" y-xpath=\"" . $timeseries_means."/name[@value='Left " . $signal."_MEAN']/component[@value='Y']/@data\"/>
								<series name=\"Right " . $signal."\" template=\"" . $line_color_right."_line" . $line_style."\" y-xpath=\"" . $timeseries_means."/name[@value='Right " . $signal."_MEAN']/component[@value='Y']/@data\"/>
								" . $timeseries_means_overlay_y."
							</seriesgroup>
							<seriesgroup name=\"normatives\">
								<series name=\"Right " . $signal." Normal Low\" template=\"normal_low\" y-xpath=\"normative.xml:v3d/owner[@value='GLOBAL']/type[@value='DERIVED']/folder[@value='NORM_LOWER']/name[@value='Right " . $signal."_Y_" . $norm_range."']/component[@value='X']/@data\"/>
								<series name=\"Right " . $signal." Normal High\" template=\"normal_high\" y-xpath=\"normative.xml:v3d/owner[@value='GLOBAL']/type[@value='DERIVED']/folder[@value='NORM_RANGE']/name[@value='Right " . $signal."_Y_" . $norm_range."']/component[@value='X']/@data\"/>
							</seriesgroup>
							<seriesgroup name=\"events\">
								" . $plot_events_y."
								" . $plot_events_overlay_y."
							</seriesgroup>
						</data>
					</chart>";
				$xml .= $plot;
				
				if(strpos($signal,'Elbow') !== false && strpos($this->session->subsessions[0]['Model_used'],'CGM') !== false) {
					//do not plot elbow rotation for CGM model
				}
				else {
					$plot = "
					<chart tag=\"\$" . $signal_un."_Z" . $condition."_MEAN\" wordtemplate=\"" . $wordTemplateDirectory . "line_area.crtx\">
						<x-axis max=\"100\" min=\"0\"/>
						<y-axis format=\"" . $y_axis_format."\" majorunit=\"" . $majorunit_z."\" max=\"" . $y_range[$signal_un.'_Z_max']."\" min=\"" . $y_range[$signal_un.'_Z_min']."\"/>
						<label tag=\"\$upper\" text=\"" . $upper_z."\"/>
						<label tag=\"\$lower\" text=\"" . $lower_z."\"/>
						<label tag=\"\$units\" text=\"$y_axis_units\"/>
						<data>
							<seriesgroup name=\"lines\">
								<series name=\"Left " . $signal."\" template=\"" . $line_color_left."_line" . $line_style."\" y-xpath=\"" . $timeseries_means."//name[@value='Left " . $signal."_MEAN']/component[@value='Z']/@data\"/>
								<series name=\"Right " . $signal."\" template=\"" . $line_color_right."_line" . $line_style."\" y-xpath=\"" . $timeseries_means."/name[@value='Right " . $signal."_MEAN']/component[@value='Z']/@data\"/>
								" . $timeseries_means_overlay_z."
							</seriesgroup>
							<seriesgroup name=\"normatives\">
								<series name=\"Right " . $signal." Normal Low\" template=\"normal_low\" y-xpath=\"normative.xml:v3d/owner[@value='GLOBAL']/type[@value='DERIVED']/folder[@value='NORM_LOWER']/name[@value='Right " . $signal."_Z_" . $norm_range."']/component[@value='X']/@data\"/>
								<series name=\"Right " . $signal." Normal High\" template=\"normal_high\" y-xpath=\"normative.xml:v3d/owner[@value='GLOBAL']/type[@value='DERIVED']/folder[@value='NORM_RANGE']/name[@value='Right " . $signal."_Z_" . $norm_range."']/component[@value='X']/@data\"/>
							</seriesgroup>
							<seriesgroup name=\"events\">
								" . $plot_events_z."
								" . $plot_events_overlay_z."
							</seriesgroup>
						</data>
					</chart>";
					$xml .= $plot;
				}
			}

			// All traces graphs ---------------------------------------------
			$side_arr = ['Left_', 'Right_', ''];
			
			foreach ($pre_post as $condition) {
				$timeseries = "timeseries.xml:v3d";
				$type = "type[@value='LINK_MODEL_BASED']";
				$folder = "folder[@value='ORIGINAL']";
				
				if (strpos($signal, 'Ankle Angles') !== false || strpos($this->session->subsessions[0]['Event_mode'],'Instrumented treadmill') !== false || !$auto_range)
					$folder = "folder[@value='PROCESSED']";
						
				if (strpos($signal, 'Arch Height') !== false) {
					$type = "type[@value='DERIVED']";
					$folder = "folder[@value='ARCH_HEIGHT']";
				}
				if (strpos($signal, 'GRF') !== false && strpos($this->session->subsessions[0]['Event_mode'],'Instrumented treadmill') === false && $auto_range) {
					$type = "type[@value='DERIVED']";
					$folder = "folder[@value='FORCE_GRAPH']";
				}
				if (strpos($analysisName, 'Comparison') !== false && strpos($condition, '_post') === false)
					$timeseries = "timeseries_pre.xml:v3d";
				
				foreach ($side_arr as $side) {
					$plot_events_x_left = [];
					$plot_events_y_left = [];
					$plot_events_z_left = [];
					$plot_events_x_right = [];
					$plot_events_y_right = [];
					$plot_events_z_right = [];
					$consistency_graphs_x = '';
					$consistency_graphs_y = '';
					$consistency_graphs_z = '';
					$series_name_x_arr = [];
					$series_name_y_arr = [];
					$series_name_z_arr = [];

					// Word events.
					foreach ($filenames as $key => $filename) {
						if (strpos($filename,'Static') === false) {
							$plot_events_x_left[] = "
								\t\t<series name=\"Left " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_right."_line_thin\" x-values=\"" . $rto_l[$key]."," . $rto_l[$key]."\" y-values=\"" . $tick_min_x."," . $tick_max_x."\"/>
								\t\t<series name=\"Left " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_right."_line_thin\" x-values=\"" . $lhs[$key]."," . $lhs[$key]."\" y-values=\"" . $tick_min_x."," . $tick_max_x."\"/>
								\t\t<series name=\"Left " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_left."_line_thin\" x-values=\"" . $lto_l[$key]."," . $lto_l[$key]."\" y-values=\"-5000,5000\"/>
							";
							$plot_events_y_left[] = "
								\t\t<series name=\"Left " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_right."_line_thin\" x-values=\"" . $rto_l[$key]."," . $rto_l[$key]."\" y-values=\"" . $tick_min_y."," . $tick_max_y."\"/>
								\t\t<series name=\"Left " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_right."_line_thin\" x-values=\"" . $lhs[$key]."," . $lhs[$key]."\" y-values=\"" . $tick_min_y."," . $tick_max_y."\"/>
								\t\t<series name=\"Left " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_left."_line_thin\" x-values=\"" . $lto_l[$key]."," . $lto_l[$key]."\" y-values=\"-5000,5000\"/>
							";
							$plot_events_z_left[] = "
								\t\t<series name=\"Left " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_right."_line_thin\" x-values=\"" . $rto_l[$key]."," . $rto_l[$key]."\" y-values=\"" . $tick_min_z."," . $tick_max_z."\"/>
								\t\t<series name=\"Left " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_right."_line_thin\" x-values=\"" . $lhs[$key]."," . $lhs[$key]."\" y-values=\"" . $tick_min_z."," . $tick_max_z."\"/>
								\t\t<series name=\"Left " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_left."_line_thin\" x-values=\"" . $lto_l[$key]."," . $lto_l[$key]."\" y-values=\"-5000,5000\"/>
							";
							$plot_events_x_right[] = "
								\t\t<series name=\"Right " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_left."_line_thin\" x-values=\"" . $lto_r[$key]."," . $lto_r[$key]."\" y-values=\"" . $tick_min_x."," . $tick_max_x."\"/>
								\t\t<series name=\"Right " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_left."_line_thin\" x-values=\"" . $rhs[$key]."," . $rhs[$key]."\" y-values=\"" . $tick_min_x."," . $tick_max_x."\"/>
								\t\t<series name=\"Right " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_right."_line_thin\" x-values=\"" . $rto_r[$key]."," . $rto_r[$key]."\" y-values=\"-5000,5000\"/>
							";
							$plot_events_y_right[] = "
								\t\t<series name=\"Right " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_left."_line_thin\" x-values=\"" . $lto_r[$key]."," . $lto_r[$key]."\" y-values=\"" . $tick_min_y."," . $tick_max_y."\"/>
								\t\t<series name=\"Right " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_left."_line_thin\" x-values=\"" . $rhs[$key]."," . $rhs[$key]."\" y-values=\"" . $tick_min_y."," . $tick_max_y."\"/>
								\t\t<series name=\"Right " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_right."_line_thin\" x-values=\"" . $rto_r[$key]."," . $rto_r[$key]."\" y-values=\"-5000,5000\"/>
							";
							$plot_events_z_right[] = "
								\t\t<series name=\"Right " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_left."_line_thin\" x-values=\"" . $lto_r[$key]."," . $lto_r[$key]."\" y-values=\"" . $tick_min_z."," . $tick_max_z."\"/>
								\t\t<series name=\"Right " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_left."_line_thin\" x-values=\"" . $rhs[$key]."," . $rhs[$key]."\" y-values=\"" . $tick_min_z."," . $tick_max_z."\"/>
								\t\t<series name=\"Right " . $signal." Event\" trial=\"" . $key."\" template=\"" . $line_color_right."_line_thin\" x-values=\"" . $rto_r[$key]."," . $rto_r[$key]."\" y-values=\"-5000,5000\"/>
							";
						}
					}
							
					// Do not plot events in GRF graphs.
					if (strpos($signal, 'GRF') === false) {
						if ($side == 'Left_') {
							$side2             = 'Left ';
							$line_color        = $line_color_left;
							$plot_events_x     = implode("\n",$plot_events_x_left);
							$plot_events_y     = implode("\n",$plot_events_y_left);
							$plot_events_z     = implode("\n",$plot_events_z_left);

							if ($consistency_graphs_as_overlay) {
								$consistency_graphs_x = "
									<series name=\"Right_" . $signal."\" template=\"" . $line_color_right."_line_thin\" y-xpath=\"" . $timeseries."/*/".$type."/".$folder."/name[@value='Right " . $signal."']/component[@value='X']/@data\"/>
								";
								$consistency_graphs_y = "
									<series name=\"Right_" . $signal."\" template=\"" . $line_color_right."_line_thin\" y-xpath=\"" . $timeseries."/*/".$type."/".$folder."/name[@value='Right " . $signal."']/component[@value='Y']/@data\"/>
								";
								$consistency_graphs_z = "
									<series name=\"Right_" . $signal."\" template=\"" . $line_color_right."_line_thin\" y-xpath=\"" . $timeseries."/*/".$type."/".$folder."/name[@value='Right " . $signal."']/component[@value='Z']/@data\"/>
								";
								
								$plot_events_x_consistency = implode("\n",$plot_events_x_right);
								$plot_events_y_consistency = implode("\n",$plot_events_y_right);
								$plot_events_z_consistency = implode("\n",$plot_events_z_right);
							}
						}
						else if ($side == 'Right_') {
							$side2             = 'Right ';
							$line_color        = $line_color_right;
							$plot_events_x     = implode("\n",$plot_events_x_right);
							$plot_events_y     = implode("\n",$plot_events_y_right);
							$plot_events_z     = implode("\n",$plot_events_z_right);
						}
						else {
							$plot_events_x             = '';
							$plot_events_y             = '';
							$plot_events_z             = '';
							$plot_events_x_consistency = '';
							$plot_events_y_consistency = '';
							$plot_events_z_consistency = '';
						}
					}
					// GRF
					else {
						if ($side == 'Left_') {
							$side2      = 'Left ';
							$line_color = $line_color_left;
												
							if ($consistency_graphs_as_overlay) {
								$consistency_graphs_x = "
									<series name=\"Right_" . $signal."\" template=\"" . $line_color_right."_line_thin\" y-xpath=\"" . $timeseries."/*/".$type."/".$folder."/name[@value='Right " . $signal."']/component[@value='X']/@data\"/>
								";
								$consistency_graphs_y = "
									<series name=\"Right_" . $signal."\" template=\"" . $line_color_right."_line_thin\" y-xpath=\"" . $timeseries."/*/".$type."/".$folder."/name[@value='Right " . $signal."']/component[@value='Y']/@data\"/>
								";
								$consistency_graphs_z = "
									<series name=\"Right_" . $signal."\" template=\"" . $line_color_right."_line_thin\" y-xpath=\"" . $timeseries."/*/".$type."/".$folder."/name[@value='Right " . $signal."']/component[@value='Z']/@data\"/>
								";
								
								$plot_events_x_consistency = '';
								$plot_events_y_consistency = '';
								$plot_events_z_consistency = '';
							}
						}
						else if ($side == 'Right_') {
							$side2 = 'Right ';
							$line_color = $line_color_right;
						}
							
						$plot_events_x = '';
						$plot_events_y = '';
						$plot_events_z = '';
					}
					
					$i = 1;
					
					foreach ($filenames as $key => $filename) {
						if ($side == '') {
							$series_name_x_arr[] = "<series name=\"Left " . $signal."_X\" side=\"Left\" trial=\"" . $key."\" template=\"" . $line_color_left."_line_thin\" y-xpath=\"timeseries.xml:v3d/owner[@value='" . $filename."']/" . $type."/" . $folder."/name[@value='Left " . $signal."']/component[@value='X']/@data\"/>";
							$series_name_y_arr[] = "<series name=\"Left " . $signal."_Y\" side=\"Left\" trial=\"" . $key."\" template=\"" . $line_color_left."_line_thin\" y-xpath=\"timeseries.xml:v3d/owner[@value='" . $filename."']/" . $type."/" . $folder."/name[@value='Left " . $signal."']/component[@value='Y']/@data\"/>";
							$series_name_z_arr[] = "<series name=\"Left " . $signal."_Z\" side=\"Left\" trial=\"" . $key."\" template=\"" . $line_color_left."_line_thin\" y-xpath=\"timeseries.xml:v3d/owner[@value='" . $filename."']/" . $type."/" . $folder."/name[@value='Left " . $signal."']/component[@value='Z']/@data\"/>";
							$series_name_x_arr[] = "<series name=\"Right " . $signal."_X\" side=\"Right\" trial=\"" . $key."\" template=\"" . $line_color_right."_line_thin\" y-xpath=\"timeseries.xml:v3d/owner[@value='" . $filename."']/" . $type."/" . $folder."/name[@value='Right " . $signal."']/component[@value='X']/@data\"/>";
							$series_name_y_arr[] = "<series name=\"Right " . $signal."_Y\" side=\"Right\" trial=\"" . $key."\" template=\"" . $line_color_right."_line_thin\" y-xpath=\"timeseries.xml:v3d/owner[@value='" . $filename."']/" . $type."/" . $folder."/name[@value='Right " . $signal."']/component[@value='Y']/@data\"/>";
							$series_name_z_arr[] = "<series name=\"Right " . $signal."_Z\" side=\"Right\" trial=\"" . $key."\" template=\"" . $line_color_right."_line_thin\" y-xpath=\"timeseries.xml:v3d/owner[@value='" . $filename."']/" . $type."/" . $folder."/name[@value='Right " . $signal."']/component[@value='Z']/@data\"/>";
						}
						else {
							$series_name_x_arr[] = "<series name=\"" . $side . $signal."_X\" side=\"" . $side2."\" trial=\"" . $key."\" template=\"" . $line_color."_line_thin\" y-xpath=\"timeseries.xml:v3d/owner[@value='" . $filename."']/" . $type."/" . $folder."/name[@value='" . $side2 . $signal."']/component[@value='X']/@data\"/>";
							$series_name_y_arr[] = "<series name=\"" . $side . $signal."_Y\" side=\"" . $side2."\" trial=\"" . $key."\" template=\"" . $line_color."_line_thin\" y-xpath=\"timeseries.xml:v3d/owner[@value='" . $filename."']/" . $type."/" . $folder."/name[@value='" . $side2 . $signal."']/component[@value='Y']/@data\"/>";
							$series_name_z_arr[] = "<series name=\"" . $side . $signal."_Z\" side=\"" . $side2."\" trial=\"" . $key."\" template=\"" . $line_color."_line_thin\" y-xpath=\"timeseries.xml:v3d/owner[@value='" . $filename."']/" . $type."/" . $folder."/name[@value='" . $side2 . $signal."']/component[@value='Z']/@data\"/>";
						}
						
						$i++;
					}
					
					$series_name_x = implode("\n\t\t\t\t\t\t\t",$series_name_x_arr);
					$series_name_y = implode("\n\t\t\t\t\t\t\t",$series_name_y_arr);
					$series_name_z = implode("\n\t\t\t\t\t\t\t",$series_name_z_arr);
					
					$plot="
						<chart tag=\"\$" . $side . $signal_un."_X" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "line_area.crtx\">
							<x-axis max=\"100\" min=\"0\"/>
							<y-axis format=\"" . $y_axis_format."\" majorunit=\"" . $majorunit_x."\" max=\"" . $y_range[$signal_un.'_X_max']."\" min=\"" . $y_range[$signal_un.'_X_min']."\"/>
							<label tag=\"\$upper\" text=\"" . $upper_x."\"/>
							<label tag=\"\$lower\" text=\"" . $lower_x."\"/>
							<label tag=\"\$units\" text=\"$y_axis_units\"/>
							<data>
								<seriesgroup name=\"lines\">
									" . $series_name_x."
									" . $consistency_graphs_x."
								</seriesgroup>
								<seriesgroup name=\"normatives\">
									<series name=\"Right " . $signal." Normal Low\" template=\"normal_low\" y-xpath=\"normative.xml:v3d/owner[@value='GLOBAL']/type[@value='DERIVED']/folder[@value='NORM_LOWER']/name[@value='Right " . $signal."_X_" . $norm_range."']/component[@value='X']/@data\"/>
									<series name=\"Right " . $signal." Normal High\" template=\"normal_high\" y-xpath=\"normative.xml:v3d/owner[@value='GLOBAL']/type[@value='DERIVED']/folder[@value='NORM_RANGE']/name[@value='Right " . $signal."_X_" . $norm_range."']/component[@value='X']/@data\"/>
								</seriesgroup>
								<seriesgroup name=\"events\">
									" . $plot_events_x."
									" . $plot_events_x_consistency."
								</seriesgroup>
							</data>
						</chart>
						<chart tag=\"\$" . $side . $signal_un."_Y" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "line_area.crtx\">
							<x-axis max=\"100\" min=\"0\"/>
							<y-axis format=\"" . $y_axis_format."\" majorunit=\"" . $majorunit_y."\" max=\"" . $y_range[$signal_un.'_Y_max']."\" min=\"" . $y_range[$signal_un.'_Y_min']."\"/>
							<label tag=\"\$upper\" text=\"" . $upper_y."\"/>
							<label tag=\"\$lower\" text=\"" . $lower_y."\"/>
							<label tag=\"\$units\" text=\"$y_axis_units\"/>
							<data>
								<seriesgroup name=\"lines\">
									" . $series_name_y."
									" . $consistency_graphs_y."
								</seriesgroup>
								<seriesgroup name=\"normatives\">
									<series name=\"Right " . $signal." Normal Low\" template=\"normal_low\" y-xpath=\"normative.xml:v3d/owner[@value='GLOBAL']/type[@value='DERIVED']/folder[@value='NORM_LOWER']/name[@value='Right " . $signal."_Y_" . $norm_range."']/component[@value='X']/@data\"/>
									<series name=\"Right " . $signal." Normal High\" template=\"normal_high\" y-xpath=\"normative.xml:v3d/owner[@value='GLOBAL']/type[@value='DERIVED']/folder[@value='NORM_RANGE']/name[@value='Right " . $signal."_Y_" . $norm_range."']/component[@value='X']/@data\"/>
								</seriesgroup>
								<seriesgroup name=\"events\">
									" . $plot_events_y."
									" . $plot_events_y_consistency."
								</seriesgroup>
							</data>
						</chart>";
					$xml .= $plot;
					
					if(strpos($signal,'Elbow') !== false && strpos($this->session->subsessions[0]['Model_used'],'CGM') !== false) {
						//do not plot elbow rotation for CGM model
					}
					else {
						$plot = "
						<chart tag=\"\$" . $side . $signal_un."_Z" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "line_area.crtx\">
							<x-axis max=\"100\" min=\"0\"/>
							<y-axis format=\"" . $y_axis_format."\" majorunit=\"" . $majorunit_z."\" max=\"" . $y_range[$signal_un.'_Z_max']."\" min=\"" . $y_range[$signal_un.'_Z_min']."\"/>
							<label tag=\"\$upper\" text=\"" . $upper_z."\"/>
							<label tag=\"\$lower\" text=\"" . $lower_z."\"/>
							<label tag=\"\$units\" text=\"$y_axis_units\"/>
							<data>
								<seriesgroup name=\"lines\">
									" . $series_name_z."
									" . $consistency_graphs_z."
								</seriesgroup>
								<seriesgroup name=\"normatives\">
									<series name=\"Right " . $signal." Normal Low\" template=\"normal_low\" y-xpath=\"normative.xml:v3d/owner[@value='GLOBAL']/type[@value='DERIVED']/folder[@value='NORM_LOWER']/name[@value='Right " . $signal."_Z_" . $norm_range."']/component[@value='X']/@data\"/>
									<series name=\"Right " . $signal." Normal High\" template=\"normal_high\" y-xpath=\"normative.xml:v3d/owner[@value='GLOBAL']/type[@value='DERIVED']/folder[@value='NORM_RANGE']/name[@value='Right " . $signal."_Z_" . $norm_range."']/component[@value='X']/@data\"/>
								</seriesgroup>
								<seriesgroup name=\"events\">
									" . $plot_events_z."
									" . $plot_events_z_consistency."
								</seriesgroup>
							</data>
						</chart>";
						$xml .= $plot;
					}
				}
			}
		}

		// Metric bars.
		foreach ($pre_post as $condition) {
			if (strpos($analysisName, "Comparison") === false) {
				$condition     = '_post';
				$metrics       = 'metrics.xml:v3d';
				$metrics_short = 'metrics';
			}
			else if (strpos($analysisName, "Comparison") !== false && strpos($condition, "_post") === false) {
				$condition     = '_pre';
				$metrics       = 'metrics_pre.xml:v3d';
				$metrics_short = 'metrics_pre';
			}
			else if (strpos($analysisName, 'Comparison') !== false && strpos($condition, '_post') !== false) {
				$metrics       = 'metrics.xml:v3d';
				$metrics_short = 'metrics';
			}

			// Add text nodes for TSP, MAP and other metrics >>>>>>>>>>>>!!!!!!! check for comparison src metric_pre.xml????????
			$xml .= "
				<text tag=\"bodyHeight" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='SUBJECT']/name[@value='Height']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
				<text tag=\"bodyWeight" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='SUBJECT']/name[@value='Weight']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
				<text tag=\"SpeedNormPctDiff" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='TMPD']/name[@value='SpeedNormPctDiff']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
				<text tag=\"Speed" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='TMPD']/name[@value='Speed']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
				<text tag=\"Speed_STDDEV" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='TMPD']/name[@value='Speed_STDDEV']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
				<text tag=\"CadenceNormPctDiff" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='TMPD']/name[@value='CadenceNormPctDiff']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
				<text tag=\"Stride_LengthNormPctDiff" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='TMPD']/name[@value='Stride_LengthNormPctDiff']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
				<text tag=\"Left_Step_LengthNormPctDiff" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='TMPD']/name[@value='Left_Step_LengthNormPctDiff']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
				<text tag=\"Right_Step_LengthNormPctDiff" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='TMPD']/name[@value='Right_Step_LengthNormPctDiff']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
			";
				
			$metrics_names_array = ['StrideLen', 'StrideWid', 'LStepLen', 'RStepLen', 'LStepTime', 'RStepTime', 'LStanPct', 'RStanPct', 'LDblSupp', 'RDblSupp', 'Cadence'];
			foreach ($metrics_names_array as $name) {
				$plot ="
					<text tag=\"" . $name."Mean" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='SHORT']/name[@value='" . $name."Mean']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
					<text tag=\"" . $name."SD" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='SHORT']/name[@value='" . $name."SD']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
					<text tag=\"" . $name."Count" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='SHORT']/name[@value='" . $name."Count']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
				";
				$xml .= $plot;
			}

			$metrics_names_array = ['Right_GPS', 'Left_GPS', 'Overall_GPS'];
			foreach ($metrics_names_array as $name) {
				$plot ="
					<text tag=\"" . $name."_MEDIAN" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='MAP']/name[@value='" . $name."_MEDIAN']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
					<text tag=\"" . $name."_IQR" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='MAP']/name[@value='" . $name."_IQR']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
				";
				$xml .= $plot;
			}
			
			$metrics_names_array = ['Left Pelvic Angles_X', 'Left Pelvic Angles_Y', 'Left Pelvic Angles_Z', 'Left Hip Angles_X', 'Right Hip Angles_X', 'Left Hip Angles_Y', 'Right Hip Angles_Y', 'Left Hip Angles_Z', 'Right Hip Angles_Z', 'Left Knee Angles_X', 'Right Knee Angles_X', 'Left Ankle Angles_X', 'Right Ankle Angles_X', 'Left Foot Progression_Z', 'Right Foot Progression_Z'];
			foreach ($metrics_names_array as $name) {
				$plot ="
					<text tag=\"" . $name."_gvs_MEDIAN" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='MAP']/name[@value='" . $name."_gvs_MEDIAN']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
					<text tag=\"" . $name."_IQR" . $condition."\" xpath=\"" . $metrics."/owner[@value='GLOBAL']/type[@value='METRIC']/folder[@value='MAP']/name[@value='" . $name."_IQR']/component[@value='X']/@data\" src=\"" . $metrics_short.".xml\"/>
				";
				$xml .= $plot;
			}

			// Metrics graphs.
			$plot_metrix="
				<chart tag=\"\$StrideLength" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_1.crtx\" charttype=\"barChart\">
					<data>
						<series name=\"mean12\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='StrideLenMean']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='StrideLenSD']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$StrideWidth" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_1.crtx\" charttype=\"barChart\">
					<data>
						<series name=\"mean13\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='StrideWidMean']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='StrideWidSD']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$LStepLength" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_2.crtx\" charttype=\"barChart\">
					<x-axis max=\"1\" min=\"0\"/>
					<data>
						<series name=\"mean14\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='LStepLenMean']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='LStepLenSD']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$RStepLength" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_3.crtx\" charttype=\"barChart\">
					<x-axis max=\"1\" min=\"0\"/>
					<data>
						<series name=\"mean15\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='RStepLenMean']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='RStepLenSD']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>

				<chart tag=\"\$LStepTime" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_2.crtx\" charttype=\"barChart\">
					<x-axis max=\"1\" min=\"0\"/>
					<data>
						<series name=\"mean16\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='LStepTimeMean']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='LStepTimeSD']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$RStepTime" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_3.crtx\" charttype=\"barChart\">
					<x-axis max=\"1\" min=\"0\"/>
					<data>
						<series name=\"mean17\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='RStepTimeMean']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='RStepTimeSD']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>

				<chart tag=\"\$LStanceTime" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_2.crtx\" charttype=\"barChart\">
					<x-axis max=\"1\" min=\"0\"/>
					<data>
						<series name=\"mean18\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='LStanTimeMean']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='LStanTimeSD']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$RStanceTime" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_3.crtx\" charttype=\"barChart\">
					<x-axis max=\"1\" min=\"0\"/>
					<data>
						<series name=\"mean19\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='RStanTimeMean']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='RStanTimeSD']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				
				<chart tag=\"\$LDoubleSupport" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_2.crtx\" charttype=\"barChart\">
					<x-axis max=\"0.2\" min=\"0\"/>
					<data>
						<series name=\"mean20\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='LDblSuppMean']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='LDblSuppSD']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$RDoubleSupport" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_3.crtx\" charttype=\"barChart\">
					<x-axis max=\"0.2\" min=\"0\"/>
					<data>
						<series name=\"mean21\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='RDblSuppMean']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='RDblSuppSD']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				
				<chart tag=\"\$Cadence" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_1.crtx\" charttype=\"barChart\">
					<data>
						<series name=\"mean22\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='CadenceMean']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='SHORT']/name[@value='CadenceSD']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$Speed" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_1.crtx\" charttype=\"barChart\">
					<data>
						<series name=\"mean22a\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='TMPD']/name[@value='Speed']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='TMPD']/name[@value='Speed_STDDEV']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				
				<chart tag=\"\$GPS_L" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_6.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean23\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left_GPS_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left_GPS_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GPS_R" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_5.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean24\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Right_GPS_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Right_GPS_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>

				<chart tag=\"\$Overall_GPS" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_4.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean25\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Overall_GPS_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Overall_GPS_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_Pelvis_X" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_4.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean26\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Pelvic Angles_X_gvs_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Pelvic Angles_X_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_Pelvis_Y" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_4.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean27\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Pelvic Angles_Y_gvs_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Pelvic Angles_Y_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_Pelvis_Z" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_4.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean28\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Pelvic Angles_Z_gvs_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Pelvic Angles_Z_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_LHip_X" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_6.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean29\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Hip Angles_X_gvs_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Hip Angles_X_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_RHip_X" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_5.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean30\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Right Hip Angles_X_gvs_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Right Hip Angles_X_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>

				<chart tag=\"\$GVS_LHip_Y" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_6.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean31\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Hip Angles_Y_gvs_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Hip Angles_Y_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_RHip_Y" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_5.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean32\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Right Hip Angles_Y_gvs_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Right Hip Angles_Y_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				
				<chart tag=\"\$GVS_LHip_Z" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_6.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean33\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Hip Angles_Z_gvs_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Hip Angles_Z_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_RHip_Z" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_5.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean34\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Right Hip Angles_Z_gvs_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Right Hip Angles_Z_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>

				<chart tag=\"\$GVS_LKnee_X" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_6.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean35\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Knee Angles_X_gvs_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Knee Angles_X_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_RKnee_X" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_5.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean36\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Right Knee Angles_X_gvs_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Right Knee Angles_X_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>

				<chart tag=\"\$GVS_LAnkle_X" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_6.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean37\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Ankle Angles_X_gvs_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Ankle Angles_X_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_RAnkle_X" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_5.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean38\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Right Ankle Angles_X_gvs_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Right Ankle Angles_X_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>

				<chart tag=\"\$GVS_LFoot_Prog_Z" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_6.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean39\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Foot Progression_Z_gvs_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Left Foot Progression_Z_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_RFoot_Prog_Z" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_5.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean40\" template=\"mean\" x-values=\"1\" y-xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Right Foot Progression_Z_gvs_MEDIAN']/component[@value='X']/@data\">
							<errorbar xpath=\"" . $metrics."/*/*/folder[@value='MAP']/name[@value='Right Foot Progression_Z_IQR']/component[@value='X']/@data\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$Overall_GPS_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['Overall_GPS_median']."\">
							<errorbar values=\"" . $map_norms['Overall_GPS_IQR']."," . $map_norms['Overall_GPS_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GPS_L_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['Left_GPS_median']."\">
							<errorbar values=\"" . $map_norms['Left_GPS_IQR']."," . $map_norms['Left_GPS_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GPS_R_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['Right_GPS_median']."\">
							<errorbar values=\"" . $map_norms['Right_GPS_IQR']."," . $map_norms['Right_GPS_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_Pelvis_X_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['GVS_Pelvis_X_median']."\">
							<errorbar values=\"" . $map_norms['GVS_Pelvis_X_IQR']."," . $map_norms['GVS_Pelvis_X_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_LHip_X_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['GVS_LHip_X_median']."\">
							<errorbar values=\"" . $map_norms['GVS_LHip_X_IQR']."," . $map_norms['GVS_LHip_X_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_RHip_X_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['GVS_RHip_X_median']."\">
							<errorbar values=\"" . $map_norms['GVS_RHip_X_IQR']."," . $map_norms['GVS_RHip_X_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_LKnee_X_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['GVS_LKnee_X_median']."\">
							<errorbar values=\"" . $map_norms['GVS_LKnee_X_IQR']."," . $map_norms['GVS_LKnee_X_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_RKnee_X_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['GVS_RKnee_X_median']."\">
							<errorbar values=\"" . $map_norms['GVS_RKnee_X_IQR']."," . $map_norms['GVS_RKnee_X_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_LAnkle_X_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['GVS_LAnkle_X_median']."\">
							<errorbar values=\"" . $map_norms['GVS_LAnkle_X_IQR']."," . $map_norms['GVS_LAnkle_X_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_RAnkle_X_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['GVS_RAnkle_X_median']."\">
							<errorbar values=\"" . $map_norms['GVS_RAnkle_X_IQR']."," . $map_norms['GVS_RAnkle_X_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_Pelvis_Y_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['GVS_Pelvis_Y_median']."\">
							<errorbar values=\"" . $map_norms['GVS_Pelvis_Y_IQR']."," . $map_norms['GVS_Pelvis_Y_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_LHip_Y_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['GVS_LHip_Y_median']."\">
							<errorbar values=\"" . $map_norms['GVS_LHip_Y_IQR']."," . $map_norms['GVS_LHip_Y_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_RHip_Y_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['GVS_RHip_Y_median']."\">
							<errorbar values=\"" . $map_norms['GVS_RHip_Y_IQR']."," . $map_norms['GVS_RHip_Y_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_Pelvis_Z_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['GVS_Pelvis_Z_median']."\">
							<errorbar values=\"" . $map_norms['GVS_Pelvis_Z_IQR']."," . $map_norms['GVS_Pelvis_Z_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_LHip_Z_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['GVS_LHip_Z_median']."\">
							<errorbar values=\"" . $map_norms['GVS_LHip_Z_IQR']."," . $map_norms['GVS_LHip_Z_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_RHip_Z_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['GVS_RHip_Z_median']."\">
							<errorbar values=\"" . $map_norms['GVS_RHip_Z_IQR']."," . $map_norms['GVS_RHip_Z_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_LFoot_Prog_Z_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['GVS_LFoot_Prog_Z_median']."\">
							<errorbar values=\"" . $map_norms['GVS_LFoot_Prog_Z_IQR']."," . $map_norms['GVS_LFoot_Prog_Z_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
				<chart tag=\"\$GVS_RFoot_Prog_Z_norm" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "metric_bars_7.crtx\" charttype=\"barChart\">
					<y-axis max=\"20\" min=\"0\"/>
					<data>
						<series name=\"mean41\" template=\"mean\" x-values=\"1\" y-values=\"" . $map_norms['GVS_RFoot_Prog_Z_median']."\">
							<errorbar values=\"" . $map_norms['GVS_RFoot_Prog_Z_IQR']."," . $map_norms['GVS_RFoot_Prog_Z_IQR']."\" type=\"custom\" direction=\"both\" />
						</series>
					</data>
				</chart>
			";
			$xml .= $plot_metrix;
		}

		//---------------
		//EMG
		//----------------

		$last_nodes ="
			</items>
		</objects>";

		if (strpos($analysisName, 'Comparison') === false && $includes_noraxon !== true && $includes_mega_me6000 !== true
				&& $includes_delsys_trigno !== true && $includes_myon !== true && $includes_analog_EMG !== true)
			$xml .= $last_nodes;

		if (strpos($analysisName, 'Comparison') !== false && $EMG_pre_exists == 'no' && $EMG_post_exists == 'no')
			$xml .= $last_nodes;

		// Create array with EMG names.
		if ($includes_noraxon || $includes_mega_me6000 || $includes_delsys_trigno || $includes_myon || $includes_analog_EMG || $EMG_pre_exists == 'yes' || $EMG_post_exists == 'yes') {
			// Single session.
			if (strpos($analysisName, 'Comparison') === false && ($includes_noraxon || $includes_mega_me6000 || $includes_delsys_trigno || $includes_myon || $includes_analog_EMG)) {
				$EMG_names_array = [];
				
				foreach($measurements as $m)  {
					if (strcmp('True', $m['Used']) == 0 && isset($m['Channels']) && ($m['Measurement_type'] === 'Dynamic')) {
						$path_parts = pathinfo($m['Filename']);
						$filenames_array[] = $path_parts['filename'] . '.c3d'; //used to filter out static trial from EMG plots
						
						// For now we overwrite the list until we reached the last file and assume the same EMG channels were used in all files.
						
						foreach($m['Channels'] as $ch)  {
							if (
									(
										(
											strcmp('Noraxon', $ch['Board']) == 0 
											|| strcmp('MEGA/ME6000', $ch['Board']) == 0 
											|| (strcmp('Delsys Trigno', $ch['Board']) == 0 && strpos($ch['Name'], '_ACC_') === false) 
											|| (strcmp('Cometa', $ch["Board"]) == 0 && strpos($ch["Name"], "_ACC_") === false && strpos($ch["Name"], "_GYRO_") === false && strpos($ch["Name"], "_MAG_") === false && strpos($ch["Name"], "_Q_") === false)
										)
										&& (strpos($ch['Name'], 'Sync') === false)
									)
									||
									(
										(
											strcmp('USB-2533', $ch['Board']) == 0 
											|| strcmp('PCI-DAS6402/16', $ch["Board"]) == 0
										)
										&& 
										(
											strpos($ch['Name'], 'EMG_') !== false 
											|| (
													substr($ch['Name'], 0, 1) == 'R' 
													|| substr($ch['Name'], 0, 1) == 'L'
												)
										)
									)
								) {
									if (!in_array(trim($ch["Name"]), $EMG_names_array))
										$EMG_names_array[] = trim($ch["Name"]);
								}
						}
					}
				}
				// Find max value for each EMG signal at metrics_EMG.xml.
				$EMG_max_file = $this->workingDirectory . 'metrics_EMG.xml';
			}
			// Two sessions
			else {
				// Load signal names from file to array.
				if 	(file_exists($working_directory_array[1] .'\EMG_signals.json') && file_exists($working_directory .'EMG_signals.json')) {
					$EMG_names_array_pre = json_decode(file_get_contents($working_directory_array[1] .'\EMG_signals.json'), true);
					$EMG_names_array_post = json_decode(file_get_contents($working_directory .'EMG_signals.json'), true);
				}
				else if (file_exists($working_directory .'EMG_signals.json') && file_exists($working_directory_array[1] .'\EMG_signals.json') === false) {
					$EMG_names_array_post = json_decode(file_get_contents($working_directory .'EMG_signals.json'), true);
				}
				else if (file_exists($working_directory_array[1] .'\EMG_signals.json') && file_exists($working_directory .'EMG_signals.json') === false) {
					$EMG_names_array_pre = json_decode(file_get_contents($working_directory_array[1] .'\EMG_signals.json'), true);
				}
				else {
					$xml .= $last_nodes;
				}
			}
			
			// print_r($EMG_names_array_pre);	
			// print_r($EMG_names_array_post);
			// print_r($filenames_array_pre);	
			// print_r($filenames_array_post);	
			
			// Create separate graphs for PRE+POST.
			foreach ($pre_post as $condition) {
				$i = 0; $l = 1; $r = 1; $ll = $l+8; $rr = $r+8;

				if ($condition == '' && strpos($analysisName, 'Comparison') !== false && $EMG_pre_exists == 'yes') {
					$EMG_names_array = $EMG_names_array_pre;
					$filenames_array = $filenames_array_pre;
					$EMG_max_file    = $this->workingDirectory. 'metrics_EMG_compare.xml';
					$timeseries      = 'timeseries_pre.xml:v3d';
					$lto_l           = $lto_l_pre;
					$rto_r           = $rto_r_pre;
					$lhs             = $lhs_pre;
					$rhs             = $rhs_pre;
					$lto_r           = $lto_r_pre;
					$rto_l           = $rto_l_pre;
					$lto_l_emg_raw   = $lto_l_emg_raw_pre;
					$rto_r_emg_raw   = $rto_r_emg_raw_pre;
					$lhs_emg_raw     = $lhs_emg_raw_pre;
					$rhs_emg_raw     = $rhs_emg_raw_pre;
					$lto_r_emg_raw   = $lto_r_emg_raw_pre;
					$rto_l_emg_raw   = $rto_l_emg_raw_pre;
				}
				else if ($condition == '_post' && strpos($analysisName, 'Comparison') !== false && $EMG_post_exists == 'yes') {
					$EMG_names_array = $EMG_names_array_post;
					$filenames_array = $filenames_array_post;
					$EMG_max_file    = $this->workingDirectory. 'metrics_EMG_compare.xml';
					$timeseries      = 'timeseries.xml:v3d';
				}
				else {
					$timeseries = 'timeseries.xml:v3d';
				}

				//print_r($EMG_names_array);	
				//print_r($filenames_array);	
			
				if (isset($EMG_names_array)) {
					foreach ($EMG_names_array as $EMG_name)	{
						$signal_left_array = [];
						$signal_right_array = [];
						$raw_signals_left_array = []; 
						$raw_signals_right_array = [];
						$emg_events_left_arr = [];
						$emg_events_left_raw_arr = [];
						$emg_events_right_arr = [];
						$emg_events_right_raw_arr = [];
					
						//print_r($EMG_name);	
						
						//add underscore to EMG signal names
						$EMG_names_un = str_replace(" ", "_", $EMG_name);
				
						// Add _signal_MAX to signal name.
						$EMG_signal_name = $EMG_name . '_signal_MAX';
						// Use function to find max value for signal.
						$y_max = $this->getEmgMax($EMG_max_file, $EMG_signal_name);
						$y_max = round($y_max / 2) * 2;

						$EMG_signal_name_raw = $EMG_name . '_signal_raw_MAX';
						$y_max_raw = $this->getEmgMax($EMG_max_file, $EMG_signal_name_raw); 
						$y_max_raw = round($y_max_raw/2)*2;

						// Find out which session has higher max value.
						if (strpos($analysisName, 'Comparison') !== false) {
							$EMG_max_file_pre = $this->workingDirectory . '\metrics_EMG_pre.xml';
							$y_max_pre = $this->getEmgMax($EMG_max_file_pre, $EMG_signal_name); 
							$y_max_pre = round($y_max_pre / 2) * 2;
							$y_max_raw_pre = $this->getEmgMax($EMG_max_file_pre, $EMG_signal_name_raw); 
							$y_max_raw_pre = round($y_max_raw_pre / 2) * 2;
							
							if ($y_max > $y_max_pre)
								$y_max = $y_max;
							else
								$y_max = $y_max_pre;
							
							if ($y_max_raw > $y_max_raw_pre)
								$y_max_raw = $y_max_raw;
							else
								$y_max_raw = $y_max_raw_pre;
						}

						// Size of tick is 15% of y-axis range.
						$tick_EMG_min = $y_max * 0.85;
						// For raw graphs size of tick is 30% of y-axis range.
						$tick_EMG_min_raw = $y_max_raw * 0.7;
						
						// Define whether each trial or mean of all trials will be plotted.
						if ($plot_type_EMG == 'each_trial') {
							$k = 1;
							
							foreach ($filenames_array as $key => $file) {
								$signal_left_array[$file] = "<series name=\"" . $EMG_names_un."_" . $k."\" trial=\"" . $key."\" side=\"Left\" template=\"" . $line_color_EMG_left."_line\" y-xpath=\"" . $timeseries."/owner[@value='" . $file."']/type[@value='ANALOG']/folder[@value='EMG_PROCESSED']/name[@value='$EMG_name']/component[@value='X']/@data\"/>";
								$signal_right_array[$file] = "<series name=\"" . $EMG_names_un."_" . $k."\" trial=\"" . $key."\" side=\"Right\" template=\"" . $line_color_EMG_right."_line\" y-xpath=\"" . $timeseries."/owner[@value='" . $file."']/type[@value='ANALOG']/folder[@value='EMG_PROCESSED']/name[@value='$EMG_name']/component[@value='X']/@data\"/>";
								$raw_signals_left_array[$file] = "<series name=\"" . $EMG_names_un."_" . $k."\" trial=\"" . $key."\" side=\"Left\" template=\"" . $line_color_EMG_raw_left."_line\" y-xpath=\"" . $timeseries."/owner[@value='" . $file."']/type[@value='ANALOG']/folder[@value='EMG_RAW']/name[@value='$EMG_name']/component[@value='X']/@data\"/>";
								$raw_signals_right_array[$file] = "<series name=\"" . $EMG_names_un."_" . $k."\" trial=\"" . $key."\" side=\"Right\" template=\"" . $line_color_EMG_raw_right."_line\" y-xpath=\"" . $timeseries."/owner[@value='" . $file."']/type[@value='ANALOG']/folder[@value='EMG_RAW']/name[@value='$EMG_name']/component[@value='X']/@data\"/>";
							
								$k++;
							}
							
							$signal_left = implode("\n\t\t\t\t\t\t\t\t",$signal_left_array);
							$signal_right = implode("\n\t\t\t\t\t\t\t\t",$signal_right_array);
							$raw_signals_left = implode("\n\t\t\t\t\t\t\t\t",$raw_signals_left_array);
							$raw_signals_right = implode("\n\t\t\t\t\t\t\t\t",$raw_signals_right_array);
						
						}
						else {
							$timeseries = "timeseries_means.xml:v3d";
							$suffix2 = $condition;
							$signal_left = "<series name=\"$EMG_names_un\" template=\"" . $line_color_EMG_left."_line\" y-xpath=\"" . $timeseries."/owner[@value='GLOBAL']/type[@value='P2D']/folder[@value='TIMESERIES']/name[@value='$EMG_name"."_MEAN" . $suffix2."']/component[@value='Y']/@data\"/>";
							$signal_right = "<series name=\"$EMG_names_un\" template=\"" . $line_color_EMG_right."_line\" y-xpath=\"" . $timeseries."/owner[@value='GLOBAL']/type[@value='P2D']/folder[@value='TIMESERIES']/name[@value='$EMG_name"."_MEAN" . $suffix2."']/component[@value='Y']/@data\"/>";
						}
						
						//if $raw_EMG_as_overlay is true add all raw trials to filtered EMG graphs
						if ($raw_EMG_as_overlay) {
							$add_raw_signals_left = $raw_signals_left;
							$add_raw_signals_right = $raw_signals_right;
							$add_raw_suf = "_raw";
							$y_max = $y_max_raw;
							$y_min = $y_max_raw * -1;
						}
						else {
							$add_raw_signals_left = "";
							$add_raw_signals_right = "";
							$add_raw_suf = "";
							$y_min = 0;
						}

						//events
						foreach ($filenames_array as $key => $file) {
							//Word
							$emg_events_left_arr[] ="
								<series name=\"$EMG_names_un\" trial=\"" . $key."\" side=\"Right\" cycle=\"Left\" template=\"" . $line_color_EMG_right."_line\" x-values=\"" . $lhs[$key]."," . $lhs[$key]."\" y-values=\"" . $tick_EMG_min."," . $y_max."\"/>
								<series name=\"$EMG_names_un\" template=\"" . $line_color_EMG_left."_line\" x-values=\"" . $lto_l[$key]."," . $lto_l[$key]."\" y-values=\"-5000,5000\"/>
								<series name=\"$EMG_names_un\" template=\"" . $line_color_EMG_right."_line\" x-values=\"" . $rto_l[$key]."," . $rto_l[$key]."\" y-values=\"" . $tick_EMG_min."," . $y_max."\"/>
							";
							
							$emg_events_left_raw_arr[] ="
								<series name=\"$EMG_names_un\" template=\"" . $line_color_EMG_right."_line\" x-values=\"" . $lhs_emg_raw[$key]."," . $lhs_emg_raw[$key]."\" y-values=\"" . $tick_EMG_min_raw."," . $y_max_raw."\"/>
								<series name=\"$EMG_names_un\" template=\"" . $line_color_EMG_left."_line\" x-values=\"" . $lto_l_emg_raw[$key]."," . $lto_l_emg_raw[$key]."\" y-values=\"-5000,5000\"/>
								<series name=\"$EMG_names_un\" template=\"" . $line_color_EMG_right."_line\" x-values=\"" . $rto_l_emg_raw[$key]."," . $rto_l_emg_raw[$key]."\" y-values=\"" . $tick_EMG_min_raw."," . $y_max_raw."\"/>
							";
							
							$emg_events_right_arr[] ="
								<series name=\"$EMG_names_un\" template=\"" . $line_color_EMG_left."_line\" x-values=\"" . $rhs[$key]."," . $rhs[$key]."\" y-values=\"" . $tick_EMG_min."," . $y_max."\"/>
								<series name=\"$EMG_names_un\" template=\"" . $line_color_EMG_right."_line\" x-values=\"" . $rto_r[$key]."," . $rto_r[$key]."\" y-values=\"-5000,5000\"/>
								<series name=\"$EMG_names_un\" template=\"" . $line_color_EMG_left."_line\" x-values=\"" . $lto_r[$key]."," . $lto_r[$key]."\" y-values=\"" . $tick_EMG_min."," . $y_max."\"/>
							";
							
							$emg_events_right_raw_arr[] ="
								<series name=\"$EMG_names_un\" template=\"" . $line_color_EMG_left."_line\" x-values=\"" . $rhs_emg_raw[$key]."," . $rhs_emg_raw[$key]."\" y-values=\"" . $tick_EMG_min_raw."," . $y_max_raw."\"/>
								<series name=\"$EMG_names_un\" template=\"" . $line_color_EMG_right."_line\" x-values=\"" . $rto_r_emg_raw[$key]."," . $rto_r_emg_raw[$key]."\" y-values=\"-5000,5000\"/>
								<series name=\"$EMG_names_un\" template=\"" . $line_color_EMG_left."_line\" x-values=\"" . $lto_r_emg_raw[$key]."," . $lto_r_emg_raw[$key]."\" y-values=\"" . $tick_EMG_min_raw."," . $y_max_raw."\"/>
							";
						}
						
						$emg_events_left = implode("\n",$emg_events_left_arr);
						$emg_events_left_raw = implode("\n",$emg_events_left_raw_arr);
						$emg_events_right = implode("\n",$emg_events_right_arr);
						$emg_events_right_raw = implode("\n",$emg_events_right_raw_arr);

						$majorunit_emg = $y_max/2;
						$majorunit_emg_raw = $y_max_raw;

						$j = $i + 1;
						$EMG_graph_title = str_ireplace('EMG_','',$EMG_names_array); // remove 'EMG' from graph title
						$EMG_graph_title = str_ireplace('EMG ','',$EMG_graph_title);

						if ((strpos($analysisName, "Comparison") === false || $EMG_pre_exists == 'yes' || $EMG_post_exists == 'yes') && (substr($EMG_name,0,1) === "L" || stripos($EMG_name,'EMG_L') !== false || stripos($EMG_name,'EMG L') !== false)) {
							$side = "L";
							$emg[$j]="
							<chart tag=\"\$" . $side."_EMG_" . $l."" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "emg.crtx\" charttype=\"scatterChartWithArea\">
								<x-axis max=\"100\" min=\"0\"/>
								<y-axis max=\"$y_max\" min=\"$y_min\" majorunit=\"" . $majorunit_emg."\"/>
								<label tag=\"\$name\" text=\"$EMG_graph_title[$i]\"/>
								<label tag=\"\$units\" text=\"$EMG_units\"/>
								<data>
									<seriesgroup name=\"lines\">
										" . $signal_left."
										" . $add_raw_signals_left."
									</seriesgroup>
									<seriesgroup name=\"normatives\">
										<series name=\"$EMG_names_un\" template=\"normal_low\" y-xpath=\"normative.xml:v3d/*/*/folder[@value='NORM_LOWER']/name[@value='$EMG_name" . $add_raw_suf."']/component[@value='X']/@data\"/>
										<series name=\"$EMG_names_un\" template=\"normal_high\" y-xpath=\"normative.xml:v3d/*/*/folder[@value='NORM_RANGE']/name[@value='$EMG_name" . $add_raw_suf."']/component[@value='X']/@data\"/>
									</seriesgroup>
									<seriesgroup name=\"events\">
										" . $emg_events_left."
									</seriesgroup>
								</data>
							</chart>
							<chart tag=\"\$" . $side."_EMG_" . $ll."" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "emg.crtx\" charttype=\"scatterChartWithArea\">
								<x-axis max=\"1000\" min=\"0\"/>
								<y-axis max=\"$y_max_raw\" min=\"-$y_max_raw\" majorunit=\"" . $majorunit_emg_raw."\"/>
								<label tag=\"\$name\" text=\"$EMG_graph_title[$i]\"/>
								<label tag=\"\$units\" text=\"$EMG_units\"/>
								<data>
									<seriesgroup name=\"lines\">
										" . $raw_signals_left."
									</seriesgroup>
									<seriesgroup name=\"normatives\">
										<series name=\"$EMG_names_un\" template=\"normal_low\" y-xpath=\"normative.xml:v3d/*/*/folder[@value='NORM_LOWER']/name[@value='" . $EMG_name."_raw']/component[@value='X']/@data\"/>
										<series name=\"$EMG_names_un\" template=\"normal_high\" y-xpath=\"normative.xml:v3d/*/*/folder[@value='NORM_RANGE']/name[@value='" . $EMG_name."_raw']/component[@value='X']/@data\"/>
									</seriesgroup>
									<seriesgroup name=\"events\">
										" . $emg_events_left_raw."
									</seriesgroup>
								</data>
							</chart>";
							$l++; $ll++;
						}
						else {
							$side = "R";
							$emg[$j]="
							<chart tag=\"\$" . $side."_EMG_" . $r."" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "emg.crtx\" charttype=\"scatterChartWithArea\">
								<x-axis max=\"100\" min=\"0\"/>
								<y-axis max=\"$y_max\" min=\"$y_min\" majorunit=\"" . $majorunit_emg."\"/>
								<label tag=\"\$name\" text=\"$EMG_graph_title[$i]\"/>
								<label tag=\"\$units\" text=\"$EMG_units\"/>
								<data>
									<seriesgroup name=\"lines\">
										" . $signal_right."
										" . $add_raw_signals_right."
									</seriesgroup>
									<seriesgroup name=\"normatives\">
										<series name=\"$EMG_names_un\" template=\"normal_low\" y-xpath=\"normative.xml:v3d/*/*/folder[@value='NORM_LOWER']/name[@value='$EMG_name" . $add_raw_suf."']/component[@value='X']/@data\"/>
										<series name=\"$EMG_names_un\" template=\"normal_high\" y-xpath=\"normative.xml:v3d/*/*/folder[@value='NORM_RANGE']/name[@value='$EMG_name" . $add_raw_suf."']/component[@value='X']/@data\"/>
									</seriesgroup>
									<seriesgroup name=\"events\">
										" . $emg_events_right."
									</seriesgroup>
								</data>
							</chart>
							<chart tag=\"\$" . $side."_EMG_" . $rr."" . $condition."\" wordtemplate=\"" . $wordTemplateDirectory . "emg.crtx\" charttype=\"scatterChartWithArea\">
								<x-axis max=\"1000\" min=\"0\"/>
								<y-axis max=\"$y_max_raw\" min=\"-$y_max_raw\" majorunit=\"" . $majorunit_emg_raw."\"/>
								<label tag=\"\$name\" text=\"$EMG_graph_title[$i]\"/>
								<label tag=\"\$units\" text=\"$EMG_units\"/>
								<data>
									<seriesgroup name=\"lines\">
										" . $raw_signals_right."
									</seriesgroup>
									<seriesgroup name=\"normatives\">
										<series name=\"$EMG_names_un\" template=\"normal_low\" y-xpath=\"normative.xml:v3d/*/*/folder[@value='NORM_LOWER']/name[@value='" . $EMG_name."_raw']/component[@value='X']/@data\"/>
										<series name=\"$EMG_names_un\" template=\"normal_high\" y-xpath=\"normative.xml:v3d/*/*/folder[@value='NORM_RANGE']/name[@value='" . $EMG_name."_raw']/component[@value='X']/@data\"/>
									</seriesgroup>
									<seriesgroup name=\"events\">
										" . $emg_events_right_raw."
									</seriesgroup>
								</data>
							</chart>";
							$r++; $rr++;
						}
						$i++;

						$xml .= $emg[$i];
					}
				}
			}
			
			//add last two lines back
			$xml .= $last_nodes;
		}
		
		echo $xml;
	}

	// Find min/max values for auto range
	// function  will find min and max at min_max.xml and store them in array
	private function getGraphMinMax($xml, $graph_min_max_file, $signal_name) {
		$commas = '"';
		$query = '//name[@value =' . $commas . 'Left ' . $signal_name . $commas . ']/component';
		$results = $xml->xpath($query);
		
		// If data is missing (e.g. no force), it is possible that no min/max value is available.
		if (empty($results)) {
			$sig_min = -1;
			$sig_max = 1;
		}
		else {
			// Find left max.
			$results = $results[0]['data']->asXML();

			$sig_left_max= str_replace('data=', '', $results);
			$sig_left_max= str_replace('"', '', $sig_left_max);

			$query = '//name[@value =' . $commas . 'Left ' . $signal_name . $commas . ']/component';
			$results = $xml->xpath($query);

			// Find left min.
			$results = $results[0]['data']->asXML();
			$sig_left_min= str_replace('data=', '', $results);
			$sig_left_min= str_replace('"', '', $sig_left_min);

			$query = '//name[@value =' . $commas."Right " . $signal_name . $commas. ']/component';
			$results = $xml->xpath($query);

			// Find right max.
			$results = $results[0]['data']->asXML();
			$sig_right_max= str_replace('data=', '', $results);
			$sig_right_max= str_replace('"', '', $sig_right_max);

			$query = '//name[@value =' . $commas . "Right " . $signal_name . $commas . ']/component';
			$results = $xml->xpath($query);

			// Find right min.
			$results = $results[0]['data']->asXML();
			$sig_right_min= str_replace('data=', '',$results);
			$sig_right_min= str_replace('"', '', $sig_right_min);
				
			// Find higher value.
			if ($sig_right_max > $sig_left_max) {
				$sig_max = $sig_right_max;	
			}
			else {
				$sig_max = $sig_left_max;
			}
			
			// Find lower value.
			if ($sig_right_min < $sig_left_min) {
				$sig_min = $sig_right_min;	
			}
			else {
				$sig_min = $sig_left_min;
			}

			$sig_min = round($sig_min, 2);
			$sig_max = round($sig_max, 2);
		}

		return [$sig_min, $sig_max];
	}

	private function getEmgMax($EMG_max_file,$EMG_signal_name) {
		$xml = simplexml_load_file($EMG_max_file);

		$commas = '"';
		$query = '//name[@value =' . $commas . $EMG_signal_name . $commas . ']/component';
		$results = $xml->xpath($query);

		// If data is missing it is possible that no min/max value is available.
		if (empty($results)) {
			$EMG_sig_max = 1;
		}
		else {
			$results = $results[0]['data']->asXML();
			$EMG_sig_max = str_replace('data=', '', $results);
			$EMG_sig_max = str_replace('"', '',$EMG_sig_max);
		}

		return $EMG_sig_max;
	}

	private function removeValueFromArray($value, $array)  {
		if(($key = array_search($value, $array)) !== false) {
			unset($array[$key]);
		}
		return $array;
	}
}

?>
