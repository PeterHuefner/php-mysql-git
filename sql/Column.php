<?php


namespace PhpMySqlGit\SQL;


class Column {
	use SQLObject;

	protected $previousColumn;

	/**
	 * @param mixed $previousColumn
	 */
	public function setPreviousColumn($previousColumn): void {
		$this->previousColumn = $previousColumn;
	}

	public function add() {
		return "ADD ".$this->columnDefinition();
	}

	protected function columnDefinition() {
		$column_type = strtoupper($this->definition["column_type"]);

		$sql =
			"`{$this->name}` {$column_type}";

		if ($this->definition["nullable"] === false) {
			$sql .= " NOT NULL";
		} else {
			$sql .= " NULL";
		}

		if ($this->definition["default"]) {
			$sql .= " DEFAULT ".$this->defaultValue();
		}

		if ($this->definition["auto_increment"]) {
			$sql .= " AUTO_INCREMENT";
		}

		if ($this->definition["comment"]) {
			$sql .= " COMMENT ".\PhpMySqlGit\PhpMySqlGit::$instance->escape($this->definition["comment"]);
		}

		if ($this->definition["character_set"]) {
			$sql .= " CHARACTER SET {$this->definition["character_set"]}";
		}

		if ($this->definition["collation"]) {
			$sql .= " COLLATE {$this->definition["collation"]}";
		}

		if ($this->definition["on_update"]) {
			$sql .= " ON UPDATE CURRENT_TIMESTAMP";
		}

		if ($this->previousColumn) {
			$sql .= " AFTER `{$this->previousColumn}`";
		}

		return $sql;
	}

	protected function defaultValue() {
		$default = $this->definition["default"];
		if ($default) {
			switch (strtoupper($default)) {
				case 'CURRENT_TIMESTAMP' :
				case 'NULL' :
					// left unchanged
					break;
				default :
					$default = \PhpMySqlGit\PhpMySqlGit::$instance->escape($default);
			}
		}

		return $default;
	}
}