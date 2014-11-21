<?php
require_once realpath(dirname(__FILE__).'/../../../Autoload.php');
use PHPAutocoder\Helpers\Mysql\TableHelper;
use PHPAutocoder\Helpers\Mysql\SchemaHelper;
use PHPAutocoder\Autocoder;

$options = getopt('h:u:p:s:o:x:t::');
$host = null;
$user = null;
$pass = null;
$schema = null;
$dir = null;
$xmlfilename = null;
$type = 'simple';


foreach($options as $name => $value)
{
    switch($name)
    {
        case "h":
            $host = $value;
            break;
        case "u":
            $user = $value;
            break;
        case "p":
            $pass = $value;
            break;
        case "s":
            $schema = $value;
            break;
        case "o":
            $dir = realpath($value);
            break;
        case "x":
            $xmlfilename = realpath($value);
            break;
        case "t":
            if($value == 'complex' || $value == 'simple')
            {
                $type = $value;
            }
            else
            {
                usage();
                exit;
            }
            break;
        default:
            usage();
            break;
            break;
    }

}

if($host === null || $user === null || $pass === null || $schema === null || $dir === null)
{
    usage();
    exit;
}


function usage()
{
    echo "Usage: php build_daos_for_mysql.php -h MYSQL_HOST -u USER -p PASS -s SCHEMA_NAME -o OUTPUT_DIR [-x XMLSCHEMA_FILENAME] [-t simple|complex]\n\n";
}


$dsn = "mysql:dbname=$schema;host=$host";
$db  = new PDO($dsn, $user, $pass, array());

$sh = new SchemaHelper($db);
if($xmlfilename)
    $sh->loadSchemaFromXml($xmlfilename)->writeSchemaToXml($xmlfilename);

$sh->process();


if($type == 'simple')
{
    $daoTemplateFile      = realpath(dirname(__FILE__) . '/../templates/SimpleDaoTemplate.php');
    $daoGetByTemplateFile = realpath(dirname(__FILE__) . '/../templates/SimpleDaoGetByTemplate.php');
}
elseif($type == 'complex')
{
    $daoTemplateFile      = realpath(dirname(__FILE__) . '/../templates/ComplexDaoTemplate.php');
    $daoGetByTemplateFile = realpath(dirname(__FILE__) . '/../templates/ComplexDaoGetByTemplate.php');
}

