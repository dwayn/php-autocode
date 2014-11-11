<?php
/*____PROCESSOR____COMMENT____START____*/
/*
 *
 * Variables:
 * __CLASS__NAME__			string form of class name
 * __PRIMARY__KEYS__LIST__		comma seperated list of column names for primary key, ie. "'col1', 'col2', ..."
 * __PKGET__BINDS__			array of binds for the primary key lookup
 *		__COLNAME__
 *		$__COLVAL__
 * __TABLE__COLUMNS__LIST__		comma seperated list of all columns in table
 * __FUNCTION__KEYS__			array of function names and their associated columns that are used for querying - should be all permutations of valid uses of indexes on table
 *		__FUNCTION__NAME__	getBy function name, generated off each index on table (one also exists for "get" function but is named "primary" here)
 *		__TABLE__COLS__		comma seperated list of columns used for the particular getBy, ie. "'col1', 'col2', 'col3', ..."
 * $__PRIMARY__KEY__PARMS__		comma seperated list of variables as arguments for the primary key lookup, ie. "$arg1, $arg2, $arg3, ..."
 * __TABLE__NAME__			name of the table that DAO is made for
 * $__CREATE__FUNCTION__ARGS__		arguments for the create function
 * __CREATE__FUNCTION__SETS__QUERY__	set of binds for columns, ie. col1=:col1, col2=:col2, ....
 * __CREATE__FUNCTION__BINDS__		array of bind variables for the create
 *		__COLNAME__
 *		$__COLVAL__
 * __LAST__INSERT__ID__CODE__;		code to get last insert id if needed...otherwise needs to be set to ""
 * $__CREATE__FUNCTION__LOOKUP__ARGS__	set of args to do primary key lookup (may depend on var set by __LAST__INSERT__ID__CODE__)
 * __GET__BY__SUBTEMPLATES__		array of AutoCoder subtemplates to be processed, needs to be at least an empty array
 *
 *
 */
/*____PROCESSOR____COMMENT____END____*/

/**
 * __CLASS__NAME__
 *
 *
 * @author AutoCoder
 */
class __CLASS__NAME__ extends SimpleDao
{

////////////////////////////////////////////////////////////////////////////////////////////////////////////
///////////                        DO NOT MODIFY - GENERATED CODE                               ////////////
	protected static $primaryKey = array(__PRIMARY__KEYS__LIST__);
	protected static $columns = array(__TABLE__COLUMNS__LIST__);
	protected static $functionKeys = array(
			'__FUNCTION__NAME__' => array(__TABLE__COLS__),/*____REPEATABLE____:__FUNCTION__KEYS__*/
			);
//////////                                                                                      ////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////


/*____PROCESSOR____CUSTOMFUNCTIONS____START____*/

/*____PROCESSOR____CUSTOMFUNCTIONS____END____*/



/*____FUNCTION____START____*/
	// make note that for now the primary get function will only return 1 row even in the
	// case of joining other tables that potentially could make multiple rows be returned
	// @todo consider making this have support for multi row returns
	public function get($__PRIMARY__KEY__PARMS__)
	{
		$wheres = array();
		foreach(__CLASS__NAME__::$primaryKey as $k)
		{
			$wheres[] = "__TABLE__NAME__.`$k` = :$k";
		}

		$sql = 'select * from __TABLE__NAME__ ';

		$extraBinds = array();
		// adds the joins

		// add standard where clause
		$sql .= "\n where " . implode("\n AND ", $wheres);

		// all extra query magic has been done at this point
		$q = $this->db->prepare($sql);

		// standard key based binds
		$q->bindValue(':__COLNAME__', $__COLVAL__);/*____REPEATABLE____:__PKGET__BINDS__*/

		$q->execute();
		$data = $q->fetch(PDO::FETCH_ASSOC);

		if(!empty($data))
		{
			// if there is no joins involved then all the row contents to be changed
			// otherwise lick down the StateArray because at this time updating is not 
			// allowed on joined tables 
			// (due to high complexity to implement and possible issues that might arise with data integrity)
            $rval = new StateArray($data, StateArray::ALLOW_NONE);
		}
		else
		{
			//returning an empty array in the case that a row is not returned
			$rval = array();
		}

		return $rval;
	}
/*____FUNCTION____END____*/

/*____FUNCTION____START____*/
	public function update(StateArray $row)
	{
		$prev = $row->getPrev();
		if(count($prev) == 0)
		{
			return $row;
		}

		// enforce update of a updated column
		if(isset($row['updated']))
		{
			if(!isset($prev['updated']))
			{
				// sets up prev so that updated gets added to the set of updates in the query
				$prev['updated'] = $row['updated'];
			}
			$row['updated'] = date('Y-m-d H:i:s');
		}

		// disallow the update of a created column
		if(isset($prev['created']))
		{
			unset($prev['created']);
		}

		$updates = array();
		foreach($prev as $key => $val)
		{
			$updates[] = "`$key` = :$key";
		}

		$wheres = array();
		foreach(__CLASS__NAME__::$primaryKey as $k)
		{
			$wheres[] = "`$k` = :pk__$k";
		}

		$query = 'update __TABLE__NAME__ set ' . implode(', ', $updates) . ' where ' . implode(' AND ', $wheres);

		$q = $this->db->prepare($query);

		foreach($prev as $key => $val)
		{
			$q->bindValue(":$key", $row[$key]);
		}

		foreach(__CLASS__NAME__::$primaryKey as $pk)
		{
			if(isset($prev[$pk]))
			{
				$q->bindValue(":pk__$pk", $prev[$pk]);
			}
			else
			{
				$q->bindValue(":pk__$pk", $row[$pk]);
			}
		}

		$success = $q->execute();

		// @todo do we want to throw an exception on failure to write data to the table  or would we prefer to have it just log and keep going
		if(!$success)
		{
			$errorinfo = $q->errorInfo();
			throw new __CLASS__NAME__Exception("Error attempting to update record in __TABLE__NAME__ with message: {$errorinfo[2]}", 1);
		}


		//reset history on StateArray
		$row->clearPrev();

		return $row;
	}
/*____FUNCTION____END____*/

/*____FUNCTION____START____*/
	public function create($__CREATE__FUNCTION__ARGS__)
	{
		$q = $this->pdo->prepare("insert into __TABLE__NAME__ set __CREATE__FUNCTION__SETS__QUERY__");
		$q->bindValue(':__COLNAME__', $__COLVAL__);/*____REPEATABLE____:__CREATE__FUNCTION__BINDS__*/
		$success = $q->execute();

		if(!$success)
		{
			$errorinfo = $q->errorInfo();
			throw new __CLASS__NAME__Exception("Error attempting to create record in __TABLE__NAME__ with message: {$errorinfo[2]}", 1);
		}

		__LAST__INSERT__ID__CODE__;

		$rval = $this->get($__CREATE__FUNCTION__LOOKUP__ARGS__, true);

		return $rval;
	}
/*____FUNCTION____END____*/

	
/*____SUBTEMPLATE____:__GET__BY__SUBTEMPLATES__*//*____REPEATABLE____:__GET__BY__SUBTEMPLATES__*/
}

class __CLASS__NAME__Exception extends DaoException { }
