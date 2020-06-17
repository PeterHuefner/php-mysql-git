<?php


namespace PhpMySqlGit;


class SqlConnection {

	protected $pdo;

	protected $structure = [];
	protected $database;

	protected $useOverwrites = false;

	/**
	 * SqlConnection constructor.
	 * @param string|\PDO $pdoString
	 * @param null|string $username
	 * @param null|string $password
	 * @param null|string $database
	 */
	function __construct($pdoString, $username = null, $password = null, $database = null) {
		if ($pdoString instanceof \PDO) {
			$this->pdo = $pdoString;
		} else {
			$this->pdo = new \PDO($pdoString, $username, $password);
		}

		$this->setDatabase($database);
	}

	/**
	 * @param mixed $database
	 */
	public function setDatabase($database): void {
		$this->database = $database;
	}

	/**
	 * @param bool $useOverwrites
	 */
	public function setUseOverwrites(bool $useOverwrites): void {
		$this->useOverwrites = $useOverwrites;
	}

	/**
	 * @return bool
	 */
	public function isUseOverwrites(): bool {
		return $this->useOverwrites;
	}

	public function escape($string) {
		$string = $this->pdo->quote($string);

		if ($string === '') {
			$string = "''";
		}

		return $string;
	}

	public function readDbStructure() {
		$this->setNames();
		$this->getDatabase();

		if (!empty($this->structure["databases"][$this->database])) {
			$this->getTables();
			$this->getColumns();
			$this->getIndicies();
			$this->getForeignKeys();
		}

		return $this->structure;
	}

	public function getDatabase() {
		$status = false;
		$sql    = "SELECT * FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :database;";

		$statement = $this->query($sql, [":database" => $this->database]);
		if ($statement) {
			$dbStructure = $statement->fetch(\PDO::FETCH_ASSOC);
			if ($dbStructure) {
				$status = true;
				$this->useDatabase();
				$this->structure["databases"][$this->database] = $dbStructure;

				if ($this->useOverwrites && PhpMySqlGit::$instance->isOverwriteCharset()) {
					$this->structure["databases"][$this->database]['DEFAULT_CHARACTER_SET_NAME'] = PhpMySqlGit::$instance->getCharset();
					$this->structure["databases"][$this->database]['DEFAULT_COLLATION_NAME']     = PhpMySqlGit::$instance->getCollation();
				}
			}
		}

		return $status;
	}

	protected function useDatabase() {
		$this->pdo->query("USE `$this->database`;");
	}

	protected function query($sql, $params = []) {
		$statement = $this->pdo->prepare($sql);

		foreach ($params as $paramName => $paramSettings) {
			if (is_array($paramSettings) && array_keys($paramSettings)[0] === 0) {
				foreach ($paramSettings as $index => $paramSetting) {
					$paramSetting = $this->prepParam($paramSetting);
					$statement->bindParam($paramName.$index, $paramSetting['value'], $paramSetting['type']);
				}
			} else {
				$paramSettings = $this->prepParam($paramSettings);
				$statement->bindParam($paramName, $paramSettings['value'], $paramSettings['type']);
			}
		}

		if ($statement->execute()) {
			return $statement;
		} else {
			return false;
		}
	}

	protected function prepParam($param) {
		if (!is_array($param)) {
			$param = [
				'value' => $param,
				'type'  => \PDO::PARAM_STR
			];
		}

		return $param;
	}

