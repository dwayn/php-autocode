<?php
use PHPAutocoder\Autocoder;


class AutocoderTest extends PHPUnit_Framework_TestCase
{
    public function testAutocoderTemplating()
    {
        $main = new Autocoder();
        $outfilename = tempnam('/tmp', "autocoder_test_");
        $startContents = file_get_contents(realpath(dirname(__FILE__)).'/build/AutoCoderTestClass.php');
        file_put_contents($outfilename, $startContents);
        $main->setTemplate(realpath(dirname(__FILE__)).'/templates/testTemplate.php');
        $main->setOutputFile($outfilename);
        $main->assign('__CLASS__NAME__', 'AutoCoderTestClass');
        $main->assign('__SIMPLE__VAR__', 'Simple string replacement');


        $addArrays = array(
            array('__VAL1__' => 1,'__VAL2__' => 1,'__VAL3__' => 1),
            array('__VAL1__' => 2,'__VAL2__' => 2,'__VAL3__' => 2),
            array('__VAL1__' => 3,'__VAL2__' => 3,'__VAL3__' => 3),
        );
        $main->assign('__ARRAY__VAR__', $addArrays);
        $main->assign('__VAR__DEFS__', array(array('$__VAR__' => '$someVariable', '__VALUE__' => 'some string value')));
        $main->assign('__DISPLAY__TRUE__', true);
        $main->assign('__DISPLAY__FALSE__', false);


        $singleSubtemplate = new Autocoder();
        $singleSubtemplate->setTemplate(realpath(dirname(__FILE__)).'/templates/testSubTemplate.php');
        $singleSubtemplate->assign('__FUNCTION__NAME__', 'singleSubTemplateFunction');
        $singleSubtemplate->assign('$__FUNCTION__ARGS__', '$arg1, $arg2, $arg3');
        $vars = array(
            array('$__VAR__' => '$myvar1', '__VALUE__' => '$arg1'),
            array('$__VAR__' => '$myvar2', '__VALUE__' => '$arg2'),
            array('$__VAR__' => '$myvar3', '__VALUE__' => '$arg3'),
            array('$__VAR__' => '$myvar4', '__VALUE__' => '$arg1 . $arg2 . $arg3'),
            array('$__VAR__' => '$myvar5', '__VALUE__' => '__CHAINED__SUBSTITUTION__'),
        );
        $singleSubtemplate->assign('__VAR__DEFS__', $vars);
        $singleSubtemplate->assign('__CHAINED__SUBSTITUTION__', '"It worked!"');
        $main->assign('__SINGLE__SUBTEMPLATE__', $singleSubtemplate);



        $arraySubtemplate = array();
        for($x =1; $x < 4; $x++)
        {
            $st = new Autocoder();
            $st->setTemplate(realpath(dirname(__FILE__)).'/templates/testSubTemplate.php');
            $st->assign('__FUNCTION__NAME__', 'arraySubTemplateFunction'.$x);
            $st->assign('$__FUNCTION__ARGS__', '$arg1, $arg2, $arg3');
            $vars = array(
                array('$__VAR__' => '$myvar1', '__VALUE__' => '$arg1'),
                array('$__VAR__' => '$myvar2', '__VALUE__' => '$arg2'),
                array('$__VAR__' => '$myvar3', '__VALUE__' => '$arg3'),
                array('$__VAR__' => '$myvar4', '__VALUE__' => '$arg1 . $arg2 . $arg3'),
                array('$__VAR__' => '$myvar5', '__VALUE__' => '__CHAINED__SUBSTITUTION__'),
            );
            $st->assign('__VAR__DEFS__', $vars);
            $st->assign('__CHAINED__SUBSTITUTION__', '"It worked!"');
            $arraySubtemplate[] = $st;
        }

        

        $main->assign('__ARRAY__SUBTEMPLATE__', $arraySubtemplate);


//        var_dump($main);

        $main->enableFileWrite();
        $main->disableStdout();
        $main->render();

        $generatedContents = file_get_contents($outfilename);
        $expectedContents = file_get_contents(realpath(dirname(__FILE__)).'/build/AutoCoderTestClass.php');

        $this->assertEquals($expectedContents, $generatedContents);
        unlink($outfilename);
    }
}
