<?php


namespace PhpMySqlGit\Configure;


use PhpMySqlGit\PhpMySqlGit;

class DefaultData {

	protected $data = [];
	protected $database;
	protected $foreignKeyChecks = true;

	public function __construct(&$data, $database) {
		$this->data = &$data;
		$this->database = $database;
	}

	/**
	 * @param bool $foreignKeyChecks
	 */
	public function setForeignKeyChecks(bool $foreignKeyChecks): void {
		$this->foreignKeyChecks = $foreignKeyChecks;
	}

	public function getStatements() {
		$statements = [];

		foreach ($this->data as $table => $rows) {
			if ($rows) {
				$statements[] =
					"INSERT INTO `{$this->database}`.`{$table}` (".$this->getColNameList($rows).") ".
					"VALUES ".$this->getColValueList($rows)." ".
					"ON DUPLICATE KEY UPDATE ".$this->getColUpdateList($rows).";";
			}
		}

		if ($statements) {
			$preStatements = $afterStatements = [];

			$preStatements[] = "USE `{$this->database}`;";

			$preStatements[] = "SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=".($this->foreignKeyChecks ? "1" : "0").";";
			$afterStatements[] = "SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;";

			$statements = array_merge($preStatements, $statements, $afterStatements);
		}

		return $statements;
	}

	protected function getColNameList(&$rows) {
		return "`".implode("`, `", array_keys($rows[0]))."`";
	}

	protected function getColValueList(&$rows) {
		$list = "";
		$listComma = "";
		foreach ($rows as &$row) {
			$list .= $listComma."(";
			$comma = "";
			foreach ($row as $columnName => &$value) {
				$list .= $comma.$this->escape($value);
				$comma = ", ";
			}
			$list .= ")";
			$listComma = ", ";
		}

		return $list;
	}

	protected function getColUpdateList(&$rows) {
		$list = "";
		$comma = "";
		foreach ($rows[0] as $columnName => &$value) {
			$list .= $comma."`{$columnName}` = VALUE(`{$columnName}`)";
			$comma = ", ";
		}

		return $list;
	}

	protected function escape(&$value) {
		if ($value === null) {
			return "null";
		} else {
			return PhpMySqlGit::$instance->escape($value);
		}
	}
}