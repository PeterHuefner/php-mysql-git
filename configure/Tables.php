<?php


namespace PhpMySqlGit;


use PhpMySqlGit\SQL\Table;

class Tables {
	use Configuration;

	public function configure() {
		$dbTables = &$this->dbStructure["databases"][$this->database]['tables'];
		$fileTables = &$this->fileStructure["databases"][$this->database]['tables'];

		$missingTables    = array_diff(array_keys($fileTables), array_keys($dbTables));
		$additionalTables = array_diff(array_keys($dbTables), array_keys($fileTables));

		foreach ($missingTables as $missingTable) {
			$this->statements[] = (new Table($missingTable, $fileTables[$missingTable]))->create();
		}

		foreach ($additionalTables as $additionalTable) {
			$this->commentedOutStatements[] = "/* ".(new Table($additionalTable, []))->drop()." /**/";
		}
	}

	protected function checkTable() {

	}
}