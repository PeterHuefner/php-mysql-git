<?php


namespace PhpMySqlGit\Configure;


use PhpMySqlGit\Core\Common;
use PhpMySqlGit\PhpMySqlGit;
use PhpMySqlGit\Sql\Key;

class ForeignKeys {
	use Configuration;

	protected $dropStatements = [];
	protected $addStatements  = [];

	/**
	 * @return array
	 */
	public function getDropStatements(): array {
		return $this->dropStatements;
	}

	/**
	 * @return array
	 */
	public function getAddStatements(): array {
		return $this->addStatements;
	}

	public function configure() {
		// drop foreign keys that are not present in filestructure
		foreach ($this->dbStructure["databases"][$this->database]["tables"] as $tableName => &$tableSettings) {
			if (!empty($tableSettings["§§foreignKeys"])) {
				foreach ($tableSettings["§§foreignKeys"] as $foreignKey => &$foreignKeySettings) {
					if (empty($this->fileStructure["databases"][$this->database]["tables"][$tableName]["§§foreignKeys"][$foreignKey])) {
						PhpMySqlGit::$changedObjects["databases"][$this->database]["foreignKeys"][$tableName][$foreignKey] = true;
						$key = new Key($foreignKey, $foreignKeySettings);
						$this->dropStatements[] = "ALTER TABLE `{$tableName}` ".$key->dropForeignKey();
					}
				}
			}
		}

		foreach ($this->fileStructure["databases"][$this->database]["tables"] as $tableName => &$tableSettings) {
			if (!empty($tableSettings["§§foreignKeys"])) {
				$this->checkForeignKeysForTable($tableName, $tableSettings);
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

					$this->dropStatements[] = "ALTER TABLE `{$tableName}` ".$key->dropForeignKey();
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
			$this->addStatements[] = "ALTER TABLE `{$tableName}` \n\t".implode(",\n\t", $statements);
		}
	}
}