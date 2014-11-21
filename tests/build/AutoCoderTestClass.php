<?php

class AutoCoderTestClass
{
/*____PROCESSOR____CUSTOMFUNCTIONS____START____*/
    function overriddenFunction()
    {
        return "This function was overridden by custom code";
    }
/*____PROCESSOR____CUSTOMFUNCTIONS____END____*/


    function basicFunction()
    {
        $someVariable = 'some string value';
        $returnVal = array();
        $returnVal[] = $this->add(1,1,1);
        $returnVal[] = $this->add(2,2,2);
        $returnVal[] = $this->add(3,3,3);

        return $returnVal;
    }


    function add($var1, $var2, $var3)
    {
        return $var1 + $var2 + $var3;
    }


    function getString()
    {
        return 'Simple string replacement';
    }


    function multiply($var1, $var2, $var3)
    {
        return $var1 * $var2 * $var3;
    }


    function whichSubtemplate()
    {
        return 'singleSubTemplateFunction';
    }


    function singleSubTemplateFunction($arg1, $arg2, $arg3)
    {
        $returnVal = array();
        $myvar1 = $arg1;
        $myvar2 = $arg2;
        $myvar3 = $arg3;
        $myvar4 = $arg1 . $arg2 . $arg3;
        $myvar5 = "It worked!";

        $returnVal[] = $myvar1;
        $returnVal[] = $myvar2;
        $returnVal[] = $myvar3;
        $returnVal[] = $myvar4;
        $returnVal[] = $myvar5;

        return $returnVal;
    }



    function arraySubTemplateFunction1($arg1, $arg2, $arg3)
    {
        $returnVal = array();
        $myvar1 = $arg1;
        $myvar2 = $arg2;
        $myvar3 = $arg3;
        $myvar4 = $arg1 . $arg2 . $arg3;
        $myvar5 = "It worked!";

        $returnVal[] = $myvar1;
        $returnVal[] = $myvar2;
        $returnVal[] = $myvar3;
        $returnVal[] = $myvar4;
        $returnVal[] = $myvar5;

        return $returnVal;
    }


    function arraySubTemplateFunction2($arg1, $arg2, $arg3)
    {
        $returnVal = array();
        $myvar1 = $arg1;
        $myvar2 = $arg2;
        $myvar3 = $arg3;
        $myvar4 = $arg1 . $arg2 . $arg3;
        $myvar5 = "It worked!";

        $returnVal[] = $myvar1;
        $returnVal[] = $myvar2;
        $returnVal[] = $myvar3;
        $returnVal[] = $myvar4;
        $returnVal[] = $myvar5;

        return $returnVal;
    }


    function arraySubTemplateFunction3($arg1, $arg2, $arg3)
    {
        $returnVal = array();
        $myvar1 = $arg1;
        $myvar2 = $arg2;
        $myvar3 = $arg3;
        $myvar4 = $arg1 . $arg2 . $arg3;
        $myvar5 = "It worked!";

        $returnVal[] = $myvar1;
        $returnVal[] = $myvar2;
        $returnVal[] = $myvar3;
        $returnVal[] = $myvar4;
        $returnVal[] = $myvar5;

        return $returnVal;
    }


    function shouldExist1()
    {

    }

    function shouldExist2()
    {

    }

    function shouldExist3()
    {

    }


}


