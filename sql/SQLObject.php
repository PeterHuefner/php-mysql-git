<?php


namespace PhpMySqlGit\SQL;


use PhpMySqlGit\Exception;

trait SQLObject {
	protected $definition = [];
	protected $name = "";

	public function __construct($name, $definition) {
		$this->name       = $name;
		$this->definition = $definition;
	}

	public function getCharacterSetFromCollation($collation) {
		$matches = [];
		if (preg_match('/([^_]+).+/', $collation, $matches) === 1) {
			return $matches[1];
		} else {
			throw new Exception("Character Set could not determined from ".$collation);
		}
	}
}