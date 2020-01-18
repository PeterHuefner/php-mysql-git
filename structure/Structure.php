<?php


namespace PhpMySqlGit;


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

	public function readStructure($database) {
		if (is_dir($this->directory)) {
			$this->structure["databases"] = [];
			if ($this->addFileContentsToArray($this->path($this->directory, [$database, "database.php"]), $this->structure["databases"])) {
				$this->structure["databases"][$database]["tables"] = [];
				foreach (glob($this->path($this->directory, [$database, "tables", "*.php"])) as $tableFile) {
					$this->addFileContentsToArray($tableFile, $this->structure["databases"][$database]["tables"]);
				}
			}
		} else {
			throw new Exception($this->directory." does not exists");
		}
	}

	protected function path($base, $parts) {
		$path = $base;

		if ($path) {
			$path = dirname($path) . DIRECTORY_SEPARATOR . basename($path);
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
		$settings = [
			$database => $settings
		];
		$this->saveArrayToFile($settings, $this->path($this->directory, [$database, "database.php"]));
	}

	protected function saveTableConfig($database, $table, $settings) {
		$settings = [
			$table => $settings
		];

		$this->saveArrayToFile($settings, $this->path($this->directory, [$database, "tables", $table.".php"]));
	}

	protected function saveArrayToFile($array, $file) {
		$content = var_export($array, true);

		$content = "<?php\nreturn ".$content.";";

		file_put_contents($file, $content);
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