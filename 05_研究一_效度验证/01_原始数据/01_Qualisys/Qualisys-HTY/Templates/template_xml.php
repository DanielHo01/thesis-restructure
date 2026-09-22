<?php
$xml = simplexml_load_file($xml_file);
//Subject fields
$subject = array();
$subject["Type"] = (string)$xml["Type"];
foreach( $xml->Fields->children() as $field )
{
    $subject[$field->getName()] = (string)$field;
}

//Session fields
$session = array();
$session["Type"] = (string)$xml->Session["Type"];
foreach( $xml->Session->Fields->children() as $field )
{
    $session[$field->getName()] = (string)$field;
}

//Sub-Session fields

$subsession = array();
$subsession["Type"] = (string)$xml->Session->Subsession["Type"];
foreach( $xml->Session->Subsession->Fields->children() as $field )
{
    $subsession[$field->getName()] = (string)$field;
}

//Measurements fields
$measurements = array();
$includes_noraxon = FALSE;
$includes_mega_me6000 = FALSE;
$includes_delsys_trigno = FALSE;
$includes_myon = FALSE;
$includes_analog_EMG = FALSE;

// Class for filtering invalid XML characters.
function LoadXmlFile($xmlData)
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument("1.0", "UTF-8");
    $dom->strictErrorChecking = false;
    $dom->validateOnParse = false;
    $dom->recover = true;
    $dom->loadXML($xmlData);
    $output = simplexml_import_dom($dom);

    libxml_clear_errors();
    libxml_use_internal_errors(false);
    
    return $output;
}

foreach( $xml->Session->Subsession->Measurement as $meas )
{
    $measurement = array();
    $measurement["Type"] = (string)$meas["Type"];
    $measurement["Filename"] = (string)$meas["Filename"];
    foreach( $meas->Fields->children() as $field )
    {
        $measurement[$field->getName()] = (string)$field;
    }

    //Analog channel names (used files only)
    if (strcmp('True', $measurement["Used"]) == 0)
    {
        $settings_file = $working_directory . str_replace(".qtm", ".settings.xml", $measurement["Filename"]);
        if (file_exists($settings_file))
        {
			//load text file
			$input = file_get_contents($settings_file);
            
            //remove unwanted characters
            $clean = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F\xB0-\xFF]/', '', $input);
			
			//write file
            $cleanFileName = $settings_file . '.txt';
			file_put_contents($cleanFileName, $clean);

            $settings = simplexml_load_file($cleanFileName);
            $channel = array();
            $channels = array();
            foreach( $settings->Channels->children() as $ch )
            {
                $channel["Board"] = (string)$ch["Board"];
                $channel["Name"] = (string)$ch["Name"];
                $channel["Number"] = (string)$ch["Number"];
                $channels[] = $channel;
                if (strcmp('Noraxon', $ch["Board"]) == 0)
					$includes_noraxon = TRUE;
                if (strcmp('MEGA/ME6000', $ch["Board"]) == 0)
					$includes_mega_me6000 = TRUE;
                if (strcmp('Delsys Trigno', $ch["Board"]) == 0)
					$includes_delsys_trigno = TRUE;
                if (strcmp('Cometa', $ch["Board"]) == 0)
					$includes_myon = TRUE;
				if ((strcmp('PCI-DAS6402/16', $ch["Board"]) == 0 || strcmp('USB-2533', $ch["Board"]) == 0) && (strpos($ch["Name"], "EMG_") !== false || (substr($ch["Name"], 0, 1) == "R" || substr($ch["Name"], 0, 1) == "L")))
					$includes_analog_EMG = TRUE;
			}
            $measurement["Channels"]=$channels;
        }
    }
    $measurements[] = $measurement;
}

if(isset($template_file)){
	include($template_file);
}
?>