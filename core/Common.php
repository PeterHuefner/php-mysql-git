<?php


namespace PhpMySqlGit\Core;


class Common {

	public static function array_append(array &$array, $index, $elements) {
		if ($index === null || $index === false) {
			$array = array_merge($elements, $array);
		} else {
			$indexPos = array_search($index, array_keys($array));
			$length   = ($indexPos === 0 ? 1 : $indexPos);

			if (is_int($indexPos)) {
				$indexPos++;
			}

			if ($indexPos !== false) {
				$array = array_merge(
					array_slice($array, 0, $indexPos),
					$elements,
					array_slice($array, $indexPos, count($array)-$indexPos)
				);
			}
		}

		return $array;
	}

	public static function array_compare(array &$arrayOne, array &$arrayTwo) {
		$equal = true;

		if (count($arrayOne) !== count($arrayTwo)) {
			$equal = false;
		} else {
			if (array_diff(array_keys($arrayOne), array_keys($arrayTwo))) {
				$equal = false;
			} else {
				foreach ($arrayOne as $key => &$value) {
					if (array_key_exists($key, $arrayTwo)) {
						if (!is_array($value)) {
							if ($value !== $arrayTwo[$key]) {
								$equal = false;
								break;
							}
						} else {
							if (!self::array_compare($value, $arrayTwo[$key])) {
								$equal = false;
								break;
							}
						}
					} else {
						$equal = false;
						break;
					}
				}
			}
		}

		return $equal;
	}
}