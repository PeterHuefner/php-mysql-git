<?php


namespace PhpMySqlGit;


use PhpMySqlGit\SQL\Column;

class Columns {
	use Configuration;

	/**
	 * @var string
	 */
	protected $currentTable = "";

	/**
	 * @param string $currentTable
	 */
	public function setCurrentTable(string $currentTable): void {
		$this->currentTable = $currentTable;
	}

	/**
	 * @return array
	 */
	public function checkTableColumns() {
		$alterStatements = [];

		$dbColumns   = &$this->dbStructure["databases"][$this->database]['tables'][$this->currentTable]['columns'];
		$fileColumns = &$this->fileStructure["databases"][$this->database]['tables'][$this->currentTable]['columns'];

		$dbColIndexes = array_keys($dbColumns);

		// remove columns that are present in DB but not in file structure
		foreach ($dbColumns as $dbColIndex => &$dbColumn) {
			if (!$this->getColSettings($dbColumn['name'], $this->fileStructure)) {
				unset($dbColumns[$dbColIndex]);
				$alterStatements[] =
					"/* Column {$dbColumn['name']} exists in DB but not in structure */\n\t".(new Column($dbColumn['name'], []))->drop();
			}
		}
		$dbColumns = array_values($dbColumns);
		
		$previousColumn = false;
		foreach ($fileColumns as $fileColIndex => &$columnSettings) {
			$columnName    = $columnSettings['name'];
			$dbColIndex    = null;
			$dbColSettings = [];

			$columnSQL = new Column($columnName, $columnSettings);
			if ($previousColumn) {
				$columnSQL->setPreviousColumn($previousColumn);
			} else {
				$columnSQL->setFirstColumn(true);
			}

			if ($search = $this->getColSettings($columnName, $this->dbStructure)) {
				list($dbColIndex, $dbColSettings) = $search;
			}

			if (!$dbColSettings) {
				// column doesn't exists in database
				$alterStatements[] = "/* Column $columnName does not exists */\n\t".$columnSQL->add();
				// change the saved db structre to be not confused when checking other columns
				$this->appendColumnToStructure($dbColumns, $previousColumn, [$columnSettings]);
			} else if ($fileColIndex !== $dbColIndex) {
				// column position is different, the column must be modified
				// automatically all column differences are solved
				$alterStatements[] = "/* Column $columnName has another position */\n\t".$columnSQL->modify();
				//change the saved db structre to be not confused when checking other columns
				unset($dbColumns[$dbColIndex]);
				$this->appendColumnToStructure($dbColumns, $previousColumn, [$columnSettings]);
			} else if (!$this->checkColumn($dbColSettings, $columnSettings)) {
				// column is different, it may be a setting or the case of the name
				$alterStatements[]        = "/* Column $columnName is different */\n\t".$columnSQL->modify();
				$dbColumns[$fileColIndex] = $columnSettings;
			}

			$previousColumn = $columnName;
			$dbColIndexes   = array_keys($dbColumns);
		}

		return $alterStatements;
	}

	/**
	 * @param string $columnName
	 * @param array $structure
	 * @param bool $ignoreCase
	 * @return array|mixed
	 */
	protected function getColSettings(string $columnName, array &$structure, $ignoreCase = true) {
		foreach ($structure["databases"][$this->database]['tables'][$this->currentTable]['columns'] as $index => &$settings) {
			if ($ignoreCase) {
				if (strtolower($settings['name']) == strtolower($columnName)) {
					return [$index, $settings];
				}
			} else {
				if ($settings['name'] == $columnName) {
					return [$index, $settings];
				}
			}
		}

		return [];
	}

	protected function appendColumnToStructure(&$structure, $previousColumn, $columnSettings) {
		$index = false;
		if ($previousColumn) {
			foreach ($structure as $colIndex => &$column) {
				if ($column['name'] === $previousColumn) {
					$index = $colIndex;
				}
			}
		}
		Common::array_append($structure, $index, $columnSettings);
	}

	/**
	 * @param array $dbColSettings
	 * @param array $fileColSettings
	 * @return bool
	 */
	public function checkColumn(array $dbColSettings, array $fileColSettings) {
		$columnCorrect = true;

		foreach ($dbColSettings as $settingName => $settingValue) {
			if ($settingValue !== $fileColSettings[$settingName]) {
				$columnCorrect = false;
				break;
			}
		}

		return $columnCorrect;
	}
}