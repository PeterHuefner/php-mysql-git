<?php


namespace PhpMySqlGit\Configure;


use PhpMySqlGit\Core\Common;
use PhpMySqlGit\PhpMySqlGit;
use PhpMysSqlGit\Sql\Key;

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

		foreach ($structureTableSettings["§§foreignKeys"] as $foreignKeyName => $foreignKeySettings) {
			if (!empty($dbTableSettings["§§foreignKeys"][$foreignKeyName])) {
				if (!Common::array_compare($foreignKeySettings, $dbTableSettings["§§foreignKeys"][$foreignKeyName])) {
					PhpMySqlGit::$changedObjects["databases"][$this->database]["foreignKeys"][$tableName][$foreignKeyName] = true;
					$key = new Key($foreignKeyName, $foreignKeySettings);

					$this->statements[] = "ALTER TABLE `{$tableName}` ".$key->dropForeignKey();
					$this->statements[] = "ALTER TABLE `{$tableName}`  ADD ".$key->foreignKeyDefinition();

					$dbTableSettings["§§foreignKeys"][$foreignKeyName] = $foreignKeySettings;
				}
			} else {
				PhpMySqlGit::$changedObjects["databases"][$this->database]["foreignKeys"][$tableName][$foreignKeyName] = true;
				$key = new Key($foreignKeyName, $foreignKeySettings);
				$this->statements[] = "ALTER TABLE `{$tableName}` ADD ".$key->foreignKeyDefinition();
				$dbTableSettings["§§foreignKeys"][$foreignKeyName] = $foreignKeySettings;
			}
		}
	}
}