foreach ($sh->tables as $tablename => $thelper)
{
    $tablename = $sh->getOriginalTableName($tablename);
    $classname = implode("", explode(" ", ucwords(str_replace("_", " ", strtolower($tablename))))) . "Dao";
    $filename  = $dir . "/$classname.php";

    echo "writing $classname to $filename\n";


    $primaryGetBinds = array();
    $primaryKeysList = "";
    $hasPkey = false;
    if(count($thelper->primaryKeyCols))
    {
        $primaryKeysList = "'" . implode("','", $thelper->primaryKeyCols) . "'"; //***
        foreach ($thelper->primaryKeyCols as $key)
        {
            $primaryGetBinds[] = array('__COLNAME__' => $key, '$__COLVAL__' => "\$$key");
        }
        $hasPkey = true;
    }
    else
    {
        echo "Table: $tablename does not have a primary key, skipping get and update functions";
    }
    $tableColsList = "'" . implode("','", $thelper->columns) . "'"; //***

    $functionKeys = array(); //***
    $pkindexid    = $thelper->calculateIndexId($thelper->primaryKeyCols);
    foreach ($thelper->indexPermutations as $key => $value)
    {
        if ($key == $pkindexid)
        {
            $funcName = "primary";
        }
        else
        {
            $funcName = "getBy" . bumpyCaseIt(implode("_", $value));
        }
        if ($funcName == 'getByCreated' || $funcName == 'getByUpdated')
        {
            continue;
        }

        $functionKeys[] = array("__FUNCTION__NAME__" => $funcName, "__TABLE__COLS__" => "'" . implode("','", $value) . "'"); //***
    }

    $primaryKeyParams = "";
    $primaryCacheKey = "";
    if($hasPkey)
    {
        $primaryKeyParams = "\$" . implode(", \$", $thelper->primaryKeyCols); //***
        $primaryCacheKey  = "\$" . implode(":\$", $thelper->primaryKeyCols); //***
    }


    $temp = array();
    foreach ($thelper->columns as $c)
    {
        if ($c != 'updated' && $c != 'created' && $c != $thelper->autoIncCol)
        {
            $txt        = $c;
            $nullable   = ($thelper->describeData[$c]['Null'] == 'YES');
            $defaultVal = $thelper->describeData[$c]['Default'];
            if ($nullable)
                $txt .= " = null";
            elseif (!is_null($defaultVal))
            {
                // check to see if the default value is a function call in mysql, if it is then just set null
                // instead of the value of default (e.g. see guid column of business_businesses table)
                if (preg_match('/\w+\(.*?\)/', $thelper->describeData[$c]['Default']))
                {
                    $txt .= " = null";
                }
                else
                {
                    // this checks to see if it is a numeric type and doesn't add the quotes around the default val
                    if (preg_match('/\w*int\(\d+\)|float|double|real|decimal|numeric/i', $thelper->describeData[$c]['Type']))
                        $txt .= " = $defaultVal";
                    else
                        $txt .= " = '" . str_replace("'", "\\'", $defaultVal) . "'"; // have to properly escape the quotes so there is no code errors
                }
            }

            $temp[] = $txt;
        }
    }
    $createFunctionArgs = "\$" . implode(", \$", $temp); //***


    $createFunctionSetsQuery = array(); //***
    $createFunctionBinds     = array(); //***
    foreach ($thelper->columns as $c)
    {
        if ($c != $thelper->autoIncCol)
        {
            $createFunctionSetsQuery[] = "`$c`=:$c";
            $t                         = array('__COLNAME__' => $c, '$__COLVAL__' => "\$$c");
            if ($c == 'updated' || $c == 'created')
            {
                $t['$__COLVAL__'] = 'date("Y-m-d H:i:s")';
            }
            $createFunctionBinds[] = $t;
        }
    }
    $createFunctionSetsQuery = implode(", ", $createFunctionSetsQuery); //***


    $lastInsertIdCode = ''; //***
    if (!is_null($thelper->autoIncCol))
    {
        $lastInsertIdCode = "\${$thelper->autoIncCol} = \$this->db->lastInsertId();";
    }

    if($hasPkey)
        $createFunctionLookupArgs = "\$" . implode(", \$", $thelper->primaryKeyCols); //***

    $joinMaps = array();
    foreach ($sh->getJoinMap($tablename) as $tbl => $cols)
    {
        if ($tbl == $tablename)
            continue;
        $joinMaps[] = array('__DEST__TABLE__' => $tbl, '__SRC__COL__' => $cols['src_column'], '__DEST__COL__' => $cols['dest_column']);
    }

    // build sub templates
    $getBySubtemplates = array();
    foreach ($thelper->indexPermutations as $key => $value)
    {
        if ($key == $pkindexid)
        {
            continue;
        }

        $fname = "getBy" . bumpyCaseIt(implode("_", $value));
        if ($fname == 'getByCreated' || $fname == 'getByUpdated')
        {
            continue;
        }

        $ac = new AutoCoder();
        $ac->setTemplate($daoGetByTemplateFile);
        $ac->assign("__FUNCTION__NAME__", $fname);
        $ac->assign('$__FUNCTION__ARGS__', '$' . implode(', $', $value));
        $ac->assign('$__CACHE__KEY__ARGS__', '$' . implode(':$', $value));
        $ac->assign('__TABLE__NAME__', $tablename);

        $binds  = array();
        $wheres = array();
        foreach ($value as $col)
        {
            $wheres[] = "$tablename.`$col`=:$col";
            $binds[]  = array('__COLNAME__' => "$col", '$__COLVAL__' => "\$$col");
        }
        $wheres = implode(" AND ", $wheres);
        $ac->assign('__SELECT__WHERES__', $wheres);
        $ac->assign('__SELECT__BINDS__', $binds);
        $ac->assign('__CLASS__NAME__', $classname);

        $getBySubtemplates[] = $ac;
    }

    $main = new AutoCoder();
    $main->setTemplate($daoTemplateFile);
    $main->setOutputFile($filename);
    $main->assign('__CLASS__NAME__', $classname);
    $main->assign('__PRIMARY__KEYS__LIST__', $primaryKeysList);
    $main->assign('__PKGET__BINDS__', $primaryGetBinds);
    $main->assign('__TABLE__COLUMNS__LIST__', $tableColsList);
    $main->assign('__FUNCTION__KEYS__', $functionKeys);
    $main->assign('$__PRIMARY__KEY__PARMS__', $primaryKeyParams);
    $main->assign('__TABLE__NAME__', $tablename);
    $main->assign('$__CREATE__FUNCTION__ARGS__', $createFunctionArgs);
    $main->assign('__CREATE__FUNCTION__SETS__QUERY__', $createFunctionSetsQuery);
    $main->assign('__CREATE__FUNCTION__BINDS__', $createFunctionBinds);
    $main->assign('__LAST__INSERT__ID__CODE__;', $lastInsertIdCode);
    $main->assign('$__CREATE__FUNCTION__LOOKUP__ARGS__', $createFunctionLookupArgs);
    $main->assign('__GET__BY__SUBTEMPLATES__', $getBySubtemplates);
    $main->assign('__JOIN__MAPS__', $joinMaps);
    $main->assign('__HAS__PKEY__', $hasPkey);
    $main->assign('__NO__PKEY__', !$hasPkey);
    $main->assign('__HAS__INDEXES__', (count($thelper->indexPermutations) > 0));
    $main->enableFileWrite();
    $main->disableStdout();
    $main->render();

}


/*
 * turns underscored names into nice bumpy case: some_table_name -> SomeTableName
 */
function bumpyCaseIt($underscoredName)
{
    return str_replace(" ", "", ucwords(str_replace("_", " ", strtolower($underscoredName))));
}
