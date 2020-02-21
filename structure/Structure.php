<?php


namespace PhpMySqlGit\Structure;

use PhpMySqlGit\Core\Common;
use PhpMySqlGit\Core\Exception;
use PhpMySqlGit\PhpMySqlGit;

class Structure {

	protected $structure = [];
	protected $directory;

	/**
	 * @return array
	 */
	public function getStructure(): array {
		return $this->structure;
	}

	public function __construct($directory) {
		$this->directory = $directory;

		if (!is_dir($this->directory)) {
			mkdir($this->directory, PhpMySqlGit::$instance->getDirMod(), true);
		}
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

	public function saveData($data, $database) {
		if (is_dir($this->directory)) {
			$this->checkCreateDir($this->path($this->directory, [$database, "data"]));

			foreach ($data as $tableName => &$tableData) {
				$this->saveArrayToFile([$tableName => $tableData], $this->path($this->directory, [$database, "data", $tableName.".php"]));
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
				$this->structure["databases"][$database]["tables"] = [];
				foreach (glob($this->path($this->directory, [$database, "tables", "*.php"])) as $tableFile) {
					$this->addFileContentsToArray($tableFile, $this->structure["databases"][$database]["tables"]);
				}
			}
		} else {
			throw new Exception($this->directory." does not exists");
		}
	}

	protected function path($base, $parts = []) {
		$path = $base;

		if ($path) {
			$path = dirname($path) . DIRECTORY_SEPARATOR . basename($path);
		}

		if (PhpMySqlGit::$instance->isSaveNoDbName()) {
			array_splice($parts, 0, 1);
		}

		$path .= DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);

		return $path;
	}

	protected function checkCreateDir($dir) {
		if (!is_dir($dir)) {
			mkdir($dir, PhpMySqlGit::$instance->getDirMod(), true);
		}
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

	protected function saveTableConfig($database, $table, $settings) {
		$settings = [
			$table => $settings
		];

		$this->saveArrayToFile($settings, $this->path($this->directory, [$database, "tables", $table.".php"]));
	}

	protected function saveArrayToFile($array, $file) {
		Common::saveArrayToFile($array, $file);
	}

	protected function readArrayFromFile($file) {
		$array = [];
		if (file_exists($file)) {
			$array = include($file);
		}
		return $array;
	}

	protected function addFileContentsToArray($file, &$array) {
		if (($contentArray = $this->readArrayFromFile($file)) && is_array($contentArray)) {
			$array = array_merge($array, $contentArray);
			return $contentArray;
		}
		return null;
	}

	/*protected function generateFileName($filename) {
		$filename = preg_replace_("/[^a-z]|\s/", "_", $filename);

		return $filename;
	}*/
}