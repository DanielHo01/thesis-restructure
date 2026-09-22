<?php

function setV3DRegistryValue($registryPath, $registryKey, $value, $dataType = 'REG_SZ') {
	$registryPath = "HKEY_CURRENT_USER\\Software\\CMotion\\Visual3D\\" . $registryPath;
	$validDataTypes = ['REG_SZ', 'REG_BINARY', 'REG_DWORD', 'REG_BINARY'];
	
	if (!in_array($dataType, $validDataTypes)) {
		throw new InvalidArgumentException('Invalid data type: ' . $dataType);
	}

	// Ensure that a string ending in a backslash is terminated with another backslash (to avoid escaping the quote mark)
	if ($dataType == 'REG_SZ' && substr($value, -1) === '\\') {
		$value = $value . '\\';
	}

	$command = 'reg add '
	. '"' . $registryPath . '"'
	. ' /v "' . $registryKey . '"'
	. ' /t ' . $dataType
	. ' /d "' . $value . '"'
	. ' /f';

	return exec($command);
}

?>