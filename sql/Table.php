<?php


namespace PhpMysSqlGit\Sql;


use PhpMySqlGit\Configure\Columns;

class Table {
	use SQLObject;


	public function create() {
		$sql = "CREATE TABLE `$this->name` (";

		$cols = [];
		$previousColumn = null;
		foreach ($this->definition["columns"] as $columnIndex => $columnDefinition) {
			$column = new Column($columnDefinition["name"], $columnDefinition);
			$cols[] = $column->columnDefinition();
		}

		$sql .= implode(", ", $cols);

		if ($keyStatements = $this->localKeyDefinition()) {
			$sql .= ", ".implode(", ", $keyStatements);
		}

		$sql .= ") ".
			$this->tableOptions().
			";";

		return $sql;
	}

	public function drop() {
		return "DROP TABLE `$this->name`;";
	}

	public function alter(array $columnStatements, $setTableOptions = false) {
		$statement = "";
		$cols = "";
		$options = "";

		if ($columnStatements) {
			$cols = "\n\t".implode(",\n\t", $columnStatements);
		}

		if ($setTableOptions) {
			$options = $this->tableOptions();
		}

		if ($cols || $options) {
			$statement = "ALTER TABLE `{$this->name}` $cols $options ;";
		}

		return $statement;
	}

	public function engine() {
		return "ENGINE = {$this->definition["engine"]}";
	}

	public function rowFormat() {
		return "ROW_FORMAT = {$this->definition["row_format"]}";
	}

	public function characterSet() {
		return "CHARACTER SET = {$this->getCharacterSetFromCollation($this->definition["collation"])}";
	}

	public function collation() {
		return "COLLATE = {$this->definition["collation"]}";
	}

	public function comment() {
		return "COMMENT = ".\PhpMySqlGit\PhpMySqlGit::$instance->escape($this->definition["comment"]);
	}

	public function tableOptions() {
		return
			$this->engine()." ".
			$this->rowFormat()." ".
			$this->characterSet()." ".
			$this->collation()." ".
			$this->comment();
	}

	public function localKeyDefinition($withAdd = false) {
		$statements = [];

		foreach (Columns::KEY_TYPES as $keyType => $handlers) {
			if (isset($this->definition[$keyType])) {
				foreach ($this->definition[$keyType] as $keyName => &$keyDefinition) {
					$keyMaker = new Key($keyName, $keyDefinition);
					$statements[] = ($withAdd ? "ADD " : "").call_user_func([$keyMaker, $handlers['definition']]);
				}
			}
		}

		return $statements;
	}
}