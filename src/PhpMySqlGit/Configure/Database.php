<?php


namespace PhpMySqlGit\Configure;


use PhpMySqlGit\PhpMySqlGit;
use PhpMysSqlGit\Sql\Key;

class Database {
	use Configuration;

	protected $originDbStructure;
	protected $skipGlobalUseStatement = false;

	public function configure() {
		$this->originDbStructure = $this->dbStructure;

		$this->configureDatabase();

		$tables = new Tables($this->dbStructure, $this->fileStructure);
		$tables->configure();
		$this->statements             = array_merge($this->statements, $tables->getStatements());
		$this->commentedOutStatements = array_merge($this->commentedOutStatements, $tables->getCommentedOutStatements());

		$foreignKeys = new ForeignKeys($this->dbStructure, $this->fileStructure);
		$foreignKeys->configure();
		$this->statements             = array_merge($this->statements, $foreignKeys->getStatements());
		$this->commentedOutStatements = array_merge($this->commentedOutStatements, $this->getCommentedOutStatements());

		/*$columns = new Columns($this->dbStructure, $this->fileStructure);
		$columns->configure();
		$this->statements = array_merge($this->statements, $columns->getStatements());
		$this->commentedOutStatements = array_merge($this->commentedOutStatements, $columns->getCommentedOutStatements());*/

		$this->handleForeignKeys();
	}

	protected function configureDatabase() {
		$ignoreKeys = [];

		if (PhpMySqlGit::$instance->isIgnoreCharset()) {
			$ignoreKeys[] = "DEFAULT_CHARACTER_SET_NAME";
			$ignoreKeys[] = "DEFAULT_COLLATION_NAME";
		}

		if (!empty($this->dbStructure["databases"][$this->database])) {
			foreach ($this->dbStructure["databases"][$this->database] as $config => $value) {
				if ($config === "tables") {
					continue;
				} elseif (isset($this->fileStructure["databases"])
					&& array_key_exists($config, $this->fileStructure["databases"][$this->database])
					&& strtolower($value) !== strtolower($this->fileStructure["databases"][$this->database][$config])
				) {
					$this->statements[]                                                    = $this->getDb($this->fileStructure["databases"][$this->database])->alter();
					PhpMySqlGit::$changedObjects["databases"][$this->database]["database"] = true;
					break;
				}
			}

		} else {
			$this->statements[] = $this->getDb()->create();
			$this->statements[] = "USE `{$this->database}`";
			$this->skipGlobalUseStatement = true;
		}
	}

	protected function getDb($settings) {
		$db            = new \PhpMysSqlGit\Sql\Database();
		$db->name      = PhpMySqlGit::$instance->getDbname();

		$db->charset   = PhpMySqlGit::$instance->isOverwriteCharset() ? PhpMySqlGit::$instance->getCharset() : $settings["DEFAULT_CHARACTER_SET_NAME"];
		$db->collation = PhpMySqlGit::$instance->isOverwriteCharset() ? PhpMySqlGit::$instance->getCollation() : $settings["DEFAULT_COLLATION_NAME"];

		return $db;
	}

	protected function handleForeignKeys() {
		$foreignKeysToDrop = [];

		if (!PhpMySqlGit::$instance->isSkipForeignKeyChecks()) {
			if (!empty(PhpMySqlGit::$changedObjects["databases"][$this->database]["columns"])) {
				foreach (PhpMySqlGit::$changedObjects["databases"][$this->database]["columns"] as $tableName => &$columns) {
					foreach ($columns as $columnName => $changed) {
						$foreignKeysToDrop = array_merge($foreignKeysToDrop, $this->getForeignKeysRelatedTo($tableName, $columnName));
					}
				}
			}
		}

		$preStatements   = [];
		$afterStatements = [];

		foreach ($foreignKeysToDrop as $foreignKeyName => $foreignKeySettings) {
			$key               = new Key($foreignKeyName, $foreignKeySettings);
			$preStatements[]   = "ALTER TABLE `{$foreignKeySettings["originTable"]}` ".$key->dropForeignKey();
			$afterStatements[] = "ALTER TABLE `{$foreignKeySettings["originTable"]}` ADD ".$key->foreignKeyDefinition();
		}

		$this->statements = array_merge($preStatements, $this->statements, $afterStatements);
	}

	protected function getForeignKeysRelatedTo($table, $column) {
		$relatedForeignKeys = [];

		foreach ($this->originDbStructure["databases"][$this->database]["tables"] as $tableName => &$tableSettings) {
			if (!empty($tableSettings["§§foreignKeys"])) {
				foreach ($tableSettings["§§foreignKeys"] as $foreignKeyName => &$foreignKeySettings) {
					if (
						($tableName === $table && in_array($column, $foreignKeySettings['columns']))
						|| ($table === $foreignKeySettings["referenced_table"] && in_array($column, $foreignKeySettings["referenced_columns"]))
					) {
						$relatedForeignKeys[$foreignKeyName]                = array_merge(
							$relatedForeignKeys[$foreignKeyName] ?? [],
							$foreignKeySettings
						);
						$relatedForeignKeys[$foreignKeyName]["originTable"] = $tableName;
					}
				}
			}
		}

		return $relatedForeignKeys;
	}

	/**
	 * @return array
	 */
	public function getStatements(): array {

		foreach ($this->statements as &$statement) {
			$statement = trim($statement);
			if (preg_match('/;$/', $statement) !== 1) {
				$statement .= ";";
			}
		}

		if (!$this->skipGlobalUseStatement) {
			$this->statements = array_merge(["USE `{$this->database}`;"], $this->statements);
		}

		return $this->statements;
	}
}