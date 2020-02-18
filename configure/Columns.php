<?php


namespace PhpMySqlGit\Configure;


use PhpMySqlGit\PhpMySqlGit;
use PhpMysSqlGit\Sql\Column;
use PhpMysSqlGit\Sql\Key;

class Columns {
	use Configuration;

	const KEY_TYPES = [
		'§§primaryKeys'  => [
			'definition' => "primaryKeyDefinition",
			'drop'       => "dropPrimaryKey",
		],
		'§§keys'         => [
			'definition' => "keyDefinition",
			'drop'       => "dropKey",
		],
		'§§uniqueKeys'   => [
			'definition' => "uniqueKeyDefinition",
			'drop'       => "dropUniqueKey",
		],
		'§§fulltextKeys' => [
			'definition' => "fulltextKeyDefinition",
			'drop'       => "dropFulltextKey",
		],
		'§§spatialKeys'  => [
			'definition' => "spatialKeyDefinition",
			'drop'       => "dropSpatialKey",
		],
	];

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

		$changedColumns = [];
		$dbColumns      = &$this->dbStructure["databases"][$this->database]['tables'][$this->currentTable]['columns'];
		$fileColumns    = &$this->fileStructure["databases"][$this->database]['tables'][$this->currentTable]['columns'];

		// remove columns that are present in DB but not in file structure
		foreach ($dbColumns as $dbColIndex => &$dbColumn) {
			if (!$this->getColSettings($dbColumn['name'], $this->fileStructure)) {
				unset($dbColumns[$dbColIndex]);
				$alterStatements[] =
					"/* Column {$dbColumn['name']} exists in DB but not in structure */\n\t".
					(new Column($dbColumn['name'], []))->drop();
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

				$changedColumns[] = $columnName;
			} else if ($fileColIndex !== $dbColIndex) {
				// column position is different, the column must be modified
				// automatically all column differences are solved
				$alterStatements[] = "/* Column $columnName has another position */\n\t".$columnSQL->modify();
				//change the saved db structre to be not confused when checking other columns
				unset($dbColumns[$dbColIndex]);
				$this->appendColumnToStructure($dbColumns, $previousColumn, [$columnSettings]);

				$changedColumns[] = $columnName;
			} else if (!$this->checkColumn($dbColSettings, $columnSettings)) {
				// column is different, it may be a setting or the case of the name
				$alterStatements[]        = "/* Column $columnName is different */\n\t".$columnSQL->modify();
				$dbColumns[$fileColIndex] = $columnSettings;

				$changedColumns[] = $columnName;
				PhpMySqlGit::$changedObjects["databases"][$this->database]["columns"][$this->currentTable][$columnName] = true;
			}

			$previousColumn = $columnName;
		}

		list($beforeStatements, $afterStatements) = $this->checkLocalKeys($changedColumns);

		$alterStatements = array_merge(
			$beforeStatements,
			$alterStatements,
			$afterStatements
		);

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

	protected function checkLocalKeys(array $changedColumns) {

		$beforeStatements = [];
		$afterStatements  = [];

		foreach (self::KEY_TYPES as $keyType => $handlers) {
			$dbKeys   = &$this->dbStructure["databases"][$this->database]["tables"][$this->currentTable][$keyType];
			$fileKeys = &$this->fileStructure["databases"][$this->database]["tables"][$this->currentTable][$keyType];

			if (!($fileKeys && is_array($fileKeys))) {
				$fileKeys = [];
			}

			if (!($dbKeys && is_array($dbKeys))) {
				$dbKeys = [];
			}

			foreach ($fileKeys as $keyName => &$keyDefinition) {
				$keyStatus = $this->checkKey($keyType, $keyName);

				if ($keyStatus !== "equal") {
					// key is somewhat different
					PhpMySqlGit::$changedObjects["databases"][$this->database]["keys"][$this->currentTable][$keyName] = true;

					$keyMaker = new Key($keyName, $keyDefinition);

					if ($keyStatus !== "absent") {
						// when the key exists in the DB but is different, it has to be dropped first
						$beforeStatements[] = call_user_func([$keyMaker, $handlers['drop']]);
					}

					// any difference is solved by add the key correctly, again if it has exists before
					$afterStatements[] = "ADD ".call_user_func([$keyMaker, $handlers['definition']]);
				}
			}

			$additionalKeys = array_diff(array_keys($dbKeys), array_keys($fileKeys));

			foreach ($additionalKeys as $additionalKey) {
				$keyMaker           = new Key($additionalKey, []);
				$beforeStatements[] = call_user_func([$keyMaker, $handlers['drop']]);

			}
		}

		return [$beforeStatements, $afterStatements];
	}

	protected function checkKey(string $keyType, string $keyName) {
		$dbKey   = &$this->dbStructure["databases"][$this->database]["tables"][$this->currentTable][$keyType][$keyName] ?? null;
		$fileKey = &$this->fileStructure["databases"][$this->database]["tables"][$this->currentTable][$keyType][$keyName];

		if (!$dbKey) {
			return "absent";
		} elseif (count($dbKey["columns"]) !== count($fileKey["columns"])) {
			return "diff-columns";
		} elseif (($dbKey["index_type"] ?? '') !== ($fileKey["index_type"] ?? '')) {
			return "diff-type";
		} else {
			foreach ($fileKey["columns"] as $colIndex => $column) {
				if ($column["name"] !== $dbKey["columns"][$colIndex]["name"]) {
					return "diff-column-order";
				}
			}
		}

		return "equal";
	}
}