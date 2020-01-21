<?php


namespace PhpMySqlGit\SQL;


class Table {
	use SQLObject;


	public function create() {
		$sql = "CREATE TABLE `$this->name` (";

		$cols = [];
		$previousColumn = null;
		foreach ($this->definition["columns"] as $columnIndex => $columnDefinition) {
			$column = new Column($columnDefinition["name"], $columnDefinition);
			$column->setPreviousColumn($previousColumn);

			$cols[] = $column->add();

			$previousColumn = $columnDefinition["name"];
		}

		$sql .= implode(", ", $cols);

		$sql .= ") ".
			"ENGINE = {$this->definition["engine"]} ".
			"ROW_FORMAT = {$this->definition["row_format"]} ".
			"CHARACTER SET = {$this->getCharacterSetFromCollation($this->definition["collation"])} ".
			"COLLATE = {$this->definition["collation"]} ".
			"COMMENT = ".\PhpMySqlGit\PhpMySqlGit::$instance->escape($this->definition["comment"]).
			";";

		return $sql;
	}

	public function drop() {
		return "DROP TABLE `$this->name`;";
	}
}