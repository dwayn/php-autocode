<?php
namespace PHPAutocoder;
/**
 * Code building templating engine, usefule for generating code for things that
 *	can be programmatically written (ie. most code can be copy/pasted)
 *
 * @author dwayn
 */
class Autocoder
{
	const PROCESSOR_COMMENT_START	= 1;
	const PROCESSOR_COMMENT_END 	= 2;
	const REPEATABLE_LINE		= 4;
	const SUBTEMPLATE		= 8;
	const CUSTOM_FUNCTIONS_START	= 16;
	const CUSTOM_FUNCTIONS_END	= 32;
	const FUNCTION_START		= 64;
	const FUNCTION_END		= 128;

	protected $definedFunctions;
	protected $templateName;

	protected $outputBuffer;
	protected $template;

	protected $replacements;

	protected $outputFile;
    protected $writeFile;
    protected $writeStdout = true;
	protected $oldFile;

	protected $customCode;

	protected $functionBuffer;
	protected $currentFunctionName;
	protected $infunction;

    protected $currentTemplateLineNumber = 0;

	public function  __construct()
	{
		$this->outputBuffer = array();
		$this->writeFile = false;
		$this->definedFunctions = array();
		$this->infunction = false;
		$this->customCode = array();
	}

    public function disableStdout($disabled = true)
    {
        $this->writeStdout = !$disabled;
        return $this;
    }

	public function enableFileWrite($enabled = true)
	{
		$this->writeFile = $enabled;
        return $this;
	}

	public function assign($name, $value)
	{
		$this->replacements[$name] = $value;
        return $this;
	}

	public function writeFile()
	{
		if($this->writeFile)
		{
			$outfile = fopen($this->outputFile, "w");
		}

		foreach($this->outputBuffer as $line)
		{
			$line = str_replace("\n", "", $line);
            if($this->writeStdout)
            {
                echo $line . "\n";
            }
			if($this->writeFile)
			{
				fwrite($outfile, $line . "\n");
			}
		}

		if($this->writeFile)
		{
			fclose($outfile);
		}

        return $this;
	}

    public function setTemplate($filename)
    {
        $this->templateName = realpath($filename);
        return $this;
    }

    public function setOutputFile($filename = null)
    {
        if(is_null($filename))
        {
            return $this;
        }
        $this->readOldFile($filename);
        $this->loadCustomCode();
        if(file_exists(dirname($filename)))
        {
            $this->outputFile = $filename;
            $this->writeFile = true;
        }
        return $this;
    }

    public function process()
	{
//        var_dump($this->definedFunctions);
		$this->outputBuffer = array();
		$this->template = fopen($this->templateName, "r");

		while($line = $this->readline())
		{
			$tag = $this->identifyTags($line);

			if($tag > 0)
			{
				$processed = $this->processTaggedLine($tag, $line);
				$this->writeToBuffer($processed);
			}
			else
			{
				$processed = $this->doLineReplacement($line, $this->replacements);
				$this->writeToBuffer(array($processed));
			}
		}
		fclose($this->template);
		//clean up if in last function
		if($this->infunction)
		{
			if(!isset($this->definedFunctions[$this->currentFunctionName]))
			{
				$this->definedFunctions[$this->currentFunctionName] = 1;
				$this->flushFunctionBuffer();
			}
			$this->functionBuffer = array();
			$this->currentFunctionName = '';
			$this->infunction = false;

		}

		// scrub out all the preprocessor tags except custom functions
		foreach($this->outputBuffer as &$line)
		{
			$tag = $this->identifyTags($line);
			if($tag > 0 && $tag != self::CUSTOM_FUNCTIONS_START && $tag != self::CUSTOM_FUNCTIONS_END)
			{
				$line = $this->scrubPreProcessorComments($line);
			}
		}
        return $this;
	}

	protected function scrubPreProcessorComments($line)
	{
		return preg_replace('/\/\*____.*?____(:?:__.*?__)*\*\//',"",$line);
	}

