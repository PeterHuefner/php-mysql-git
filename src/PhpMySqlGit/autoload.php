<?php

/**
 * In case you are using not composer but the source files directly, you can require this file which registers a suitable autoloader.
 */
spl_autoload_register(function ($objectToInlucde) {
	$parts = explode("\\", $objectToInlucde);
	end($parts);
	$class = current($parts);

	$file = __DIR__.DIRECTORY_SEPARATOR.$class.".php";

	$dir = $namespace = $parts[1];
	if ($dir) {
		$dir = strtolower($dir[0]).substr($dir, 1);
		if (!is_dir(__DIR__.DIRECTORY_SEPARATOR.$dir)) {
			$dir = null;
		}
	}

	if (file_exists($file)) {
		require_once($file);
	} else {
		$file = __DIR__.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.$class.".php";
		if (file_exists($file)) {
			require_once($file);
		}
	}
});