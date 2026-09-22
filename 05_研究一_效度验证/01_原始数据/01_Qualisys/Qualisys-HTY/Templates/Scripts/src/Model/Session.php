<?php
namespace Qualisys\Gait\Model;

class Session {
	public function __construct($sessionXml) {
		$xml             = simplexml_load_file($sessionXml);
		$subject         = array();
		$session         = array();
		$measurements    = array();
		$subject['Type'] = (string)$xml['Type'];
		$session['Type'] = (string)$xml->Session['Type'];

		foreach ($xml->Fields->children() as $field) {
			$subject[$field->getName()] = (string)$field;
		}

		foreach ($xml->Session->Fields->children() as $field) {
			$session[$field->getName()] = (string)$field;
			$this->{$field->getName()}  = (string)$field;
		}

		foreach($xml->Session->Measurement as $m) {
			$measurement             = array();
			$measurement['Type']     = (string)$m['Type'];
			$measurement['Filename'] = (string)$m['Filename'];

			foreach($m->Fields->children() as $field) {
				$measurement[$field->getName()] = (string)$field;
			}
			
			$measurements[] = $measurement;
		}

		$this->fields       = $session;
		$this->subject      = $subject;
		$this->measurements = $measurements;
	}

	public function used()  {
		return array_filter($this->measurements, function ($m) { return $m['Used'] == 'True'; });
	}

	public function dynamic($used = true)  {
		return array_filter($this->measurements, function($m) use($used) { return $m['Measurement_type'] == 'Dynamic' && ($used ? $m['Used'] == 'True' : $m['Used'] == 'False'); });
	}
}
?>