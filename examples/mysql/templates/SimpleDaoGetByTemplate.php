/*____PROCESSOR____COMMENT____START____*/
<?php
// wrapping this since it is made to be included into other templates
// this allows for the template to still pass php lint checks

class absolute_dummy_class_ignore_me 
{
/**
 * Simple getBy* function for doing lookups on non primary key indexes
 *
 * Variables that need to be assigned:
 * __FUNCTION__NAME__			name of the getBy function
 * $__FUNCTION__ARGS__			comma seperated list of variables that the lookup is happening on, ie. "$col1, $col2, $col3,..."
 * $__CACHE__KEY__ARGS__		colon seperated list of variables to use for setting cache key, ie. "$col1:$col2:$col3..."
 * __TABLE__NAME__			name of table for selecting on
 * __SELECT__WHERES__			string used to build query in the form of  "col1=:col1 AND col2=:col2 AND..."
 * __SELECT__BINDS__			array of bind variable sets
 *		__COLNAME__		name of the column
 *		$__COLVAL__		variable that holds value for column
 * __CLASS__NAME__			parent class name
 *
 */
/*____PROCESSOR____COMMENT____END____*/

/*____FUNCTION____START____*/
	public function __FUNCTION__NAME__($__FUNCTION__ARGS__, $limit = 0)
	{

		$sql = 'select * from __TABLE__NAME__ ';

		$extraBinds = array();
		// adds the joins
		if(!is_null($this->joins))
		{
			$passthruJoins = clone $this->joins;
			$joinArray = array();
			self::getJoins('__TABLE__NAME__', $passthruJoins, $joinArray, $extraBinds);
			$sql .= "\n".implode("\n", $joinArray);
		}

		// add standard where clause
		$sql .= "\n where __SELECT__WHERES__ ";

		// add extra where clauses if provided
		if(!is_null($this->wheres))
			$sql .= "\n" . $this->wheres->getWhereSql($extraBinds);

		// if there is a limit then apply it
		if($limit > 0)
			$sql .= "\nlimit $limit";


		$q = $this->db->prepare($sql);
		// standard binds
		$q->bindValue(':__COLNAME__', $__COLVAL__);/*____REPEATABLE____:__SELECT__BINDS__*/

		// extra binds from wheres/joins
		foreach($extraBinds as $bindKey => $bindVal)
		{
			$q->bindValue($bindKey, $bindVal);
		}
		$success = $q->execute();

		if(!$success)
		{
			$info = $q->errorInfo();
			throw new __CLASS__NAME__Exception("Error querying table __TABLE__NAME__ with message: $info[2]", 1);
		}

		$data = array();
		while($row = $q->fetch(PDO::FETCH_ASSOC))
		{
			if(is_null($this->joins))
				$data[] = new StatefulArray($row, StatefulArray::ALLOW_WRITE);
			else
				$data[] = new StatefulArray($row, StatefulArray::ALLOW_NONE);

		}

		return $data;
	}
/*____FUNCTION____END____*/
/*____PROCESSOR____COMMENT____START____*/
}
/*____PROCESSOR____COMMENT____END____*/
