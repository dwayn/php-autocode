<?php
namespace PHPAutocoder\Helpers\Mysql;
use SimpleXMLElement;
use PDO;


/**
 * Class to read an entire schema, processing each using TableHelper,
 *    and finding the different foreign key connections
 *
 * @author dwayn
 */
class SchemaHelper
{

    protected $iteratios = 0;
    /**
     * @var PDO
     */
    protected $db;
    protected $tables = array();
    protected $origTableNames = array();
    // array of fk map references in the form of 'root_col' => array of other columns that point to root_col
    // note that the column names are all products of $this->generateColumnSignature
    protected $fkReferences = array();
    // array of column pointers in the form 'col' => 'root_col'
    //	- in the case of a column not pointing to foreign key, it will be 'col' => 'col'
    //	   so that you can chain the lookup of all the others that a column is attached
    //	   to by $this->fkReferences[$this->colReferences['col']]
    // note that the column names are all products of $this->generateColumnSignature
    protected $colReferences = array();
    protected $joinMap = array();
    protected $extraJoinData = array();

    protected $outputXmlFile;

    /**
     * PDO connection needs to be instantiated with the schema that is to be processed
     *
     * @param PDO $db
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }


    public function __get($name)
    {
        if (isset($this->$name))
        {
            return $this->$name;
        }
    }


    /**
     * Main function to start processing the schema
     */
    public function process()
    {
        ini_set("memory_limit", '2048M');
        $this->loadTables();
        $this->processForeignKeyMap();
        $this->buildJoinMap();
        $this->outputSchema();
    }


    /**
     * reads the schema, processes all the tables in the schema using TableHelper, and builds all the foreign key mappings
     */
    protected function loadTables()
    {
        $q = $this->db->prepare("show tables");
        $q->execute();

        while ($tableName = $q->fetchColumn())
        {
            // this catches the case where case sensitivity is being abused by having multiple
            //	tables with the same name but different case, ie: 2 tables named my_table & MY_TABLE
            if (isset($this->origTableNames[strtolower($tableName)]))
                throw new SchemaHelperException('Multiple tables with the same name: aborting');
            $this->origTableNames[strtolower($tableName)] = $tableName;
            $tableName                                    = strtolower($tableName);
            $this->tables[$tableName]                     = new TableHelper($this->db);
            $this->tables[$tableName]->processTable($this->origTableNames[$tableName]);
        }
    }


    protected function processForeignKeyMap()
    {
        foreach ($this->tables as $tableName => $th)
        {
            // map the foreign key columns
            foreach ($th->foreignKeys as $colName => $refData)
            {
                if (!isset($this->colReferences[$this->gcs($tableName, $colName)]))
                {
                    // the side effect of this is that the root column (the actual foreign key) is mapped to itself to enable traversing all the other things that point to it
                    $this->colReferences[$this->gcs($tableName, $colName)] = $this->gcs(strtolower($refData['table']), $refData['column']);
                }
                if (!isset($this->fkReferences[$this->gcs(strtolower($refData['table']), $refData['column'])]))
                {
                    $this->fkReferences[$this->gcs(strtolower($refData['table']), $refData['column'])] = array();
                }

                $this->fkReferences[$this->gcs(strtolower($refData['table']), $refData['column'])][] = $this->gcs($tableName, $colName);
            }

            // map the non foreign key columns
            foreach ($th->columns as $column)
            {
                if (!isset($this->colReferences[$this->gcs($tableName, $column)]))
                {
                    // the side effect of this is that the root column (the actual foreign key) is mapped to itself to enable traversing all the other things that point to it
                    $this->colReferences[$this->gcs($tableName, $column)] = $this->gcs($tableName, $column);
                }
                if (!isset($this->fkReferences[$this->gcs($tableName, $column)]))
                {
                    $this->fkReferences[$this->gcs($tableName, $column)] = array();
                }
            }
        }
    }


    public function getJoinMap($tableName)
    {
        $tableName = strtolower($tableName);
        $rval      = array();
        foreach ($this->joinMap[$tableName] as $mapping)
        {
            $map = array();
            if (count($mapping) != 2)
                continue;
            foreach ($mapping as $tbl => $col)
            {
                if ($tbl == $tableName)
                {
                    $map['src_column'] = $col;
                }
                else
                {
                    $map['dest_column'] = $col;
                }
            }
            $rval[$this->origTableNames[$tbl]] = $map;
        }

        return $rval;
    }


