<?php


namespace PhpMySqlGit\SQL;


class Database {

	public $name;
	public $charset = "utf8mb4";
	public $collation = "utf8mb4_unicode_ci";


	public function create() {
		return "CREATE DATABASE IF NOT EXISTS `$this->name` CHARACTER SET $this->charset COLLATE $this->collation;";
	}

	public function alter() {
		return "ALTER DATABASE `$this->name` CHARACTER SET $this->charset COLLATE $this->collation;";
	}
}