<?php


namespace PhpMySqlGit\Configure;


use PhpMySqlGit\PhpMySqlGit;
use PhpMySqlGit\Sql\Key;
use PhpMySqlGit\Sql\Table;

class Database {
	use Configuration;

	protected $originDbStructure;
	protected $skipGlobalUseStatement = false;

	public function configure() {
		$this->originDbStructure = $this->dbStructure;

		$this->configureDatabase();

		$tables = new Tables($this->dbStructure, $this->fileStructure);
		$tables->configure();
		$tableStatements              = array_merge($this->statements, $tables->getStatements());
		$this->commentedOutStatements = array_merge($this->commentedOutStatements, $tables->getCommentedOutStatements());

		$foreignKeys = new ForeignKeys($this->dbStructure, $this->fileStructure);
		$foreignKeys->configure();

		$this->statements = array_merge(
			$foreignKeys->getDropStatements(),
			$tableStatements,
			$foreignKeys->getAddStatements()
		);

		//$this->statements             = array_merge($this->statements, $foreignKeys->getStatements());
		$this->commentedOutStatements = array_merge($this->commentedOutStatements, $foreignKeys->getCommentedOutStatements());

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
					&& strtolower((string) $value) !== strtolower((string) $this->fileStructure["databases"][$this->database][$config])
				) {
					$this->statements[]                                                    = $this->getDb($this->fileStructure["databases"][$this->database])->alter();
					PhpMySqlGit::$changedObjects["databases"][$this->database]["database"] = true;
					break;
				}
			}

		} else {
			$this->statements[] = $this->getDb($this->fileStructure["databases"][$this->database])->create();
			$this->statements[] = "USE `{$this->database}`";
			$this->skipGlobalUseStatement = true;
		}
	}

	protected function getDb($settings) {
		$db            = new \PhpMySqlGit\Sql\Database();
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
			// when foreign key was not changed before through explicit foreign key checks
			if (empty(PhpMySqlGit::$changedObjects["databases"][$this->database]["foreignKeys"][$foreignKeySettings["originTable"]][$foreignKeyName])) {
				$key               = new Key($foreignKeyName, $foreignKeySettings);
				$preStatements[]   = "ALTER TABLE `{$foreignKeySettings["originTable"]}` ".$key->dropForeignKey();

				// foreign key exists in structure, so key can be added at end again
				if (!empty($this->fileStructure["databases"][$this->database]["tables"][$foreignKeySettings["originTable"]]["§§foreignKeys"][$foreignKeyName])) {
					$afterStatements[] = "ALTER TABLE `{$foreignKeySettings["originTable"]}` ADD ".$key->foreignKeyDefinition();
				}
			}
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
			$statement = trim((string) $statement);
			if (preg_match('/;$/', $statement) !== 1) {
				$statement .= ";";
			}
		}

		if (PhpMySqlGit::$changedCharsetObjects && !PhpMySqlGit::$instance->isIgnoreCharset()) {
			$convertStatements = [<<<EOT
###############################################################################################################################################################################
# !!! ATTENTION !!! ATTENTION !!! ATTENTION !!! !!! ATTENTION !!! ATTENTION !!! ATTENTION !!! ATTENTION !!! ATTENTION !!! ATTENTION !!! ATTENTION !!! ATTENTION !!! ATTENTION #
#                                                                                                                                                                             #
# At least one table has a column with a different charset/collation as stated in file structure and would be changed in statements.                                          #
# In general there are two ways to change charsets for tables and their columns. One is to modify/change each column and specify the charset.                                 #
# Other one is to CONVERT in ALTER TABLE, this would change charset for table and all columns (which support a charset) in one statement.                                     #
#                                                                                                                                                                             #
# In some situations these ways can fail and crash the server (should start with normal operations, so this should not lead to a heavy database crash).                       #
# It depends on server version and sitation https://jira.mariadb.org/browse/MDEV-19300 , https://jira.mariadb.org/browse/MDEV-21214 .                                         #
#                                                                                                                                                                             #
# Second way can be more reliable, BUT: It also can change column types!                                                                                                      #
# Text can become mediumtext etc., to ensure your data will fit after convert. This inline convert is done by the server and php-mysql-git will                               #
# generate alter statements the next time to the stored types.                                                                                                                #
# You have to check and solve this by your own (recreate/save structure files).                                                                                               #
#                                                                                                                                                                             #
# Following statements contain both ways. Directly after this comment block come the CONVERT-Statements, they are commented out via multiline comment.                        #
# Statements with the modify way are printed further down, togehter with other changes. They are not commented out.                                                           #
###############################################################################################################################################################################
/*
EOT
];
			foreach (array_keys(PhpMySqlGit::$changedCharsetObjects) as $tableName) {
				$table = new Table($tableName, $this->fileStructure["databases"][$this->database]["tables"][$tableName]);
				$convertStatements[] = $table->convertTo();
			}
			$convertStatements[] = "/**/";
			$this->statements = array_merge($convertStatements, $this->statements);
		}

		if (!$this->skipGlobalUseStatement) {
			$this->statements = array_merge(["USE `{$this->database}`;"], $this->statements);
		}

		return $this->statements;
	}
}