<?php

// only allow access through command line
if (php_sapi_name() !== "cli") {
	exit();
}

if (file_exists(($autoloadPath = __DIR__ . '/../vendor/autoload.php'))) {
	require_once $autoloadPath;
} else {
	require_once __DIR__ . '/../src/PhpMySqlGit/autoload.php';
}

var_dump(class_exists('PhpMySqlGit\PhpMySqlGit'));