<?php

$phpMySqlGit = include __DIR__ . '/boot.php';

$structureDir = __DIR__ . '/data/multiple_databases';

$phpMySqlGit->setSaveNoDbname(false);

foreach (['sakila', 'demo'] as $database) {
	$phpMySqlGit->setDbName($database);

	$phpMySqlGit->saveStructure($structureDir);
	$phpMySqlGit->saveData($structureDir);
}

