<?php


namespace PhpMySqlGit\Configure;


use PhpMySqlGit\Core\Common;
use PhpMySqlGit\PhpMySqlGit;
use PhpMySqlGit\Sql\Key;

class ForeignKeys {
	use Configuration;

	public function configure() {
		foreach ($this->fileStructure["databases"][$this->database]["tables"] as $tableName => &$tableSettings) {
			if (!empty($tableSettings["§§foreignKeys"])) {
				$this->checkForeignKeysForTable($tableName, $tableSettings);
			}
		}

		// drop foreign keys that are not present in filestructure
		foreach ($this->dbStructure["databases"][$this->database]["tables"] as $tableName => &$tableSettings) {
			if (!empty($tableSettings["§§foreignKeys"])) {
				foreach ($tableSettings["§§foreignKeys"] as $foreignKey => &$foreignKeySettings) {
					if (empty($this->fileStructure["databases"][$this->database]["tables"][$tableName]["§§foreignKeys"][$foreignKey])) {
						$key = new Key($foreignKeyName, $foreignKeySettings);
						$this->statements[] = "ALTER TABLE `{$tableName}` ".$key->dropForeignKey();
					}
				}
			}
		}
	}

	protected function checkForeignKeysForTable($tableName, &$structureTableSettings) {
		$dbTableSettings = &$this->dbStructure["databases"][$this->database]["tables"][$tableName];

		$addStatements = [];

		foreach ($structureTableSettings["§§foreignKeys"] as $foreignKeyName => $foreignKeySettings) {
			if (!empty($dbTableSettings["§§foreignKeys"][$foreignKeyName])) {
				if (!Common::array_compare($foreignKeySettings, $dbTableSettings["§§foreignKeys"][$foreignKeyName])) {
					PhpMySqlGit::$changedObjects["databases"][$this->database]["foreignKeys"][$tableName][$foreignKeyName] = true;
					$key = new Key($foreignKeyName, $foreignKeySettings);

					$this->statements[] = "ALTER TABLE `{$tableName}` ".$key->dropForeignKey();
					$addStatements[$tableName][] = "ADD ".$key->foreignKeyDefinition();

					$dbTableSettings["§§foreignKeys"][$foreignKeyName] = $foreignKeySettings;
				}
			} else {
				PhpMySqlGit::$changedObjects["databases"][$this->database]["foreignKeys"][$tableName][$foreignKeyName] = true;
				$key = new Key($foreignKeyName, $foreignKeySettings);
				$addStatements[$tableName][] = "ADD ".$key->foreignKeyDefinition();
				$dbTableSettings["§§foreignKeys"][$foreignKeyName] = $foreignKeySettings;
			}
		}

		foreach ($addStatements as $tableName => $statements) {
			$this->statements[] = "ALTER TABLE `{$tableName}` \n\t".implode(",\n\t", $statements);
		}
	}
}