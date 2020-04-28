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

$structureDir = __DIR__ . '/data/multiple_databases';
$phpMySqlGit = new PhpMySqlGit\PhpMySqlGit([
	'connectionString' => 'mysql:host=127.0.0.1;port=3306;',
	'username'         => 'demouser',
]);

$cli = new \PhpMySqlGit\CommandLineInterface($phpMySqlGit);
$cli->setDataDir($structureDir);
$cli->execute();