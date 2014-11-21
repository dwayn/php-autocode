<?php
namespace PHPAutocoder\Helpers\Mysql;
use PDO;


/**
 * Helper class for parsing table definition data (describe and create)
 *    into usable data to aid in code generation
 *
 * @author dwayn
 */
class TableHelper
{
    protected $pdo;
    protected $createTable;
    protected $indexes;
    protected $indexPermutations;
    protected $describeData;
    protected $tablename;
    protected $primaryKeyCols;
    protected $uniqueKeyCols;
    protected $columns;
    protected $colWeights;
    protected $autoIncCol;
    protected $foreignKeys;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function  __get($name)
    {
        if (isset($this->$name))
        {
            return $this->$name;
        }
    }

    public function processTable($tablename)
    {
        $this->autoIncCol   = null;
        $this->tablename    = $tablename;
        $q                  = $this->pdo->prepare("describe {$this->tablename}");
        $this->describeData = array();
        $this->foreignKeys  = array();
        $q->execute();
        while ($row = $q->fetch(PDO::FETCH_ASSOC))
        {
            $this->describeData[$row['Field']] = $row;
        }

        if (count($this->describeData) == 0)
        {
            throw new TableHelperException("Unable to get table definitions for {$this->tablename}\n", 1);
        }

        $q = $this->pdo->prepare("show create table {$this->tablename}");
        $q->execute();

        $row               = $q->fetch(PDO::FETCH_NUM);
        $this->createTable = $row[1];

        $this->parseDescribe();
        $this->parseIndexes();
        $this->parseForeignKeys();
        $this->permuteIndexes();

    }

    protected function parseIndexes()
    {
        $pieces = explode("\n", $this->createTable);

        foreach ($pieces as $line)
        {
            // skip foreign key definitions or they will be picked up
            // by the last generic key match...this is so that the foreign key
            // logic stays together in parseForeignKeys
            if (preg_match('/CONSTRAINT.*?FOREIGN KEY.*?/', $line))
            {
                continue;
            }

            if (preg_match('/PRIMARY KEY.*?\((`\w+`)([,\s]+`\w+`)*/', $line, $matches))
            {
                preg_match('/KEY.*?\((`\w+`([,\s]+`\w+`)*)/', $line, $matches);
                preg_match_all('/`(\w+)`/', $matches[1], $m);
                $this->primaryKeyCols     = $m[1];
                $this->indexes['primary'] = $this->primaryKeyCols;
                continue;
            }

            if (preg_match('/UNIQUE KEY.*?\((`\w+`)([,\s]+`\w+`)*/', $line, $matches))
            {
                preg_match('/KEY.*?\((`\w+`([,\s]+`\w+`)*)/', $line, $matches);
                preg_match_all('/`(\w+)`/', $matches[1], $m);

                $this->uniqueKeyCols = $m[1];
                $this->indexes[]     = $this->uniqueKeyCols;
                continue;
            }

            if (preg_match('/KEY.*?\((`\w+`)([,\s]+`\w+`)*/', $line, $matches))
            {
                preg_match('/KEY.*?\((`\w+`([,\s]+`\w+`)*)/', $line, $matches);
                preg_match_all('/`(\w+)`/', $matches[1], $m);
                $this->indexes[] = $m[1];
                continue;
            }
        }
    }

    protected function parseForeignKeys()
    {
        $pieces = explode("\n", $this->createTable);

        foreach ($pieces as $line)
        {
            if (preg_match('/CONSTRAINT.*?FOREIGN KEY.*?\((`\w+`)\).*?REFERENCES.*?(`\w+`).*?\((`\w+`)\)*/', $line, $matches))
            {
                $colName     = str_replace('`', '', $matches[1]);
                $refTable    = str_replace('`', '', $matches[2]);
                $refTableCol = str_replace('`', '', $matches[3]);

                // not sure if this is useful yet but might as well grab it if we know it
                $cascadeUpdates = false;
                $cascadeDeletes = false;
                if (preg_match('/ON DELETE CASCADE/', $line))
                    $cascadeDeletes = true;
                if (preg_match('/ON UPDATE CASCADE/', $line))
                    $cascadeUpdates = true;

                $this->foreignKeys[$colName] = array("table" => $refTable, "column" => $refTableCol, "cascade_deletes" => $cascadeDeletes, "cascade_updates" => $cascadeUpdates);
                continue;
            }
        }

    }

    protected function permuteIndexes()
    {
        $permutations = array();

        foreach ($this->indexes as $mainIndex)
        {
            $id = $this->calculateIndexId($mainIndex);
            if (!isset($permutations[$id]))
            {
                $permutations[$id] = $mainIndex;
            }

            $perm = array();
            for ($x = 0; $x < count($mainIndex) - 1; $x++)
            {
                $perm[] = $mainIndex[$x];
                $id     = $this->calculateIndexId($perm);
                if (!isset($permutations[$id]))
                {
                    $permutations[$id] = $perm;
                }
            }
        }
        $this->indexPermutations = $permutations;
    }

    public function calculateIndexId($cols)
    {
        $rval = 0;
        foreach ($cols as $c)
        {
            $rval += $this->colWeights[$c];
        }

        return $rval;
    }

    protected function parseDescribe()
    {
        $this->columns = array();
        foreach ($this->describeData as $d)
        {
            $this->columns[] = $d['Field'];
            if (strpos($d['Extra'], "auto_increment") !== false)
            {
                $this->autoIncCol = $d['Field'];
            }
        }

        //weight columns to aid in making unique permutations, ie.
        $this->colWeights = array();
        $x                = 1;
        foreach ($this->columns as $c)
        {
            $this->colWeights[$c] = $x;
            $x *= 2;
        }
    }

}

