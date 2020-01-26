<?php


namespace PhpMySqlGit;


class Database {
	use Configuration;

	public function configure() {
		$this->configureDatabase();

		$tables = new Tables($this->dbStructure, $this->fileStructure);
		$tables->configure();
		$this->statements = array_merge($this->statements, $tables->getStatements());
		$this->commentedOutStatements = array_merge($this->commentedOutStatements, $tables->getCommentedOutStatements());

		/*$columns = new Columns($this->dbStructure, $this->fileStructure);
		$columns->configure();
		$this->statements = array_merge($this->statements, $columns->getStatements());
		$this->commentedOutStatements = array_merge($this->commentedOutStatements, $columns->getCommentedOutStatements());*/
	}

	protected function configureDatabase() {
		if (!empty($this->dbStructure["databases"][$this->database])) {
			foreach ($this->dbStructure["databases"][$this->database] as $config => $value) {
				if ($config === "tables") {
					continue;
				} elseif(isset($this->fileStructure["databases"])
					&& array_key_exists($config, $this->fileStructure["databases"][$this->database])
					&& strtolower($value) !== strtolower($this->fileStructure["databases"][$this->database][$config])
				) {
					$this->statements[] = $this->getDb()->alter();
					break;
				}
			}

		} else {
			$this->statements[] = $this->getDb()->create();
		}
	}

	protected function getDb() {
		$db            = new \PhpMySqlGit\SQL\Database();
		$db->name      = PhpMySqlGit::$instance->getDbname();
		$db->charset   = PhpMySqlGit::$instance->getCharset();
		$db->collation = PhpMySqlGit::$instance->getCollation();

		return $db;
	}
}