<?php
namespace PHPAutocoder\Helpers\Mysql;


class DaoTableJoins
{
    protected $joins = array();

    const REL_EQUALS         = '=';
    const REL_GREATER        = '>';
    const REL_LESS           = '<';
    const REL_GREATER_EQUALS = '>=';
    const REL_LESS_EQUALS    = '<=';
    const REL_NOT_EQUALS     = '!=';
    const REL_IN             = 'IN';

    const TYPE_BIND     = 1;
    const TYPE_INTERNAL = 2;

    public function addJoin($leftTable, $rightTable, $relationship = self::REL_EQUALS)
    {
        if ($relationship == self::REL_IN)
            throw new DaoTableJoinsException("Cannot use REL_IN as the relation type for standard join");

//		$leftTable = strtolower($leftTable);
//		$rightTable = strtolower($rightTable);
        if (!isset($this->joins[$leftTable]))
            $this->joins[$leftTable] = array();
        $this->joins[$leftTable][$rightTable] = array("relation" => $relationship, "extra_ons" => array());

        return $this;
    }

    public function removeJoin($leftTable, $rightTable)
    {
        if (isset($this->joins[$leftTable][$rightTable]))
            unset($this->joins[$leftTable][$rightTable]);
    }

    /**
     *
     * @param string $leftTable left table name
     * @param <type> $rightTable
     * @param <type> $leftVal
     * @param <type> $rightVal
     * @param <type> $relationship
     * @param <type> $type
     */
    public function addExtraOn($leftTable, $rightTable, $leftVal, $rightVal, $relationship = self::REL_EQUALS, $type = self::TYPE_INTERNAL)
    {
//		$leftTable = strtolower($leftTable);
//		$rightTable = strtolower($rightTable);
        if (!isset($this->joins[$leftTable][$rightTable]))
            throw new DaoTableJoinsException("Must have a join defined for $leftTable => $rightTable before adding ON clause");
        if ($relationship == self::REL_IN && !is_array($rightVal))
            throw new DaoTableJoinsException("rightVal must be an array of values for the IN clause");

        $this->joins[$leftTable][$rightTable]['extra_ons'][] = array("left_val" => $leftVal, "right_val" => $rightVal, "relation" => $relationship, "type" => $type);
    }

    public function getTablesJoined($leftTableName)
    {
//		$leftTableName = strtolower($leftTableName);
        if (!isset($this->joins[$leftTableName]))
            return array();

        return array_keys($this->joins[$leftTableName]);
    }

    /**
     *
     * @param string $leftTable
     * @param string $leftTableCol
     * @param string $rightTable
     * @param string $rightTableCol
     *
     * @return string
     */
    public function getJoinData($leftTable, $leftTableCol = null, $rightTable, $rightTableCol = null)
    {
        $binds        = array();
        $relationship = $this->joins[$leftTable][$rightTable]['relation'];
        $joinSql      = " JOIN $rightTable ON ";

        $joins = array();

        // add the standard join syntax if the left and right column names are not null
        // if the standard cols are null you must provide extra ON clause to join on
        if (!is_null($leftTableCol) && !is_null($rightTableCol))
        {
            $joins[] = " $leftTable.$leftTableCol $relationship $rightTable.$rightTableCol ";
        }
        else
        {
            if (empty($this->joins[$leftTable][$rightTable]['extra_ons']))
                throw new DaoTableJoinsException("Must provide extra ON clause(s) if not using standard join: attempted to join $leftTable => $rightTable");
        }
        //load the extra ON clauses
        if (!empty($this->joins[$leftTable][$rightTable]['extra_ons']))
            $joins[] = $this->getExtraOnsSql($leftTable, $rightTable, $binds);

        $joinSql .= implode("\nAND ", $joins);

        return array("sql" => $joinSql, "binds" => $binds);
    }

    protected function getExtraOnsSql($leftTable, $rightTable, &$binds)
    {
        $sqls = array();
        foreach ($this->joins[$leftTable][$rightTable]['extra_ons'] as $e)
        {
            $sql  = '';
            $lval = $e['left_val'];
            $rval = $e['right_val'];
            $rel  = $e['relation'];
            $type = $e['type'];

            $sql .= " $lval $rel ";
            if ($rel == self::REL_IN)
            {
                $vars = array();
                foreach ($rval as $k)
                {
                    // check if it is a number or if it has already been
                    // quoted (quote is first char in string), if not
                    // then we need to quote the field
                    if (floatval($k) == $k || strpos($k, "'") == 0 || strpos($k, '"') == 0)
                        $vars[] = $k;
                    else
                        $vars[] = "'$k'";
                }
                $sql .= "(" . implode(",", $vars) . ") ";
                $sqls[] = $sql;
                continue;
            }

            // if it is an inner reference to a table column then we just set it
            if ($type == self::TYPE_INTERNAL)
            {
                $sql .= " $rval ";
                $sqls[] = $sql;
                continue;
            }

            // if it is a bind, then we need to add it to the set of binds and generate a bind replacement key for it
            // this is where it will get a little interesting
            if ($type == self::TYPE_BIND)
            {
                $key = md5("$lval::$rval");
                $sql .= " :$key";
                $binds[":$key"] = $rval;
                $sqls[]         = $sql;
                continue;
            }

        }

        return implode(" AND ", $sqls);
    }

}