    protected function buildJoinMap()
    {
        $totalfound = 0;
        foreach ($this->tables as $tableName => $th)
        {
            echo "processing $tableName...";
            $map = array();

            foreach ($th->columns as $column)
            {
                //handle the column references another table
                if ($this->colReferences[$this->gcs($tableName, $column)] != $this->gcs($tableName, $column))
                {
                    // add the single map for the direct relationship
                    $tableData = $this->decodeColumnSignature($this->colReferences[$this->gcs($tableName, $column)]);
                    $destTable = $tableData['table'];
                    $destCol   = $tableData['column'];

                    $map[] = array($tableName => $column, $destTable => $destCol);

                    foreach ($this->fkReferences[$this->colReferences[$this->gcs($tableName, $column)]] as $linkedTableCol)
                    {
                        $tableData = $this->decodeColumnSignature($linkedTableCol);
                        $destTable = $tableData['table'];
                        $destCol   = $tableData['column'];

                        // avoid the case where the fk points back to table
                        if ($destTable != $tableName)
                        {
                            $map[] = array($tableName => $column, $destTable => $destCol);
                        }
                    }
                }


                //handle the column has other things referencing itself
                if (!empty($this->fkReferences[$this->gcs($tableName, $column)]))
                {
                    foreach ($this->fkReferences[$this->gcs($tableName, $column)] as $linkedTableCol)
                    {
                        $tableData = $this->decodeColumnSignature($linkedTableCol);
                        $destTable = $tableData['table'];
                        $destCol   = $tableData['column'];

                        // avoid the case where points to self
                        if ($destTable != $tableName)
                        {
                            $map[] = array($tableName => $column, $destTable => $destCol);
                        }
                    }
                }


                //add the defined extra join mappings
                if (isset($this->extraJoinData[$tableName]))
                {
                    foreach ($this->extraJoinData[$tableName] as $mp)
                    {
                        $map[] = array($tableName => $mp['column'], $mp['dest_table'] => $mp['dest_col']);
                    }
                }
            }


            $this->joinMap[$tableName] = $map;
            echo "found " . count($map) . " possible joins with $tableName\n";
            $totalfound += count($map);
        }
    }


    // get all the table names
    public function getTables()
    {
        return array_keys($this->tables);
    }

    /**
     * shortened version of generateColumnSignature for internal use to make code more readable
     *
     * @param string $tableName
     * @param string $columnName
     *
     * @return string
     */
    protected function gcs($tableName, $columnName)
    {
        return $this->generateColumnSignature($tableName, $columnName);
    }


    /**
     * generates a signature for a column name...used as a key for the foreign key mappings
     *
     * @param string $tableName
     * @param string $columnName
     *
     * @return string
     */
    public function generateColumnSignature($tableName, $columnName)
    {
        return "$tableName::$columnName";
    }


    /**
     * breaks the signature from generateColumnSignature into its respective table and column
     *
     * @param string $signature genereated by generateColumnSignature
     *
     * @return array of table and column name
     */
    public function decodeColumnSignature($signature)
    {
        $pieces = explode("::", $signature);

        return array("table" => $pieces[0], "column" => $pieces[1]);
    }


    /**
     * read a schema xml and use it to load the code
     * currently only loads the ExtraJoinMappings from the file
     * @todo add support for the entire schema to load from xml
     * - chainable
     *
     * @param string $filename
     *
     * @return SchemaHelper
     */
    public function loadSchemaFromXml($filename)
    {
        if(file_exists($filename))
        {
            $schema = simplexml_load_file($filename);

            if (isset($schema->ExtraJoinMappings, $schema->ExtraJoinMappings->ExtraJoinMapping))
            {
                foreach ($schema->ExtraJoinMappings->ExtraJoinMapping as $mp)
                {
                    if (!isset($this->extraJoinData[strval($mp->SrcTable)]))
                    {
                        $this->extraJoinData[strval($mp->SrcTable)] = array();
                    }
                    if (!isset($this->extraJoinData[strval($mp->DestTable)]))
                    {
                        $this->extraJoinData[strval($mp->DestTable)] = array();
                    }

                    $this->extraJoinData[strval($mp->SrcTable)][]  = array('column' => strval($mp->SrcColumn), 'dest_table' => strval($mp->DestTable), 'dest_col' => strval($mp->DestColumn));
                    $this->extraJoinData[strval($mp->DestTable)][] = array('column' => strval($mp->DestColumn), 'dest_table' => strval($mp->SrcTable), 'dest_col' => strval($mp->SrcColumn));
                }
            }
        }

        return $this;
    }

    /**
     * set the file to write the schema xml out to
     *
     * @param string $filename
     *
     * @return SchemaHelper
     */
    public function writeSchemaToXml($filename)
    {
        $this->outputXmlFile = $filename;

        return $this;
    }

