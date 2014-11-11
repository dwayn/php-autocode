<?php
/**
 * Example base class that implements features for joins and special where clauses
 *
 * @author dwayn
 */
abstract class ComplexDao
{
	/**
	 * @var DaoTableJoins
	 */
	protected $joins;
	/**
	 * @var DaoQueryWheres
	 */
	protected $wheres;
	/**
	 * @var PDO
	 */
	protected $db;

	protected $debug = false;
	
	protected static $joinMap = array();

	public function  __construct(PDO $db)
	{
		$this->db = $db;
	}

	/**
	 * set the dao to output debugging text
	 *
	 * @param boolean $debug
	 * @return CFDao 
	 */
	public function withDebug($debug = true)
	{
		$this->debug = $debug;
		return $this;
	}

	protected function debug($sql, $binds)
	{
		if(!$this->debug)
			return;

		echo "SQL:\n";
		echo "$sql\n\n";

		echo "binds:\n";
		print_r($binds);
		echo "\n\n";




	}

	/**
	 * function to add the joins that you want to your query
	 *
	 * @param DaoTableJoins $joins
	 * @return CFDao
	 */
	public function join(DaoTableJoins $joins)
	{
		$this->joins = $joins;
		return $this;
	}

	/**
	 * function to add the extra where clauses that you want to add
	 *
	 * @param DaoQueryWheres $wheres
	 * @return CFDao
	 */
	public function where(DaoQueryWheres $wheres)
	{
		$this->wheres = $wheres;
		return $this;
	}


	public static function tableNameToClassName($tableName)
	{
		// this effectively turns the table name into a class name:  USER_user_roles becomes UserUserRolesDao
		$classname = implode("", explode(" ", ucwords(str_replace("_", " ", strtolower($tableName)))))."Dao";
		return $classname;
	}

	protected static function getJoins($tableName, DaoTableJoins &$joins, &$joinArray, &$bindsArray)
	{
		if(is_null($joins))
		{
			return;
		}
		$tables = $joins->getTablesJoined($tableName);
		if(empty($tables))
		{
			return;
		}

		foreach($tables as $table)
		{
			//get the joins and binds for each table that this one joins to
			if(isset(static::$joinMap[$table]))
				$joindata = $joins->getJoinData($tableName, static::$joinMap[$table]['src_col'], $table, static::$joinMap[$table]['dest_col']);
			else
				$joindata = $joins->getJoinData($tableName, null, $table, null);
			$joinArray[] = $joindata['sql'];
			foreach($joindata['binds'] as $key => $val)
			{
				$bindsArray[$key] = $val;
			}
			//remove the join from the joins object after it has been processed
			$joins->removeJoin($tableName, $table);
		}
		//process the joins for each of the tables that this one is linked to
		foreach($tables as $table)
		{
			$j = $joins->getTablesJoined($table);
			if(empty($j))
				continue;
			$daoClass = self::tableNameToClassName($table);
			$daoClass::getJoins($table, $joins, $joinArray, $bindsArray);
			//$dao = new $class($this->db);
			//$dao->getJoins($table, $joins, $joinArray, $bindsArray);
		}

	}
}


class DaoException extends Exception {}
