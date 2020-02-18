<?php


namespace PhpMySqlGit\Configure;


use PhpMySqlGit\PhpMySqlGit;
use PhpMysSqlGit\Sql\Column;
use PhpMysSqlGit\Sql\Table;

class Tables {
	use Configuration;

	protected $currentTable = "";

	public function configure() {
		$dbTables   = &$this->dbStructure["databases"][$this->database]['tables'];
		$fileTables = &$this->fileStructure["databases"][$this->database]['tables'];

		$missingTables    = array_diff(array_keys($fileTables), array_keys($dbTables));
		$additionalTables = array_diff(array_keys($dbTables), array_keys($fileTables));

		foreach ($missingTables as $missingTable) {
			$this->statements[] = (new Table($missingTable, $fileTables[$missingTable]))->create();
		}

		foreach ($additionalTables as $additionalTable) {
			$this->commentedOutStatements[] = "/* ".(new Table($additionalTable, []))->drop()." /**/";
		}

		foreach ($dbTables as $tableName => &$tableSettings) {
			if (!empty($fileTables[$tableName])) {
				$this->currentTable = $tableName;
				$this->checkTable();
			}
		}
	}

	protected function checkTable() {
		$alterTableOptions = false;

		foreach ($this->dbStructure["databases"][$this->database]['tables'][$this->currentTable] as $settingName => &$settings) {
			if (is_scalar($settings)) {
				if (!$this->checkTableSetting($settingName)) {
					$alterTableOptions = true;
					PhpMySqlGit::$changedObjects["databases"][$this->database]["tables"][$this->currentTable] = true;
				}
			}
		}

		$columnChecker = new Columns($this->dbStructure, $this->fileStructure);
		$columnChecker->setCurrentTable($this->currentTable);

		$alterStatements = $columnChecker->checkTableColumns();

		if ($alterStatements || $alterTableOptions) {
			$this->statements[] =
				(new Table($this->currentTable, $this->fileStructure['databases'][$this->database]['tables'][$this->currentTable]))
					->alter($alterStatements, $alterTableOptions);
			PhpMySqlGit::$changedObjects["databases"][$this->database]["tables"][$this->currentTable] = true;
		}
	}

	protected function checkTableSetting($setting, $ignoreCase = true) {
		$dbSetting   = &$this->dbStructure["databases"][$this->database]['tables'][$this->currentTable][$setting];
		$fileSetting = &$this->fileStructure["databases"][$this->database]['tables'][$this->currentTable][$setting];

		if ($ignoreCase) {
			$dbSetting   = strtolower($dbSetting);
			$fileSetting = strtolower($fileSetting);
		}

		return $dbSetting === $fileSetting;
	}


}