	protected function getTables() {
		$this->structure["databases"][$this->database]["tables"] = [];

		$sql = "SELECT * FROM information_schema.TABLES WHERE TABLE_SCHEMA = :database AND TABLE_TYPE = 'BASE TABLE';";
		foreach ($this->query($sql, [":database" => $this->database]) as $table) {
			$this->structure["databases"][$this->database]["tables"][$table["TABLE_NAME"]] = [
				"engine"     => PhpMySqlGit::$instance->isOverwriteEngine() && $this->useOverwrites ? PhpMySqlGit::$instance->getEngine() : $table["ENGINE"],
				"row_format" => PhpMySqlGit::$instance->isOverwriteRowFormat() && $this->useOverwrites ? PhpMySqlGit::$instance->getRowFormat() : $table["ROW_FORMAT"],
				"collation"  => PhpMySqlGit::$instance->isOverwriteCharset() && $this->useOverwrites ? PhpMySqlGit::$instance->getCollation() : $table["TABLE_COLLATION"],
				"comment"    => $table["TABLE_COMMENT"] ?? '',
			];
		}
	}

	protected function getColumns() {
		if (!empty($this->structure["databases"][$this->database]["tables"])) {
			foreach ($this->structure["databases"][$this->database]["tables"] as $table => &$structure) {
				$sql = "SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :database AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION;";
				foreach ($this->query($sql, [":table" => $table, ":database" => $this->database]) as $column) {
					$columnDefinition = [
						"name"           => $column["COLUMN_NAME"],
						"default"        => $this->getColumnDefault($column),
						"nullable"       => $column["IS_NULLABLE"] == "NO" ? false : true,
						"type"           => $column["DATA_TYPE"],
						"column_type"    => $column["COLUMN_TYPE"],
						"length"         => ($column["CHARACTER_MAXIMUM_LENGTH"] ?? $column["NUMERIC_PRECISION"].($column["NUMERIC_SCALE"] != 0 ? ",".$column["NUMERIC_SCALE"] : "")),
						"character_set"  => $column["CHARACTER_SET_NAME"] && PhpMySqlGit::$instance->isOverwriteCharset() && $this->useOverwrites ? PhpMySqlGit::$instance->getCharset() : $column["CHARACTER_SET_NAME"],
						"collation"      => $column["COLLATION_NAME"] && PhpMySqlGit::$instance->isOverwriteCharset() && $this->useOverwrites ? PhpMySqlGit::$instance->getCollation() : $column["COLLATION_NAME"],
						"auto_increment" => $column["EXTRA"] === "auto_increment",
						"comment"        => $column["COLUMN_COMMENT"],
						"on_update"      => stripos($column["EXTRA"], "on update") !== false,
					];

					$structure['columns'][] = $columnDefinition;
				}
			}
		}
	}

	protected function getIndicies() {
		if (!empty($this->structure["databases"][$this->database]["tables"])) {
			foreach ($this->structure["databases"][$this->database]["tables"] as $table => &$structure) {
				$sql = "SHOW INDEX FROM `".$table."`;";
				foreach ($this->query($sql) as $index) {

					$indexType = "§§keys";
					if ($index["Key_name"] === "PRIMARY") {
						$indexType = "§§primaryKeys";
					} elseif ($index["Non_unique"] === "0" || $index["Non_unique"] === 0) {
						$indexType = "§§uniqueKeys";
					} elseif ($index['Index_type'] === "FULLTEXT") {
						$indexType = "§§fulltextKeys";
					} elseif ($index['Index_type'] === "SPATIAL") {
						$indexType = "§§spatialKeys";
					}

					$structure[$indexType][$index["Key_name"]]["columns"][] = [
                        "name"     => $index["Column_name"],
                        "order"    => $index["Collation"],
                        "sub_part" => $index["Sub_part"],
					];

					if (in_array($index["Index_type"], ["BTREE", "HASH"])) {
						$structure[$indexType][$index["Key_name"]]["index_type"] = $index["Index_type"];
					}
				}
			}
		}
	}

