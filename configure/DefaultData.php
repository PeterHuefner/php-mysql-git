<?php


namespace PhpMySqlGit\Configure;


use PhpMySqlGit\PhpMySqlGit;

class DefaultData {

	protected $data = [];
	protected $database;

	public function __construct(&$data, $database) {
		$this->data = &$data;
		$this->database = $database;
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