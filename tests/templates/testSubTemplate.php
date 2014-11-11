/*____PROCESSOR____COMMENT____START____*/
<?php
/*
 *
 * Variables:
 *
 * __FUNCTION__NAME__      function name for the function that this
 * $__FUNCTION__ARGS__
 * __VAR__DEFS__        array of variable names and their corresponding values
 *      $__VAR__
 *      __VALUE__
 *
 */
// Anything included inside of the preprocessor comment tags will not be included in the final rendering of the (sub)template
class NonexistentClass extends __CLASS__NAME__  // the extends in this definition is to aid in IDE auto completions
{

/*____PROCESSOR____COMMENT____END____*/
    /*____FUNCTION____START____*/

    function basicFunction()
    {
        // this function should not be in the final version as it was already defined in the main template
        return null;
    }

    /*____FUNCTION____END____*/
    /*____FUNCTION____START____*/

    function multiply($var1, $var2, $var3)
    {
        return $var1 * $var2 * $var3;
    }

    /*____FUNCTION____END____*/
    /*____FUNCTION____START____*/

    function whichSubtemplate()
    {
        return '__FUNCTION__NAME__';
    }

    /*____FUNCTION____END____*/
    /*____FUNCTION____START____*/

    function __FUNCTION__NAME__($__FUNCTION__ARGS__)
    {
        $returnVal = array();
        $__VAR__ = __VALUE__;/*____REPEATABLE____:__VAR__DEFS__*/

        $returnVal[] = $__VAR__;/*____REPEATABLE____:__VAR__DEFS__*/

        return $returnVal;
    }

    /*____FUNCTION____END____*/
/*____PROCESSOR____COMMENT____START____*/
}
/*____PROCESSOR____COMMENT____END____*/
