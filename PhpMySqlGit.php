<?php

namespace PhpMySqlGit;

spl_autoload_register(function ($objectToInlucde) {
	$parts = explode("\\", $objectToInlucde);
	end($parts);
	$class = current($parts);

	$file = __DIR__.DIRECTORY_SEPARATOR.$class.".php";

	if (file_exists($file)) {
		require_once($file);
	} else {

		if ($parts[1] == "SQL") {
			require_once(__DIR__.DIRECTORY_SEPARATOR.'sql'.DIRECTORY_SEPARATOR.$class.".php");
		} else {
			switch ($class) {
				case 'Exception' :
					require_once(__DIR__.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.$class.".php");
					break;
				case 'Database' :
				case 'Tables' :
				case 'Columns' :
				case 'Configuration' :
					require_once(__DIR__.DIRECTORY_SEPARATOR.'configure'.DIRECTORY_SEPARATOR.$class.".php");
					break;
				case 'Structure' :
					require_once(__DIR__.DIRECTORY_SEPARATOR.'structure'.DIRECTORY_SEPARATOR.$class.".php");
					break;
			}
		}

	}
});

class PhpMySqlGit {

	/**
	 * @var PhpMySqlGit
	 */
	public static $instance;

	#region internal properties

	/**
	 * @var SqlConnection
	 */
	protected $database;

	/**
	 * @var array
	 */
	protected array $dbStructure = [];

	#endregion
	#region setter and getter
	/**
	 * @var string
	 */
	protected $dbname;

	/**
	 * Default Charset for all tables and columns
	 *
	 * @var string
	 */
	protected $charset = "utf8mb4";

	/**
	 * Default Collation for all tables and columns
	 *
	 * @var string
	 */
	protected $collation = "utf8mb4_unicode_ci";

	/**
	 * Default table engine
	 *
	 * @var string
	 */
	protected $engine = "InnoDB";

	/**
	 * Default row format for all tables
	 *
	 * @var string
	 */
	protected $rowFormat = "DYNAMIC";

	/**
	 * @var bool
	 */
	protected $skipForeignKeyChecks = false;

	protected $fileMod = "0664";

	protected $dirMod = "0775";

	/**
	 * @return string
	 */
	public function getDbname() {
		return $this->dbname;
	}

	/**
	 * @param string $dbname
	 */
	public function setDbname($dbname): void {
		$this->dbname = $dbname;
	}

	/**
	 * @return string
	 */
	public function getCharset(): string {
		return $this->charset;
	}

	/**
	 * @param string $charset
	 */
	public function setCharset(string $charset): void {
		$this->charset = $charset;
	}

	/**
	 * @return string
	 */
	public function getCollation(): string {
		return $this->collation;
	}

	/**
	 * @param string $collation
	 */
	public function setCollation(string $collation): void {
		$this->collation = $collation;
	}

	/**
	 * @return string
	 */
	public function getEngine(): string {
		return $this->engine;
	}

	/**
	 * @param string $engine
	 */
	public function setEngine(string $engine): void {
		$this->engine = $engine;
	}

	/**
	 * @return string
	 */
	public function getRowFormat(): string {
		return $this->rowFormat;
	}

	/**
	 * @param string $rowFormat
	 */
	public function setRowFormat(string $rowFormat): void {
		$this->rowFormat = $rowFormat;
	}

	/**
	 * @param bool $skipForeignKeyChecks
	 */
	public function setSkipForeignKeyChecks(bool $skipForeignKeyChecks): void {
		$this->skipForeignKeyChecks = $skipForeignKeyChecks;
	}

	/**
	 * @return bool
	 */
	public function isSkipForeignKeyChecks(): bool {
		return $this->skipForeignKeyChecks;
	}

	/**
	 * @param string $fileMod
	 */
	public function setFileMod(string $fileMod): void {
		$this->fileMod = $fileMod;
	}

	/**
	 * @return string
	 */
	public function getFileMod(): string {
		return $this->fileMod;
	}

	/**
	 * @param string $dirMod
	 */
	public function setDirMod(string $dirMod): void {
		$this->dirMod = $dirMod;
	}

	/**
	 * @return string
	 */
	public function getDirMod(): string {
		return $this->dirMod;
	}

	#endregion

	function __construct($dbConnection) {
		$matches = [];
		if (preg_match('/dbname=(.+)(;|$)/', $dbConnection['connectionString'], $matches) === 1) {
			$this->dbname = $matches[1];
		}

		$this->database = new SqlConnection($dbConnection['connectionString'], $dbConnection['username'] ?? null, $dbConnection['password'] ?? null, $this->dbname);
		self::$instance = $this;
	}

	protected function prepare() {
		if (empty($this->dbname)) {
			throw new Exception("no database provided");
		}

		self::$instance = $this;
	}

	public function configureDatabase($structureSource) {
		$this->dbStructure = $this->database->readDbStructure();
		if (is_string($structureSource)) {
			$structure = new Structure($structureSource);
			$structure->readStructure($this->dbname);
			$fileStructure = $structure->getStructure();
		} else {
			$fileStructure = $structureSource;
		}

		echo("file structure\n");
		var_dump($fileStructure);
		echo("\n\n");

		$statements = [];

		$database = new Database($this->dbStructure, $fileStructure);
		$database->configure();
		$statements = array_merge($statements, $database->getStatements());

		var_dump($statements);
		var_dump($this->dbStructure);
	}

	public function saveStructure($saveToDir = null, $tables = []) {
		$this->dbStructure = $this->database->readDbStructure();

		if ($saveToDir && is_dir($saveToDir)) {
			$structure = new Structure($saveToDir);
			$structure->saveStructure($this->dbStructure, $tables);
		} else {
			return $this->dbStructure;
		}
	}

	public function saveData($table = null, $saveToFile = null) {

	}
}