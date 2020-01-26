<?php


namespace PhpMySqlGit;


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
}