	protected function getForeignKeys() {
		if (!empty($this->structure["databases"][$this->database]["tables"])) {
			foreach ($this->structure["databases"][$this->database]["tables"] as $table => &$structure) {
                $sql = "SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = :database AND TABLE_NAME = :table AND NOT ISNULL(REFERENCED_TABLE_NAME) ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION, POSITION_IN_UNIQUE_CONSTRAINT;";
                foreach ($this->query($sql, [":table" => $table, ":database" => $this->database]) as $foreignKey) {

                    $structure["§§foreignKeys"][$foreignKey["CONSTRAINT_NAME"]]["columns"][] = $foreignKey["COLUMN_NAME"];
                    //$structure["§§foreignKeys"][$foreignKey["CONSTRAINT_NAME"]]["referenced_schema"]    = $foreignKey["REFERENCED_TABLE_SCHEMA"];
                    $structure["§§foreignKeys"][$foreignKey["CONSTRAINT_NAME"]]["referenced_table"]     = $foreignKey["REFERENCED_TABLE_NAME"];
                    $structure["§§foreignKeys"][$foreignKey["CONSTRAINT_NAME"]]["referenced_columns"][] = $foreignKey["REFERENCED_COLUMN_NAME"];

                }

                $sql = "SELECT * FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = :database AND TABLE_NAME = :table;";
                foreach ($this->query($sql, [":table" => $table, ":database" => $this->database]) as $foreignKey) {
                    $structure["§§foreignKeys"][$foreignKey["CONSTRAINT_NAME"]]["UPDATE_RULE"] = $foreignKey["UPDATE_RULE"];
                    $structure["§§foreignKeys"][$foreignKey["CONSTRAINT_NAME"]]["DELETE_RULE"] = $foreignKey["DELETE_RULE"];
                }
			}
		}
	}

	protected function getColumnDefault(array $columnDefinition) {
		if ($columnDefinition['COLUMN_DEFAULT'] === "current_timestamp()") {
			$columnDefinition['COLUMN_DEFAULT'] = "CURRENT_TIMESTAMP";
		}

		// some older mariadb Versions (10.1) store default here as NULL, when the column is nullable
		// newer (10.4) store a 'NULL' instead, which is more clear
		// so if a column is nullable and the defaul value is NULL, convert it to 'NULL'
		// a default with a string NULL, would be saved as NULL surrounded with quotes, => "'NULL'".
		if ($columnDefinition["IS_NULLABLE"] != "NO") {
			if ($columnDefinition['COLUMN_DEFAULT'] === NULL) {
				$columnDefinition['COLUMN_DEFAULT'] = 'NULL';
			} elseif (is_string($columnDefinition['COLUMN_DEFAULT']) && strtolower($columnDefinition['COLUMN_DEFAULT']) === 'null') {
				$columnDefinition['COLUMN_DEFAULT'] = 'NULL';
			}
		}

		return $columnDefinition['COLUMN_DEFAULT'];
	}

	public function getData($tables = []) {
		$this->setNames();
		$this->useDatabase();

		$data = [];
		$sql = "SELECT * FROM information_schema.TABLES WHERE TABLE_SCHEMA = :database AND TABLE_TYPE = 'BASE TABLE'";
		$params = [":database" => $this->database];
		if ($tables) {
			$tableParams = [];
			foreach (array_values($tables) as $index => $table) {
				$tableParams[":table".$index] = $table;
			}
			$sql .= "AND TABLE_NAME IN(".implode(", ", array_keys($tableParams)).")";
			$params = array_merge($params, $tableParams);

			$sql .= " ORDER BY FIELD(TABLE_NAME, '".implode("', '", $tables)."')";
		}
		foreach ($this->query($sql, $params) as $table) {
			$data[$table["TABLE_NAME"]] = $this->getDataFromTable($table["TABLE_NAME"]);
		}

		return $data;
	}

	protected function getDataFromTable($table) {
		$sql = "SELECT * FROM `".$table."`;";
		$statement = $this->query($sql);
		return ($statement ? $statement->fetchAll(\PDO::FETCH_ASSOC) : []);
	}

	public function setNames() {
		$this->pdo->query("SET NAMES '".PhpMySqlGit::$instance->getCharset()."' COLLATE '".PhpMySqlGit::$instance->getCollation()."';");
	}
}