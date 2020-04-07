<?php

namespace PhpMySqlGit;

use PhpMySqlGit\Core\Exception;

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
	 * If true the foreign key checks will be enabled before the default data statements.
	 * When false they will be disabled.
	 * Afterwards the previous state will be recovered.
	 *
	 * @var bool
	 */
	protected $foreignKeyChecksForData = true;

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

	/**
	 * @return bool
	 */
	public function isForeignKeyChecksForData(): bool {
		return $this->foreignKeyChecksForData;
	}

	/**
	 * @param bool $foreignKeyChecksForData
	 */
	public function setForeignKeyChecksForData(bool $foreignKeyChecksForData): void {
		$this->foreignKeyChecksForData = $foreignKeyChecksForData;
	}

	#endregion

	/**
	 * PhpMySqlGit constructor.
	 * @param array|\PDO $dbConnection
	 * @throws Exception
	 */
	function __construct($dbConnection) {
		if (is_array($dbConnection) && !empty($dbConnection['connectionString'])) {
			$matches = [];
			if (preg_match('/dbname=(.+)(;|$)/', $dbConnection['connectionString'], $matches) === 1) {
				$this->dbname = $matches[1];
			}

			$this->database = new SqlConnection($dbConnection['connectionString'], $dbConnection['username'] ?? null, $dbConnection['password'] ?? null, $this->dbname);
		} else if ($dbConnection instanceof \PDO) {
			$this->database = new SqlConnection($dbConnection);
		} else {
			throw new Exception('invalid arguments passed. $dbConnection must be an array with key "connectionString" or a PDO instance');
		}

		self::$instance = $this;
	}

	protected function prepare() {
		if (empty($this->dbname)) {
			throw new Exception("no database provided");
		}

		self::$instance = $this;
	}

	/**
	 * Returns an array of SQL-Statements that will synchronise the current database with the stored structure.
	 * The $structureSource can be a string to a directory containing the structure or the array with the structure data directly.
	 *
	 * @param string|array $structureSource
	 * @return array
	 * @throws Core\Exception
	 */
	public function configureDatabase($structureSource) {
		$this->dbStructure = $this->database->readDbStructure();
		if (is_string($structureSource)) {
			$structure = new Structure\Structure($structureSource);
			$structure->readStructure($this->dbname);
			$fileStructure = $structure->getStructure();
		} else {
			$fileStructure = $structureSource;
		}

		$statements = [];

		$database = new Configure\Database($this->dbStructure, $fileStructure);
		$database->configure();
		$statements = array_merge($statements, $database->getStatements());

		return array_merge($statements, $database->getCommentedOutStatements());
	}

	/**
	 * Returns an array of SQL-Statements that will INSERT/UPDATE the default data stored in the structure.
	 * The $structureSource can be a string to a directory containing the structure/default data or the array with the data directly.
	 *
	 * @param string|array $structureSource
	 * @return array
	 */
	public function configureData($structureSource) {
		if (is_string($structureSource)) {
			$structure = new Structure\Structure($structureSource);
			$data = $structure->readData($this->dbname);
		} else {
			$data = $structureSource;
		}

		$defaultData = new Configure\DefaultData($data, $this->dbname);
		$defaultData->setForeignKeyChecks($this->foreignKeyChecksForData);

		return $defaultData->getStatements();
	}

	public function saveStructure($saveToDir = null, $tables = []) {
		$this->dbStructure = $this->database->readDbStructure();

		if ($saveToDir) {
			$structure = new Structure\Structure($saveToDir);
			$structure->saveStructure($this->dbStructure, $tables);
		}

		return $this->dbStructure;
	}

	public function saveData($saveToDir = null, $tables = [], $skipColumns = []) {
		$data = $this->database->getData($tables);

		if ($skipColumns) {
			foreach ($data as $table => $rows) {
				$columsToDelete = [];

				foreach ($skipColumns as $key => $skipColumnData) {
					if (is_int($key)) {
						$columsToDelete[] = $skipColumnData;
					} elseif (is_string($key) && strtolower($key) == strtolower($table)) {
						if (!is_array($skipColumnData)) {
							$skipColumnData = [$skipColumnData];
						}

						$columsToDelete = array_merge($columsToDelete, $skipColumnData);
					}

					foreach ($rows as $index => $row) {
						foreach ($columsToDelete as $column) {
							unset($data[$table][$index][$column]);
						}
					}
				}
			}
		}

		if ($saveToDir) {
			$structure = new Structure\Structure($saveToDir);
			$structure->saveData($data, $this->dbname);
		}

		return $data;
	}

	public function escape($string) {
		return $this->database->escape($string);
	}
}