    /**
     * writes the output xml file
     */
    protected function outputSchema()
    {
        if (is_null($this->outputXmlFile))
            return;

        $data                         = array();
        $data['Tables']               = array();
        $data['DirectJoinMappings']   = array();
        $data['IndirectJoinMappings'] = array();
        $data['ExtraJoinMappings']    = array();

        foreach ($this->tables as $tablename => $thelper)
        {
            $tabledata                      = array();
            $tabledata['TableName']         = $this->origTableNames[$tablename];
            $tabledata['Columns']           = array();
            $tabledata['IndexPermutations'] = array();
            $tabledata['IndexDefinitions']  = array();
            $tabledata['ForeignKeys']       = array();

            $joins = $this->getJoinMap($tablename);

            foreach ($thelper->columns as $columnName)
            {
                $col                    = array();
                $col['Name']            = $columnName;
                $col['Type']            = $thelper->describeData[$columnName]['Type'];
                $col['Null']            = $thelper->describeData[$columnName]['Null'];
                $col['Default']         = $thelper->describeData[$columnName]['Default'];
                $tabledata['Columns'][] = $col;
            }
            if (!is_null($thelper->autoIncCol))
                $tabledata['AutoIncColumn'] = $thelper->autoIncCol;

            if (!empty($thelper->primaryKeyCols))
            {
                $tabledata['PrimaryKey'] = array('Columns' => array());
                foreach ($thelper->primaryKeyCols as $pkcolname)
                {
                    $tabledata['PrimaryKey']['Columns'][] = $pkcolname;
                }
            }

            if (!empty($thelper->uniqueKeyCols))
            {
                $tabledata['UniqueKey'] = array('Columns' => array());
                foreach ($thelper->uniqueKeyCols as $ucolname)
                {
                    $tabledata['UniqueKey']['Columns'][] = $ucolname;
                }
            }

            foreach ($thelper->indexes as $id => $index)
            {
                $indexdef = array('Id' => $id, 'Columns' => array());
                foreach ($index as $colname)
                {
                    $indexdef['Columns'][] = $colname;
                }
                $tabledata['IndexDefinitions'][] = $indexdef;
            }

            foreach ($thelper->indexPermutations as $permutation)
            {
                $ipermute = array('Columns' => array());
                foreach ($permutation as $colname)
                {
                    $ipermute['Columns'][] = $colname;
                }
                $tabledata['IndexPermutations'][] = $ipermute;
            }

            foreach ($thelper->foreignKeys as $srcCol => $link)
            {
                $fkdata                     = array();
                $fkdata['SrcColumn']        = $srcCol;
                $fkdata['Table']            = $this->origTableNames[$link['table']];
                $fkdata['DestColumn']       = $link['column'];
                $tabledata['ForeignKeys'][] = $fkdata;
            }

//			var_dump($tablename, $thelper, $joins);exit;
            $data['Tables'][] = $tabledata;
        }

        $colrefs = $this->colReferences;
        ksort($colrefs);

        foreach ($colrefs as $colsig => $refsig)
        {
            $mapping = array();
            if ($colsig != $refsig)
            {
                // map the direct references
                $srcData                      = $this->decodeColumnSignature($colsig);
                $refData                      = $this->decodeColumnSignature($refsig);
                $mapping['SrcTable']          = $this->origTableNames[$srcData['table']];
                $mapping['SrcColumn']         = $srcData['column'];
                $mapping['DestTable']         = $this->origTableNames[$refData['table']];
                $mapping['DestColumn']        = $refData['column'];
                $data['DirectJoinMappings'][] = $mapping;

                foreach ($this->fkReferences[$refsig] as $joinsig)
                {
                    $joinData                       = $this->decodeColumnSignature($joinsig);
                    $mapping['DestTable']           = $this->origTableNames[$joinData['table']];
                    $mapping['DestColumn']          = $joinData['column'];
                    $data['IndirectJoinMappings'][] = $mapping;
                }
            }
            else
            {
                $srcData              = $this->decodeColumnSignature($colsig);
                $refData              = $this->decodeColumnSignature($refsig);
                $mapping['SrcTable']  = $this->origTableNames[$srcData['table']];
                $mapping['SrcColumn'] = $srcData['column'];
                foreach ($this->fkReferences[$refsig] as $joinsig)
                {
                    $joinData                     = $this->decodeColumnSignature($joinsig);
                    $mapping['DestTable']         = $this->origTableNames[$joinData['table']];
                    $mapping['DestColumn']        = $joinData['column'];
                    $data['DirectJoinMappings'][] = $mapping;
                }
            }
        }

        // write out the extra defined joins
        foreach ($this->extraJoinData as $tablename => $arr)
        {
            $mapping                     = array('SrcTable' => $this->origTableNames[$tablename], 'SrcColumn' => $arr['column'], 'DestTable' => $this->origTableNames[$arr['dest_table']], 'DestColumn' => $arr['dest_col']);
            $data['ExtraJoinMappings'][] = $mapping;
        }

        $xml = $this->xml_pretty_printer($this->buildXMLFromArray($data, 'Schema'));

        $file = fopen($this->outputXmlFile, "w");
        if (!$file)
            throw new SchemaHelperException("Unable to open {$this->outputXmlFile} for writing");

        fwrite($file, $xml);
    }

