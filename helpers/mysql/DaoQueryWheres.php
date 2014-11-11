<?php
namespace PHPAutocoder\Helpers\Mysql;


/**
 * Class to handle extra where conditions (outside of the standard ones that are
 * provided by the auto generated dao classes)
 * use very carefully and sparingly, as this will allow you to circumvent the
 * control that the daos put on you to make sure you query against indexes
 *
 * all the extra where clauses that are generated are AND'ed together as using OR in
 * a where clause is much higher load on the database
 *
 * @author dwayn
 */
class DaoQueryWheres
{
	protected $wheres = array();

	const REL_EQUALS		= '=';
	const REL_GREATER		= '>';
	const REL_LESS			= '<';
	const REL_GREATER_EQUALS	= '>=';
	const REL_LESS_EQUALS		= '<=';
	const REL_NOT_EQUALS		= '!=';
	const REL_IN			= 'IN';

	// use TYPE_BIND if value is a variable that needs to be bound, use TYPE_INTERNAL
	// if value is another column name
	// ie. for something that looks like "table_foo.id" = 5 use TYPE_BIND
	//     for something that looks like "table_foo.id = table_bar.id" use TYPE_INTERNAL
	const TYPE_BIND		= 1;	// denotes that the value field needs to be bound in a prepared query
	const TYPE_INTERNAL	= 2;	// denotes that the value field as anther column reference

	/**
	 * add a where clause to the object
	 *
	 * @param string $column - should be in the form table_name.column_name, but is not required; not adding table name reference may cause query to fail due to ambiguous refernces
	 * @param mixed $value - value to assign to column, must be an array of values if $relationship = REL_IN
	 * @param const $relationship - must be one of the constants of REL_*
	 * @param const $type - must be of type TYPE_*
	 * @return DaoQueryWheres
	 */
	public function addWhere($column, $value, $relationship = self::REL_EQUALS, $type = self::TYPE_BIND)
	{
		if($relationship == self::REL_IN && !is_array($value))
			throw new DaoQueryWheresException("Value must be an array when using REL_IN for an in clause");

		$this->wheres[] = array("left_val" => $column, "right_val" => $value, "relation" => $relationship, "type" => $type);

		return $this;
	}

	/**
	 * Clear the loaded where clauses
	 *	- chainable
	 *
	 * @return DaoQueryWheres 
	 */
	public function clearWheres()
	{
		$this->wheres = array();
		return $this;
	}

	public function getWhereSql(&$binds)
	{
		$sqls = array();
		foreach($this->wheres as $e)
		{
			$sql = '';
			$lval = $e['left_val'];
			$rval = $e['right_val'];
			$rel = $e['relation'];
			$type = $e['type'];

			$sql .= " AND $lval $rel ";

			if($rel == self::REL_IN)
			{
				$vars = array();
				foreach($rval as $k)
				{
					// check if it is a number or if it has already been
					// quoted (quote is first char in string), if not
					// then we need to quote the field
					if(floatval($k) == $k || strpos($k, "'") == 0 || strpos($k, '"') == 0)
						$vars[] = $k;
					else
						$vars[] = "'$k'";
				}
				$sql .= "(" . implode(",", $vars) . ") ";
				$sqls[] = $sql;
				continue;
			}

			// if it is an inner reference to a table column then we just set it
			if($type == self::TYPE_INTERNAL)
			{
				$sql .= " $rval ";
				$sqls[] = $sql;
				continue;
			}

			// if it is a bind, then we need to add it to the set of binds and generate a bind replacement key for it
			// this is where it will get a little interesting
			if($type == self::TYPE_BIND)
			{
				$key = md5("$lval::$rval");
				$sql .= " :$key";
				$binds[":$key"] = $rval;
				$sqls[] = $sql;
				continue;
			}

		}

		return implode("\n", $sqls);
	}


}
