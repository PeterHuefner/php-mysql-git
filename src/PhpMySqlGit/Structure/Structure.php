<?php


namespace PhpMySqlGit\Structure;

use PhpMySqlGit\Core\Common;
use PhpMySqlGit\Core\Exception;
use PhpMySqlGit\PhpMySqlGit;

class Structure {

	protected $structure = [];
	protected $directory;

	public function __construct($directory) {
		$this->directory = $directory;

		if (!is_dir($this->directory)) {
			mkdir($this->directory, PhpMySqlGit::$instance->getDirMod(), true);
		}
	}

	/**
	 * @return array
	 */
	public function getStructure(): array {
		return $this->structure;
	}

	public function saveStructure($structure, $tables) {
		$this->structure = $structure;

		if (is_dir($this->directory)) {
			foreach ($this->structure['databases'] as $database => $settings) {
				$this->checkCreateDir($this->path($this->directory, [$database]));
				$this->saveDatabaseConfig($database, $settings);

				$this->checkCreateDir($this->path($this->directory, [$database, "tables"]));
				foreach ($settings["tables"] as $tableName => $tableSettings) {
					if ($tables && !in_array($tableName, $tables)) {
						continue;
					}

					$this->saveTableConfig($database, $tableName, $tableSettings);
				}
			}
		} else {
			throw new Exception($this->directory." does not exists");
		}
	}

	protected function checkCreateDir($dir) {
		if (!is_dir($dir)) {
			mkdir($dir, PhpMySqlGit::$instance->getDirMod(), true);
		}
	}

	protected function path($base, $parts = []) {
		$path = $base;

		if ($path) {
			$path = dirname($path).DIRECTORY_SEPARATOR.basename($path);
		}

		if (PhpMySqlGit::$instance->isSaveNoDbName()) {
			array_splice($parts, 0, 1);
		}

		$path .= DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $parts);

		return $path;
	}

	protected function saveDatabaseConfig($database, $settings) {
		unset($settings["tables"]);
		if (PhpMySqlGit::$instance->isSaveNoDbName()) {
			unset($settings['SCHEMA_NAME']);
			$settings = [
				"DATABASE" => $settings
			];
		} else {
			$settings = [
				$database => $settings
			];
		}
		$this->saveArrayToFile($settings, $this->path($this->directory, [$database, "database.php"]));
	}

	protected function saveArrayToFile($array, $file) {
		Common::saveArrayToFile($array, $file);
	}

	protected function saveTableConfig($database, $table, $settings) {
		$settings = [
			$table => $settings
		];

		$this->saveArrayToFile($settings, $this->path($this->directory, [$database, "tables", $table.".php"]));
	}

	public function saveData($data, $database) {
		if (is_dir($this->directory)) {
			$this->checkCreateDir($this->path($this->directory, [$database, "data"]));

			$counter = 0;
			foreach ($data as $tableName => &$tableData) {
				$this->saveArrayToFile([$tableName => $tableData], $this->path($this->directory, [$database, "data", sprintf('%08d', $counter)."-".$tableName.".php"]));
				$counter++;
			}
		} else {
			throw new Exception($this->directory." does not exists");
		}
	}

	public function readStructure($database) {
		if (is_dir($this->directory)) {
			$this->structure["databases"] = [];
			if ($this->addFileContentsToArray($this->path($this->directory, [$database, "database.php"]), $this->structure["databases"])) {
				if (PhpMySqlGit::$instance->isSaveNoDbName()) {
					$this->structure["databases"][$database] = $this->structure["databases"]["DATABASE"];
					unset($this->structure["databases"]["DATABASE"]);
				}

				if (PhpMySqlGit::$instance->isOverwriteCharset()) {
					$this->structure["databases"][$database]["DEFAULT_CHARACTER_SET_NAME"] = PhpMySqlGit::$instance->getCharset();
					$this->structure["databases"][$database]["DEFAULT_COLLATION_NAME"]     = PhpMySqlGit::$instance->getCollation();
				}

				$this->structure["databases"][$database]["tables"] = [];
				foreach (glob($this->path($this->directory, [$database, "tables", "*.php"])) as $tableFile) {
					$this->addFileContentsToArray($tableFile, $this->structure["databases"][$database]["tables"]);
				}

				foreach ($this->structure["databases"][$database]["tables"] as $tableName => &$tableConfig) {
					if (PhpMySqlGit::$instance->isOverwriteCharset()) {
						$tableConfig["collation"] = PhpMySqlGit::$instance->getCollation();

						foreach ($tableConfig["columns"] as $columnName => &$columnConfig) {
							if ($columnConfig['character_set']) {
								$columnConfig['character_set'] = PhpMySqlGit::$instance->getCharset();
							}
							if ($columnConfig['collation']) {
								$columnConfig['collation'] = PhpMySqlGit::$instance->getCollation();
							}
						}
					}

					if (PhpMySqlGit::$instance->isOverwriteEngine()) {
						$tableConfig["engine"] = PhpMySqlGit::$instance->getEngine();
					}

					if (PhpMySqlGit::$instance->isOverwriteRowFormat()) {
						$tableConfig["row_format"] = PhpMySqlGit::$instance->getRowFormat();
					}
				}
			}
		} else {
			throw new Exception($this->directory." does not exists");
		}
	}

	protected function addFileContentsToArray($file, &$array) {
		if (($contentArray = $this->readArrayFromFile($file)) && is_array($contentArray)) {
			$array = array_merge($array, $contentArray);
			return $contentArray;
		}
		return null;
	}

	protected function readArrayFromFile($file) {
		$array = [];
		if (file_exists($file)) {
			$array = $this->incFile($file);
		}
		return $array;
	}

	/**
	 * includes a file and returns the php execution result.
	 * through output buffering is ensured that no echos or other outputs are written on output stream or buffer
	 *
	 * @param $file
	 * @return mixed
	 */
	protected function incFile($file) {
		$buffer = null;
		if (ob_get_level() === 0) {
			ob_start();
		} else {
			$buffer = ob_get_clean();
		}
		$return = include($file);
		ob_get_clean();
		if ($buffer) {
			echo $buffer;
		}
		return $return;
	}

	public function readData($database) {
		$data = [];

		if (is_dir($this->directory)) {
			$dataPath = $this->path($this->directory, [$database, "data"]);
			if (is_dir($dataPath)) {
				foreach (scandir($dataPath) as $tableFile) {
					$pathinfo = pathinfo($tableFile);
					if ($tableFile[0] === "." || ($pathinfo['extension'] ?? '') !== 'php') {
						continue;
					}
					$tableData = $this->incFile($this->path($this->directory, [$database, "data", $tableFile]));
					if (is_array($tableData)) {
						$data = array_merge($data, $tableData);
					}
				}
			}
		}

		return $data;
	}

	/*protected function generateFileName($filename) {
		$filename = preg_replace_("/[^a-z]|\s/", "_", $filename);

		return $filename;
	}*/
}