<?php
/**
 * Example DAO base class
 *
 * @author dwayn
 */
abstract class SimpleDao
{
	/**
	 * @var PDO
	 */
	protected $db;

	protected $debug = false;
	

	public function  __construct(PDO $db)
	{
		$this->db = $db;
	}

	/**
	 * set the dao to output debugging text
	 *
	 * @param boolean $debug
	 * @return SimpleDao
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

	public static function tableNameToClassName($tableName)
	{
		// this effectively turns the table name into a class name:  USER_user_roles becomes UserUserRolesDao
		$classname = implode("", explode(" ", ucwords(str_replace("_", " ", strtolower($tableName)))))."Dao";
		return $classname;
	}

}


class DaoException extends Exception {}
