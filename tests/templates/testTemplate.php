<?php
/*____PROCESSOR____COMMENT____START____*/
/*
 *
 * Variables:
 * __CLASS__NAME__      name of the generated class
 * __SIMPLE__VAR__      basic simple variable
 * __ARRAY__VAR__       basic array variable
 *      __VAL1__
 *      __VAL2__
 *      __VAL3__
 * __VAR__DEFS__        array of variable names and their corresponding values
 *      $__VAR__
 *      __VALUE__
 * __SINGLE__SUBTEMPLATE__  single subtemplate, should be an instance of autocoder configured with the subtemplate
 * __ARRAY__SUBTEMPLATE__   array of subtemplates, an array of autocoder instances, each configured for their subtemplates
 *
 *
 */


//function overridenFunction()
//{
//    return "This function was overridden by custom code";
//}



/*____PROCESSOR____COMMENT____END____*/

class __CLASS__NAME__
{
/*____PROCESSOR____CUSTOMFUNCTIONS____START____*/
/*____PROCESSOR____CUSTOMFUNCTIONS____END____*/

    /*____FUNCTION____START____*/
    function overriddenFunction()
    {
        return "This function should not end up in the final class as it should have been overridden by a custom function";
    }

    /*____FUNCTION____END____*/
    /*____FUNCTION____START____*/

    function basicFunction()
    {
        $__VAR__ = '__VALUE__';/*____REPEATABLE____:__VAR__DEFS__*/
        $returnVal = array();
        $returnVal[] = $this->add(__VAL1__,__VAL2__,__VAL3__);/*____REPEATABLE____:__ARRAY__VAR__*/

        return $returnVal;
    }

    /*____FUNCTION____END____*/
    /*____FUNCTION____START____*/

    function add($var1, $var2, $var3)
    {
        return $var1 + $var2 + $var3;
    }

    /*____FUNCTION____END____*/
    /*____FUNCTION____START____*/

    function getString()
    {
        return '__SIMPLE__VAR__';
    }

    /*____FUNCTION____END____*/
/*____SUBTEMPLATE____:__SINGLE__SUBTEMPLATE__*/

/*____SUBTEMPLATE____:__ARRAY__SUBTEMPLATE__*//*____REPEATABLE____:__ARRAY__SUBTEMPLATES__*/
}


