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
	 * ignores engine completly, so no alter or create statement will contain the specification of engine
	 *
	 * @var bool
	 */
	protected $ignoreEngine = false;

	/**
	 * ignores row format completely, so no alter or create statement will contain the specification of row format
	 *
	 * @var bool
	 */
	protected $ignoreRowFormat = false;

	/**
	 * ignores charset completely, alter or create statement (for database, table and column) will contain the specification of charset
	 *
	 * @var bool
	 */
	protected $ignoreCharset = false;

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
	 * Gets the current used datebase name
	 *
	 * @return string
	 */
	public function getDbname() {
		return $this->dbname;
	}

	/**
	 * Sets the name of the database to use
	 * if not explicit called, it can be provided also in the connectionString of the class constructor
	 *
	 * @param string $dbname
	 */
	public function setDbname($dbname): void {
		$this->dbname = $dbname;
		if ($this->database) {
			$this->database->setDatabase($this->dbname);
		}
	}

	/**
	 * Gets the Default Charset for all tables and columns
	 *
	 * @return string
	 */
	public function getCharset(): string {
		return $this->charset;
	}

	/**
	 * Sets the Default Charset for all tables and columns
	 *
	 * @param string $charset
	 */
	public function setCharset(string $charset): void {
		$this->charset = $charset;
	}

	/**
	 * Gets the Default Collation for all tables and columns
	 *
	 * @return string
	 */
	public function getCollation(): string {
		return $this->collation;
	}

	/**
	 * Sets the Default Collation for all tables and columns
	 *
	 * @param string $collation
	 */
	public function setCollation(string $collation): void {
		$this->collation = $collation;
	}

	/**
	 * Gets the default engine to use in create statements
	 *
	 * @return string
	 */
	public function getEngine(): string {
		return $this->engine;
	}

	/**
	 * Sets the default engine to use
	 *
	 * @param string $engine
	 */
	public function setEngine(string $engine): void {
		$this->engine = $engine;
	}

	/**
	 * Gets the default row format
	 *
	 * @return string
	 */
	public function getRowFormat(): string {
		return $this->rowFormat;
	}

	/**
	 * sets the default row format
	 *
	 * @param string $rowFormat
	 */
	public function setRowFormat(string $rowFormat): void {
		$this->rowFormat = $rowFormat;
	}

	/**
	 * sets if foreign key checks should be done
	 * When set to true, the configureDatabase-function will check foreign keys while altering the database.
	 * Every affected foreign key by a alter or create statement will be dropped (a drop statement is created automatically).
	 * At the end of statements, the dropped foreign keys are recreated.
	 *
	 * This Option does not set the FOREIGN_KEY_CHECKS-Server-Variable
	 *
	 * @param bool $skipForeignKeyChecks
	 */
	public function setSkipForeignKeyChecks(bool $skipForeignKeyChecks): void {
		$this->skipForeignKeyChecks = $skipForeignKeyChecks;
	}

	/**
	 * Returns true if foreign key checks are on
	 *
	 * @return bool
	 */
	public function isSkipForeignKeyChecks(): bool {
		return $this->skipForeignKeyChecks;
	}

	/**
	 * Set the numeric file permission for created files in the structure dir
	 *
	 * @param string $fileMod
	 */
	public function setFileMod(string $fileMod): void {
		$this->fileMod = $fileMod;
	}

	/**
	 * Get the numeric file permission for created files
	 *
	 * @return string
	 */
	public function getFileMod(): string {
		return $this->fileMod;
	}

	/**
	 * Sets the numeric permission for directories in the strcuture dir
	 *
	 * @param string $dirMod
	 */
	public function setDirMod(string $dirMod): void {
		$this->dirMod = $dirMod;
	}

	/**
	 * gets the numeric permission for directories in the strcuture dir
	 *
	 * @return string
	 */
	public function getDirMod(): string {
		return $this->dirMod;
	}

	/**
	 * Enables or Disables the overwriting of Charset.
	 * When enabled the charset defined in {@link PhpMySqlGit::setCharset()} and {@link PhpMySqlGit::setCollation()} will be saved and configured, regardless of which charset is defined in database or saved structure.
	 * This is a good way to ensure that all used databses have the same charset and collation.
	 *
	 * @param bool $overwriteCharset
	 */
	public function setOverwriteCharset(bool $overwriteCharset): void {
		$this->overwriteCharset = $overwriteCharset;
	}

	/**
	 * Returns true if charset will be overwritten to the previous defined
	 *
	 * @return bool
	 */
	public function isOverwriteCharset() {
		return $this->overwriteCharset;
	}

	/**
	 * Enables or Disables the overwriting of Engine.
	 * When enabled the engine defined in {@link PhpMySqlGit::setEngine()} will be saved and configured, regardless of which engine is defined in database or saved structure.
	 *
	 * @param bool $overwriteEngine
	 */
	public function setOverwriteEngine(bool $overwriteEngine): void {
		$this->overwriteEngine = $overwriteEngine;
	}

	/**
	 * Returns true if engine overwriting is enabled
	 *
	 * @return bool
	 */
	public function isOverwriteEngine() {
		return $this->overwriteEngine;
	}

	/**
	 * Enables or Disables the overwriting of row format.
	 * When enabled the row format defined in {@link PhpMySqlGit::setRowFormat()} will be saved and configured, regardless of which row format is defined in database or saved structure.
	 *
	 * @param bool $overwriteRowFormat
	 */
	public function setOverwriteRowFormat(bool $overwriteRowFormat): void {
		$this->overwriteRowFormat = $overwriteRowFormat;
	}

	/**
	 * Returns true if overwriting of row format is enabled
	 *
	 * @return mixed
	 */
	public function isOverwriteRowFormat() {
		return $this->overwriteRowFormat;
	}

	/**
	 * Enables or Disables the storing of the name of database in the saved structure.
	 * When enabled the name of database will not be stored in the stucture files.
	 *
	 * So you can save the structure based on a database called 'client_dev'. With that saved structure, you can configure another database called 'client_live'.
	 * You only have to call setDbName to set the current name.
	 *
	 * @param bool $saveNoDbName
	 */
	public function setSaveNoDbName(bool $saveNoDbName): void {
		$this->saveNoDbName = $saveNoDbName;
	}

	/**
	 * Returns true if the database name will not be stored in saved structure.
	 *
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
	 * Sets FOREIGN_KEY_CHECKS according to true or false when {@link PhpMySqlGit::configureData()} configureData.
	 * Defaults to true.
	 *
	 * @param bool $foreignKeyChecksForData
	 */
	public function setForeignKeyChecksForData(bool $foreignKeyChecksForData): void {
		$this->foreignKeyChecksForData = $foreignKeyChecksForData;
	}

	/**
	 * When true the charset is not checkd and changed while {@link PhpMySqlGit::configureDatabase()} configureDatabase
	 *
	 * @param bool $ignoreCharset
	 */
	public function setIgnoreCharset(bool $ignoreCharset): void {
		$this->ignoreCharset = $ignoreCharset;
	}

	/**
	 * Returns true if charset is not checked during {@link PhpMySqlGit::configureDatabase()} configureDatabase
	 *
	 * @return bool
	 */
	public function isIgnoreCharset(): bool {
		return $this->ignoreCharset;
	}

	/**
	 * When true the engine is not checkd and changed while {@link PhpMySqlGit::configureDatabase()} configureDatabase
	 *
	 * @param bool $ignoreEngine
	 */
	public function setIgnoreEngine(bool $ignoreEngine): void {
		$this->ignoreEngine = $ignoreEngine;
	}

	/**
	 * Returns true if engine is not checked during {@link PhpMySqlGit::configureDatabase()} configureDatabase
	 *
	 * @return bool
	 */
	public function isIgnoreEngine(): bool {
		return $this->ignoreEngine;
	}

	/**
	 * When true the row format is not checkd and changed while {@link PhpMySqlGit::configureDatabase()} configureDatabase
	 *
	 * @param bool $ignoreRowFormat
	 */
	public function setIgnoreRowFormat(bool $ignoreRowFormat): void {
		$this->ignoreRowFormat = $ignoreRowFormat;
	}

	/**
	 * Returns true if row format is not checked during {@link PhpMySqlGit::configureDatabase()} configureDatabase
	 *
	 * @return bool
	 */
	public function isIgnoreRowFormat(): bool {
		return $this->ignoreRowFormat;
	}

	#endregion

	/**
	 *
	 * provide a PDO Instance which should be used for database communication or
	 * provide an array with at least the key 'connectionString' which you would provide to PDO.
	 *
	 * username and password in the array are optional.
	 *
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
		$this->database->setUseOverwrites(false);
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

	/**
	 * Returns and stores the database structure.
	 * If $saveToDir is a string it is used as path where the structure is stored in php-files.
	 * If $tables is an array with entries, only the given table names are stored.
	 *
	 * @param null|string $saveToDir
	 * @param array $tables
	 * @return array
	 * @throws Exception
	 */
	public function saveStructure($saveToDir = null, $tables = []) {
		$this->database->setUseOverwrites(true);
		$this->dbStructure = $this->database->readDbStructure();

		if ($saveToDir) {
			$structure = new Structure\Structure($saveToDir);
			$structure->saveStructure($this->dbStructure, $tables);
		}

		return $this->dbStructure;
	}

	/**
	 * Returns and stores the data of database.
	 * if $saveToDir is a string it is used as path where the data is stored in php-files.
	 * If $tables is an array, only data of that table names is stored.
	 *
	 * If $skipColumns is an array, the names of that columns are used to not store the data of that columns.
	 * If the key of an entry is numeric, the column is blacklisted for all tables.
	 * If the key is a string, it is treated as a table name and the specified columns in that associative array are only blacklisted for that column.
	 * For example:
	 *
	 * [
	 * 'name', // for all tables blacklisted
	 * 'language' => ['language_id'], // blacklisted only fot the table 'language'
	 * 'category' => 'last_update' // blacklisted only fot the table 'category'
	 * ]
	 *
	 * if $indexFiles is true, then a numeric increment is put in the filename. This is to ensure, that the order of tables is remembered every time.
	 * When you use this, make sure you always use saveData with the same order of Tables in array $tables. Otherwise data files for same table will be created multiple times.
	 * if $indexFiles is false (which is the default), only the tablename is used as filename.
	 * Consider to use setForeignKeyChecksForData to false, to prevent Foreign Key Errors.
	 *
	 * @param null|string $saveToDir
	 * @param array $tables
	 * @param array $skipColumns
	 * @param bool $indexFiles
	 * @return array
	 * @throws Exception
	 */
	public function saveData($saveToDir = null, $tables = [], $skipColumns = [], $indexFiles = false) {
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
			$structure->saveData($data, $this->dbname, $indexFiles);
		}

		return $data;
	}

	public function escape($string) {
		return $this->database->escape($string);
	}
}