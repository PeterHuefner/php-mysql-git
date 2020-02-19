<?php


namespace PhpMySqlGit;


class SqlConnection {

	protected $pdo;

	protected $structure = [];
	protected $database;

	function __construct($pdoString, $username, $password, $datbase) {
		$this->pdo      = new \PDO($pdoString, $username, $password);
		$this->setDatabase($datbase);
	}

	/**
	 * @param mixed $database
	 */
	public function setDatabase($database): void {
		$this->database = $database;
	}

	public function escape($string) {
		$string = $this->pdo->quote($string);

		if ($string === '') {
			$string = "''";
		}

		return $string;
	}

	public function readDbStructure() {
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
				$this->pdo->query("USE $this->database;");
				$this->structure["databases"][$this->database] = $dbStructure;

				if (PhpMySqlGit::$instance->isOverwriteCharset()) {
					$this->structure["databases"][$this->database]['DEFAULT_CHARACTER_SET_NAME'] = PhpMySqlGit::$instance->getCharset();
					$this->structure["databases"][$this->database]['DEFAULT_COLLATION_NAME']     = PhpMySqlGit::$instance->getCollation();
				}
			}
		}

		return $status;
	}

	protected function query($sql, $params = []) {
		$statement = $this->pdo->prepare($sql);

		foreach ($params as $paramName => $paramSettings) {
			if (!is_array($paramSettings)) {
				$paramSettings = [
					'value' => $paramSettings,
					'type'  => \PDO::PARAM_STR
				];
			}

			$statement->bindParam($paramName, $paramSettings['value'], $paramSettings['type']);
		}

		if ($statement->execute()) {
			return $statement;
		} else {
			return false;
		}
	}

	protected function getTables() {
		$sql = "SELECT * FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE';";
		foreach ($this->query($sql) as $table) {
			$this->structure["databases"][$this->database]["tables"][$table["TABLE_NAME"]] = [
				"engine"     => PhpMySqlGit::$instance->isOverwriteEngine() ? PhpMySqlGit::$instance->getEngine() : $table["ENGINE"],
				"row_format" => PhpMySqlGit::$instance->isOverwriteRowFormat() ? PhpMySqlGit::$instance->getRowFormat() : $table["ROW_FORMAT"],
				"collation"  => PhpMySqlGit::$instance->isOverwriteCharset() ? PhpMySqlGit::$instance->getCollation() : $table["TABLE_COLLATION"],
				"comment"    => $table["TABLE_COMMENT"] ?? '',
			];
		}
	}

	protected function getColumns() {
		foreach ($this->structure["databases"][$this->database]["tables"] as $table => &$structure) {
			$sql = "SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION;";
			foreach ($this->query($sql, [":table" => $table]) as $column) {
				$columnDefinition = [
					"name"           => $column["COLUMN_NAME"],
					"default"        => $this->getColumnDefault($column["COLUMN_DEFAULT"]),
					"nullable"       => $column["IS_NULLABLE"] == "NO" ? false : true,
					"type"           => $column["DATA_TYPE"],
					"column_type"    => $column["COLUMN_TYPE"],
					"length"         => ($column["CHARACTER_MAXIMUM_LENGTH"] ?? $column["NUMERIC_PRECISION"].($column["NUMERIC_SCALE"] != 0 ? ",".$column["NUMERIC_SCALE"] : "")),
					"character_set"  => $column["CHARACTER_SET_NAME"] && PhpMySqlGit::$instance->isOverwriteCharset() ? PhpMySqlGit::$instance->getCharset() : $column["CHARACTER_SET_NAME"],
					"collation"      => $column["COLLATION_NAME"] && PhpMySqlGit::$instance->isOverwriteCharset() ? PhpMySqlGit::$instance->getCollation() : $column["COLLATION_NAME"],
					"auto_increment" => $column["EXTRA"] === "auto_increment",
					"comment"        => $column["COLUMN_COMMENT"],
					"on_update"      => stripos($column["EXTRA"], "on update") !== false,
				];

				$structure['columns'][] = $columnDefinition;
			}
		}
	}

	protected function getIndicies() {
		foreach ($this->structure["databases"][$this->database]["tables"] as $table => &$structure) {
			$sql = "SHOW INDEX FROM ".$table.";";
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
					"name"  => $index["Column_name"],
					"order" => $index["Collation"]
				];

				if (in_array($index["Index_type"], ["BTREE", "HASH"])) {
					$structure[$indexType][$index["Key_name"]]["index_type"] = $index["Index_type"];
				}
			}
			if ($table === "addresses") {

			}
		}
	}

	protected function getForeignKeys() {
		foreach ($this->structure["databases"][$this->database]["tables"] as $table => &$structure) {
			$sql = "SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND NOT ISNULL(REFERENCED_TABLE_NAME) ORDER BY ORDINAL_POSITION;";
			foreach ($this->query($sql, [":table" => $table]) as $foreignKey) {

				$structure["§§foreignKeys"][$foreignKey["CONSTRAINT_NAME"]]["columns"][]            = $foreignKey["COLUMN_NAME"];
				//$structure["§§foreignKeys"][$foreignKey["CONSTRAINT_NAME"]]["referenced_schema"]    = $foreignKey["REFERENCED_TABLE_SCHEMA"];
				$structure["§§foreignKeys"][$foreignKey["CONSTRAINT_NAME"]]["referenced_table"]     = $foreignKey["REFERENCED_TABLE_NAME"];
				$structure["§§foreignKeys"][$foreignKey["CONSTRAINT_NAME"]]["referenced_columns"][] = $foreignKey["REFERENCED_COLUMN_NAME"];

			}

			$sql = "SELECT * FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table;";
			foreach ($this->query($sql, [":table" => $table]) as $foreignKey) {
				$structure["§§foreignKeys"][$foreignKey["CONSTRAINT_NAME"]]["UPDATE_RULE"] = $foreignKey["UPDATE_RULE"];
				$structure["§§foreignKeys"][$foreignKey["CONSTRAINT_NAME"]]["DELETE_RULE"] = $foreignKey["DELETE_RULE"];
			}
		}
	}

	protected function getColumnDefault($default) {
		if ($default === "current_timestamp()") {
			$default = "CURRENT_TIMESTAMP";
		}

		return $default;
	}
}