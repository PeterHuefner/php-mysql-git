<?php


namespace PhpMySqlGit;


trait Configuration {

	protected $fileStructure = [];
	protected $dbStructure = [];

	protected $statements = [];

	protected $database;

	function __construct($dbStructure, $fileStructure) {
		$this->dbStructure   = $dbStructure;
		$this->fileStructure = $fileStructure;
		$this->database      = PhpMySqlGit::$instance->getDbname();
	}

	/**
	 * @return array
	 */
	public function getStatements(): array {
		return $this->statements;
	}

	public function configure() {

	}
}