<?php


namespace PhpMySqlGit\Sql;


use PhpMySqlGit\PhpMySqlGit;

class Column {
	use SQLObject;
	
	protected $previousColumn;
	protected $firstColumn = false;

	/**
	 * @param mixed $previousColumn
	 */
	public function setPreviousColumn($previousColumn): void {
		$this->previousColumn = $previousColumn;
	}

	/**
	 * @param bool $firstColumn
	 */
	public function setFirstColumn(bool $firstColumn): void {
		$this->firstColumn = $firstColumn;
	}

	/**
	 * @return string
	 */
	public function add() {
		return "ADD COLUMN ".$this->columnDefinition();
	}

	/**
	 * @return string
	 */
	public function modify() {
		return "MODIFY COLUMN ".$this->columnDefinition();
	}

	/**
	 * @return string
	 */
	public function drop() {
		return "DROP COLUMN `{$this->name}`";
	}

	/**
	 * @return string
	 */
	public function columnDefinition() {
		$column_type = $this->definition["column_type"];

		$sql =
			"`{$this->name}` {$column_type}";

		if ($this->definition["nullable"] === false) {
			$sql .= " NOT NULL";
		} else {
			$sql .= " NULL";
		}

		if ($this->definition["default"] !== null) {
            $default = $this->defaultValue();
            if ($default === '') {
                $default = '""';
            }
			$sql .= " DEFAULT ".$default;
		}

		if ($this->definition["auto_increment"]) {
			$sql .= " AUTO_INCREMENT";
		}

		if ($this->definition["comment"]) {
			$sql .= " COMMENT ".PhpMySqlGit::$instance->escape($this->definition["comment"]);
		}

		/*if ($this->definition["character_set"]) {
			$sql .= " CHARACTER SET {$this->definition["character_set"]}";
		}*/

		if ($this->definition["collation"] && !PhpMySqlGit::$instance->isIgnoreCharset()) {
			$sql .= " COLLATE {$this->definition["collation"]}";
		}

		if ($this->definition["on_update"]) {
			$sql .= " ON UPDATE CURRENT_TIMESTAMP";
		}

		if ($this->previousColumn) {
			$sql .= " AFTER `{$this->previousColumn}`";
		} else if ($this->firstColumn) {
			$sql .= " FIRST";
		}

		return $sql;
	}

	/**
	 * @return false|mixed|string
	 */
	protected function defaultValue() {
		return $this->definition["default"];

		/*$default = $this->definition["default"];
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

		return $default;*/
	}
}