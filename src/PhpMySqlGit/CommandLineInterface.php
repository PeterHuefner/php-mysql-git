<?php


namespace PhpMySqlGit;


class CommandLineInterface {

	/**
	 * @var PhpMySqlGit
	 */
	protected $phpMySqlGit;

	protected $dataDir;

	protected $args;

	public function __construct(PhpMySqlGit $phpMySqlGit = null) {
		if (!empty($phpMySqlGit)) {
			$this->phpMySqlGit = $phpMySqlGit;
		}
	}

	/**
	 * @param mixed $dataDir
	 */
	public function setDataDir($dataDir): void {
		$this->dataDir = $dataDir;
	}

	public function execute() {
		$this->prepare();
		$executionFunctions = [
			'configureDatabase' => 0,
			'configureData'     => 0,
			'saveStructure'     => 0,
			'saveData'          => 0,
		];

		foreach ($this->args as $function => $args) {
			if (isset($executionFunctions[$function])) {

				$functionArguments = $args;
				if (!empty($this->dataDir)) {
					$functionArguments[$executionFunctions[$function]] = $this->dataDir;
				}
				call_user_func_array([$this->phpMySqlGit, $function], $functionArguments);
				//var_dump($functionArguments);
			}
		}

		//var_dump($this->phpMySqlGit);
		//var_dump($this->args);
	}

	protected function prepare() {
		global $argv;

		foreach ($argv as $key => $argument) {
			$matches = [];
			if (preg_match('/--([^=]+)=?(.*)/', $argument, $matches) === 1) {
				$functionName = $matches[1];
				$paramValue   = $matches[2];
				$json         = false;

				if ($paramValue === "true" || $paramValue === "1") {
					$paramValue = true;
				} elseif ($paramValue === "false" || $paramValue === "0") {
					$paramValue = false;
				} elseif (preg_match('/^\[.+\]$|^\{.+\}$/', $paramValue) === 1) {
					$paramValue = json_decode($paramValue, true);
					$json       = true;
				}

				if (!$json) {
					$this->args[$functionName][] = $paramValue;
				} else {
					$this->args[$functionName] = $paramValue;
				}
			}
		}

		if (empty($this->phpMySqlGit) && !empty($this->args['connectionString'])) {
			$reflection                             = new \ReflectionClass("\PhpMySqlGit\PhpMySqlGit");
			$instanceParameters['connectionString'] = $this->args['connectionString'][0];
			if (!empty($this->args['username'])) {
				$instanceParameters['username'] = $this->args['username'][0];
			}
			if (!empty($this->args['password'])) {
				$instanceParameters['password'] = $this->args['password'][0];
			}

			$this->phpMySqlGit = $reflection->newInstanceArgs([$instanceParameters]);
		}

		foreach ($this->args as $functionName => $parameters) {
			if (strpos($functionName, "set") === 0 && method_exists($this->phpMySqlGit, $functionName)) {
				call_user_func_array([$this->phpMySqlGit, $functionName], $parameters);
			}
		}
	}
}