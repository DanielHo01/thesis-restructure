<?php
namespace Qualisys\Gait\Model;

use Qualisys\Gait\Model\Session;

class GaitSession extends Session {
	public function __construct($sessionXml) {
		$xml             = simplexml_load_file($sessionXml);
		$subject         = [];
		$session         = [];
		$subsessions     = [];
		$measurements    = [];
		$subject['Type'] = (string)$xml['Type'];
		$session['Type'] = (string)$xml->Session['Type'];
		$countDynamicTrials = 0;
		
		foreach ($xml->Fields->children() as $field) {
			$subject[$field->getName()] = (string)$field;
		}

		foreach ($xml->Session->Fields->children() as $field) {
			$session[$field->getName()] = (string)$field;
			$this->{$field->getName()}  = (string)$field;
		}

		foreach($xml->Session->Subsession as $s) {
			$subsession             = array();
			$subsession['Type']     = (string)$s['Type'];
			$subsession['Filename'] = (string)$s['Filename'];

			foreach($s->Fields->children() as $field) {
				$subsession[$field->getName()] = (string)$field;
			}

			foreach($s->Measurement as $m) {
				$measurement             = array();
				$measurement['Type']     = (string)$m['Type'];
				$measurement['Filename'] = (string)$m['Filename'];


				foreach($m->Fields->children() as $field) {
					$measurement[$field->getName()] = (string)$field;
				}
				
				$measurements[] = $measurement;
			}
		
			$subsessions[] = $subsession;
		}

		$this->fields       = $session;
		$this->subject      = $subject;
		$this->subsessions = $subsessions;
		$this->measurements = $measurements;
		
		foreach($this->measurements as $m) {
			if ($m['Used'] == 'True' && $m['Measurement_type'] == 'Dynamic')
				$countDynamicTrials = $countDynamicTrials + 1;
		}
		
		$this->countDynamicTrials = $countDynamicTrials;
	}
}
?>