    /**
     * Builds a complete simple xml object from an array of data and returns flattened xml text
     *
     * @param array            $data
     * @param string           $rootNode
     * @param SimpleXMLElement $xml
     *
     * @return xml string
     */
    protected function buildXMLFromArray(&$data, $rootNodeName = null)
    {
        if (is_null($rootNodeName))
        {
            $rootNodeName = 'Schema';
        }

        return $this->buildXMLFromArrayHelper($data, $rootNodeName, null)->asXML();
    }


    /**
     * Builds simple xml response from array.
     *
     * @param array            $data
     * @param string           $rootNode
     * @param SimpleXMLElement $xml
     *
     * @return SimpleXMLElement
     */
    protected function buildXMLFromArrayHelper(&$data, $rootNode, $xml = null)
    {
        if ($xml == null)
        {
            $xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><$rootNode />", "SimpleXMLElement", LIBXML_NOCDATA);
        }

        foreach ($data as $key => $value)
        {
            if (is_numeric($key))
            {
                $key = substr($rootNode, 0, strlen($rootNode) - 1);
            }

            $key = ucfirst(str_replace(" ", "_", $key));

            if (is_array($value))
            {
                $node = $xml->addChild($key);
                $this->buildXMLFromArrayHelper($value, $key, $node);
            }
            else
            {
                $value = htmlspecialchars(html_entity_decode($value, ENT_COMPAT, "UTF-8"));
                $xml->addChild($key, $value);
            }
        }

        return $xml;
    }


    /**
     * Takes xml as a string and returns it nicely indented
     *
     * @param string  $xml         The xml to beautify
     * @param boolean $html_output If the xml should be formatted for display on an html page
     *
     * @return string The beautified xml
     */
    public function xml_pretty_printer($xml, $html_output = false)
    {
        $xml_obj = new SimpleXMLElement($xml);
//		$xml_lines = explode("><", $xml_obj->asXML());
        $xml_lines    = explode("\n", str_replace("><", ">\n<", $xml_obj->asXML()));
        $indent_level = 0;

        $new_xml_lines = array();
        foreach ($xml_lines as $xml_line)
        {
            if (preg_match('#^(<[a-z0-9_:-]+((s+[a-z0-9_:-]+="[^"]+")*)?>.*<s*/s*[^>]+>)|(<[a-z0-9_:-]+((s+[a-z0-9_:-]+="[^"]+")*)?s*/s*>)#i', ltrim($xml_line)))
            {
                $new_line        = str_pad('', $indent_level * 4) . ltrim($xml_line);
                $new_xml_lines[] = $new_line;
            }
            elseif (preg_match('#^<[a-z0-9_:-]+((s+[a-z0-9_:-]+="[^"]+")*)?>#i', ltrim($xml_line)))
            {
                $new_line = str_pad('', $indent_level * 4) . ltrim($xml_line);
                $indent_level++;
                $new_xml_lines[] = $new_line;
            }
            elseif (preg_match('#<s*/s*[^>/]+>#i', $xml_line))
            {
                $indent_level--;
                if (trim($new_xml_lines[sizeof($new_xml_lines) - 1]) == trim(str_replace("/", "", $xml_line)))
                {
                    $new_xml_lines[sizeof($new_xml_lines) - 1] .= $xml_line;
                }
                else
                {
                    $new_line        = str_pad('', $indent_level * 4) . $xml_line;
                    $new_xml_lines[] = $new_line;
                }
            }
            else
            {
                $new_line        = str_pad('', $indent_level * 4) . $xml_line;
                $new_xml_lines[] = $new_line;
            }
        }

        $xml = join("\n", $new_xml_lines);

        return ($html_output) ? '<pre>' . htmlentities($xml) . '</pre>' : $xml;
    }

    public function getOriginalTableName($tableName)
    {
        return $this->origTableNames[strtolower($tableName)];
    }
}
