<?php

namespace PhpMySqlGit;

spl_autoload_register(function ($objectToInlucde) {
	$parts = explode("\\", $objectToInlucde);
	end($parts);
	$class = current($parts);

	$file = __DIR__.DIRECTORY_SEPARATOR.$class.".php";

	$dir = $namespace = $parts[1];
	if ($dir) {
		$dir = strtolower($dir[0]).substr($dir, 1);
		if (!is_dir(__DIR__.DIRECTORY_SEPARATOR.$dir)) {
			$dir = null;
		}
	}

	if (file_exists($file)) {
		require_once($file);
	} else {
		$file = __DIR__.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.$class.".php";
		if (file_exists($file)) {
			require_once($file);
		}
	}
});

class PhpMySqlGit {

	/**
	 * @var PhpMySqlGit
	 */
	public static $instance;

	/**
	 * a collection of Tables, Columns and Keys that would changed by the execution of generated SQL-Statements
	 *
	 * @var array
	 */
	public static $changedObjects = [];

	#region internal properties

	/**
	 * @var SqlConnection
	 */
	protected $database;

	/**
	 * @var array
	 */
	protected $dbStructure = [];

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

	/**
	 * the mod to create files used in chmod
	 *
	 * @var string
	 */
	protected $fileMod = "0664";

	/**
	 * the mod to create dirs used in chmod
	 *
	 * @var string
	 */
	protected $dirMod = "0775";

	/**
	 * ignores the charset stored in the database when saving the structure.
	 * the given overwrite charset is used instead. a good way to ensure that databases have the same charset.
	 *
	 * @var bool
	 */
	protected $overwriteCharset = false;

	/**
	 * ignores the engine stored in the database when saving the structure.
	 * the given overwrite engine is used instead. a good way to ensure that databases have the same engine.
	 *
	 * @var bool
	 */
	protected $overwriteEngine = false;

	/**
	 * ignores the row format stored in the database when saving the structure.
	 * the given overwrite row format is used instead. a good way to ensure that databases have the same row format.
	 *
	 * @var bool
	 */
	protected $overwriteRowFormat = false;

	/**
	 * Defaults to true.
	 * If true the database name is not saved in structure and has to be given when constructing the class within the
	 * PDO connection string or right before any database transaction with {@link self::setDbname()} setDbname
	 *
	 * @var bool
	 */
	protected $saveNoDbName = true;

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
		if ($this->database) {
			$this->database->setDatabase($this->dbname);
		}
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

	/**
	 * @param mixed $overwriteCharset
	 */
	public function setOverwriteCharset($overwriteCharset): void {
		$this->overwriteCharset = $overwriteCharset;
	}

	/**
	 * @return mixed
	 */
	public function isOverwriteCharset() {
		return $this->overwriteCharset;
	}

	/**
	 * @param mixed $overwriteEngine
	 */
	public function setOverwriteEngine($overwriteEngine): void {
		$this->overwriteEngine = $overwriteEngine;
	}

	/**
	 * @return mixed
	 */
	public function isOverwriteEngine() {
		return $this->overwriteEngine;
	}

	/**
	 * @param mixed $overwriteRowFormat
	 */
	public function setOverwriteRowFormat($overwriteRowFormat): void {
		$this->overwriteRowFormat = $overwriteRowFormat;
	}

	/**
	 * @return mixed
	 */
	public function isOverwriteRowFormat() {
		return $this->overwriteRowFormat;
	}

	/**
	 * @param bool $saveNoDbName
	 */
	public function setSaveNoDbName(bool $saveNoDbName): void {
		$this->saveNoDbName = $saveNoDbName;
	}

	/**
	 * @return bool
	 */
	public function isSaveNoDbName(): bool {
		return $this->saveNoDbName;
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
			$structure = new Structure\Structure($structureSource);
			$structure->readStructure($this->dbname);
			$fileStructure = $structure->getStructure();
		} else {
			$fileStructure = $structureSource;
		}

		/*echo("file structure\n");
		var_dump($fileStructure);
		echo("\n\n");*/

		$statements = [];

		$database = new Configure\Database($this->dbStructure, $fileStructure);
		$database->configure();
		$statements = array_merge($statements, $database->getStatements());

		//var_dump($statements);
		//var_dump($database->getCommentedOutStatements());
		//var_dump($this->dbStructure);

		return array_merge($statements, $database->getCommentedOutStatements());
	}

	public function saveStructure($saveToDir = null, $tables = []) {
		$this->dbStructure = $this->database->readDbStructure();

		if ($saveToDir) {
			$structure = new Structure\Structure($saveToDir);
			$structure->saveStructure($this->dbStructure, $tables);
		}

		return $this->dbStructure;
	}

	public function saveData($table = null, $saveToFile = null) {

	}

	public function escape($string) {
		return $this->database->escape($string);
	}
}