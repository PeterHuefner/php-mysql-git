<?php


namespace PhpMySqlGit\Sql;


class Key {
	use SQLObject;

	protected function keyDef($type, $withConstraint = false) {
		if ($type) {
			$type .= " ";
		}

		$sql =  ($withConstraint ? "CONSTRAINT `".$this->name."` " : "").$type."KEY";

		if ($type != "PRIMARY") {
			$sql .= " `".$this->name."`";
		}

		$comma = "";
		$sql .= " (";
		foreach ($this->definition["columns"] as $column) {
			$sql .= $comma."`".$column["name"]."` ASC";
			$comma = ", ";
		}

		return $sql.")";
	}

	protected function drop($type) {
		if ($type) {
			$type .= " ";
		}

		$sql = "DROP ".$type."KEY";

		if ($type != "PRIMARY") {
			$sql .= " `".$this->name."`";
		}

		return $sql;
	}

	public function primaryKeyDefinition() {
		return $this->keyDef("PRIMARY");
	}

	public function keyDefinition() {
		return $this->keyDef("");
	}

	public function uniqueKeyDefinition() {
		return $this->keyDef("UNIQUE", true);
	}

	public function fulltextKeyDefinition() {
		return $this->keyDef("FULLTEXT");
	}

	public function spatialKeyDefinition() {
		return $this->keyDef("SPATIAL");
	}

	public function foreignKeyDefinition() {
		return
			"CONSTRAINT `{$this->name}` ".
			"FOREIGN KEY `{$this->name}` (`".implode('`, `', $this->definition['columns'])."`) ".
			"REFERENCES `{$this->definition['referenced_table']}` (`".implode('`, `', $this->definition['referenced_columns'])."`) ".
			"ON UPDATE {$this->definition['UPDATE_RULE']} ".
			"ON DELETE {$this->definition['DELETE_RULE']}";
	}

	public function dropKey() {
		return $this->drop("");
	}

	public function dropPrimaryKey() {
		return $this->drop("PRIMARY");
	}

	public function dropUniqueKey() {
		return $this->drop("UNIQUE");
	}

	public function dropFulltextKey() {
		return $this->drop("FULLTEXT");
	}

	public function dropSpatialKey() {
		return $this->drop("SPATIAL");
	}

	public function dropForeignKey() {
		return $this->drop("FOREIGN");
	}
}