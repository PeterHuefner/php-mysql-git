<?php


namespace PhpMySqlGit;


trait Configuration {

	protected $fileStructure = [];
	protected $dbStructure = [];

	protected $statements = [];
	protected $commentedOutStatements = [];

	protected $database;

	function __construct(&$dbStructure, &$fileStructure) {
		$this->dbStructure   = &$dbStructure;
		$this->fileStructure = &$fileStructure;
		$this->database      = PhpMySqlGit::$instance->getDbname();
	}

	/**
	 * @return array
	 */
	public function getStatements(): array {
		return $this->statements;
	}

	/**
	 * @return array
	 */
	public function getCommentedOutStatements(): array {
		return $this->commentedOutStatements;
	}

	public function configure() {

	}
}