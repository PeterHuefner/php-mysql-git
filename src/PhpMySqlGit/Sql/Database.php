<?php


namespace PhpMySqlGit\Sql;
use PhpMySqlGit\PhpMySqlGit;

class Database {

	public $name;
	public $charset = "utf8mb4";
	public $collation = "utf8mb4_unicode_ci";


	public function create() {
		$charsetSql = "";

		if (!PhpMySqlGit::$instance->isIgnoreCharset()) {
			$charsetSql = " CHARACTER SET $this->charset COLLATE $this->collation";
		}

		return "CREATE DATABASE IF NOT EXISTS `$this->name`{$charsetSql};";
	}

	public function alter() {
		return "ALTER DATABASE `$this->name` CHARACTER SET $this->charset COLLATE $this->collation;";
	}
}