	protected function writeToBuffer($lines)
	{
		if($this->infunction)
		{
			foreach($lines as $l)
			{
				$this->functionBuffer[] = $l;
			}
		}
		else
		{
			if(!is_array($lines))
			{
			    throw new Exception("dammit",1);
			}
			foreach($lines as $l)
			{
				$this->outputBuffer[] = $l;
			}
		}
	}

	protected function flushFunctionBuffer()
	{
		foreach($this->functionBuffer as $l)
		{
			$this->outputBuffer[] = $l;
		}
	}


	protected function processTaggedLine($tag, $line)
	{
		if($tag & self::PROCESSOR_COMMENT_START)
		{
			return $this->readComment();
		}
		elseif($tag & self::CUSTOM_FUNCTIONS_START)
		{
			$rval = array($line);

			foreach($this->customCode as $cc)
			{
				$rval[] = $cc;
			}
			// read and ignore the template up through the close custom functions tag
			while($l = $this->readline())
			{
				if($this->identifyTags($l) & self::CUSTOM_FUNCTIONS_END)
				{
					$rval[] = $l;
					break;
				}
			}

			return $rval;
		}
		elseif($tag & self::FUNCTION_START)
		{
			$this->infunction = true;
			return array();
		}
		elseif($tag & self::FUNCTION_END)
		{
			if(!isset($this->definedFunctions[$this->currentFunctionName]))
			{
				$this->definedFunctions[$this->currentFunctionName] = 1;
				$this->flushFunctionBuffer();
			}
			$this->functionBuffer = array();
			$this->currentFunctionName = '';
			$this->infunction = false;

			return array();
		}
		elseif($tag & self::REPEATABLE_LINE)
		{
			return $this->processRepeatable($tag, $line);
		}
		elseif($tag & self::SUBTEMPLATE)
		{
			$subMetaVal = $this->parseMetaTag(self::SUBTEMPLATE, $line);
			return $this->processSubTemplate($this->replacements[$subMetaVal]);
		}
	}

	protected function parseMetaTag($tag, $line)
	{
		$metaVal = '';
		$matchText = '';
		switch($tag)
		{
			case self::REPEATABLE_LINE:
				$matchText = 'REPEATABLE';
				break;
			case self::SUBTEMPLATE:
				$matchText = 'SUBTEMPLATE';
		}
		if(preg_match('/\/\*____'.$matchText.'____:(.*?)\*\//', $line, $matches))
		{
			$metaVal = $matches[1];
		}

		return $metaVal;
	}

	protected function processRepeatable($tag, $line)
	{
		$buffer = array();
		$metaVal = $this->parseMetaTag(self::REPEATABLE_LINE, $line);
		if($tag & self::SUBTEMPLATE)
		{
			$subMetaVal = $this->parseMetaTag(self::SUBTEMPLATE, $line);
			foreach($this->replacements[$subMetaVal] as $st)
			{
				$subbuffer = $this->processSubTemplate($st);
				foreach($subbuffer as $sb)
				{
					$buffer[] = $sb;
				}
			}
		}
		else
		{
            if(!isset($this->replacements[$metaVal]))
                throw new Exception("Missing assignment for $metaVal defined on line {$this->currentTemplateLineNumber} in {$this->templateName}");
			foreach($this->replacements[$metaVal] as $replarray)
			{
				$l = $this->doLineReplacement($line, $replarray);
				$buffer[] = $l;
			}
		}
		return $buffer;
	}

	protected function processSubTemplate(AutoCoder $subAutoCoder)
	{
		// pass defined state to sub template
		$subAutoCoder->infunction = $this->infunction;
		$subAutoCoder->functionBuffer = $this->functionBuffer;
		$subAutoCoder->currentFunctionName = $this->currentFunctionName;
		$subAutoCoder->definedFunctions = $this->definedFunctions;

		// process sub template and snag output buffer
		$subAutoCoder->process();
		$buffer = $subAutoCoder->outputBuffer;

		// get the updated current state back from sub template
		$this->infunction = $subAutoCoder->infunction;
		$this->currentFunctionName = $subAutoCoder->currentFunctionName;
		$this->functionBuffer = $subAutoCoder->functionBuffer;
		$this->definedFunctions = $subAutoCoder->definedFunctions;

		return $buffer;
	}

