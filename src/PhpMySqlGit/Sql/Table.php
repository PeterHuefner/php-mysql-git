<?php


namespace PhpMysSqlGit\Sql;


use PhpMySqlGit\Configure\Columns;
use PhpMySqlGit\PhpMySqlGit;

class Table {
	use SQLObject;


	public function create() {
		$sql = "CREATE TABLE `$this->name` (\n\t";

		$cols = [];
		$previousColumn = null;
		foreach ($this->definition["columns"] as $columnIndex => $columnDefinition) {
			$column = new Column($columnDefinition["name"], $columnDefinition);
			$cols[] = $column->columnDefinition();
		}

		$sql .= implode(",\n\t", $cols);

		if ($keyStatements = $this->localKeyDefinition()) {
			$sql .= ",\n\t".implode(",\n\t", $keyStatements);
		}

		$sql .= "\n) ".
			$this->tableOptions();

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
			$statement = "ALTER TABLE `{$this->name}` ".$this->tableOptions().";\n";
		}

		if ($cols || $options) {
			$statement .= "ALTER TABLE `{$this->name}` $cols;";
		}

		return $statement;
	}

	public function engine() {
		return PhpMySqlGit::$instance->isIgnoreEngine() ? "" : "ENGINE = {$this->definition["engine"]}";
	}

	public function rowFormat() {
		return PhpMySqlGit::$instance->isIgnoreRowFormat() ? "" : "ROW_FORMAT = {$this->definition["row_format"]}";
	}

	public function characterSet() {
		return PhpMySqlGit::$instance->isIgnoreCharset() ? "" : "CHARACTER SET = {$this->getCharacterSetFromCollation($this->definition["collation"])}";
	}

	public function collation() {
		return PhpMySqlGit::$instance->isIgnoreCharset() ? "" : "COLLATE = {$this->definition["collation"]}";
	}

	public function comment() {
		return ($this->definition["comment"] ? "COMMENT = ".PhpMySqlGit::$instance->escape($this->definition["comment"]) : "");
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