	protected function readComment()
	{
		while($line = $this->readline())
		{
			if($this->identifyTags($line) & self::PROCESSOR_COMMENT_END)
			{
				break;
			}
		}
		return array();
	}


	protected function readline()
	{
        $this->currentTemplateLineNumber++;
		$line = fgets($this->template);
		return $line;
	}

	protected function readOldFile($filename)
	{
		if(file_exists($filename))
		{
			$this->oldFile = file_get_contents($filename);
		}
	}

	protected function setDefinedFunctions($functionNameArray)
	{
		$this->definedFunctions = $functionNameArray;
	}

	protected function loadCustomCode()
	{
		if(is_null($this->oldFile))
		{
			return array();
		}
		$lines = explode("\n", $this->oldFile);

		$this->customCode = array();
		$inside = false;

		foreach($lines as $l)
		{
			$tag = $this->identifyTags($l);
			if($tag & self::CUSTOM_FUNCTIONS_END)
			{
				$inside = false;
			}

			if($inside)
			{
				$this->customCode[] = $l;
				if(preg_match('/.*?\sfunction[\s]+([\w]+)[\s]*\(.*/i', $l, $matches))
				{
					$this->definedFunctions[$matches[1]] = "CUSTOM";
				}
			}

			if($tag & self::CUSTOM_FUNCTIONS_START)
			{
				$inside = true;
			}
		}

	}


	protected function identifyTags($line)
	{
		$rval = 0;
		if(preg_match('/\/\*____PROCESSOR____COMMENT____START____\*\//', $line, $matches))
		{
			$rval += self::PROCESSOR_COMMENT_START;
		}
		if(preg_match('/\/\*____PROCESSOR____COMMENT____END____\*\//', $line, $matches))
		{
			$rval += self::PROCESSOR_COMMENT_END;
		}
		if(preg_match('/\/\*____PROCESSOR____CUSTOMFUNCTIONS____START____\*\//', $line, $matches))
		{
			$rval += self::CUSTOM_FUNCTIONS_START;
		}
		if(preg_match('/\/\*____PROCESSOR____CUSTOMFUNCTIONS____END____\*\//', $line, $matches))
		{
			$rval += self::CUSTOM_FUNCTIONS_END;
		}
		if(preg_match('/\/\*____REPEATABLE____:(.*?)\*\//', $line))
		{
			$rval += self::REPEATABLE_LINE;
		}
		if(preg_match('/\/\*____SUBTEMPLATE____:(.*?)\*\//', $line))
		{
			$rval += self::SUBTEMPLATE;
		}
		if(preg_match('/\/\*____FUNCTION____START____\*\//', $line, $matches))
		{
			$rval += self::FUNCTION_START;
		}
		if(preg_match('/\/\*____FUNCTION____END____\*\//', $line, $matches))
		{
			$rval += self::FUNCTION_END;
		}
		return $rval;
	}




	protected function doLineReplacement($line, &$replacementData)
	{
		$rval = $line;
        foreach($replacementData as $key => $value)
        {
            if(strpos($rval, $key) !== false)
            {
                $rval = str_replace($key, $value, $rval);
            }
        }
        // look for any global defined replacements that need to be made
        foreach($this->replacements as $key => $value)
        {
            // @TODO look into reimplementing the tagged line processing such that it works like a stack and it recurses, each meta tag gets stripped from the line
            //      this would allow the ability to nest looping, scopes, etc

            // we can skip arrays because those are dependant on preprocessor tags
            if(strpos($rval, $key) !== false && !is_array($value))
            {
                $rval = str_replace($key, $value, $rval);
            }
        }

		if($this->infunction)
		{
			if(preg_match('/.*?\sfunction[\s]+([\w]+)[\s]*\(.*/i', $rval, $matches))
			{
				$this->currentFunctionName = $matches[1];
			}
		}

		return $rval;
	}

}



