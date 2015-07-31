<?

class SohaBase
	{
	const DELIM_TEXT = '@';
	const DELIM_CFG = '~';
	const DELIM_DEFINE = '$';
	const DELIM_VALUE = '=';
	
	public static function domElementToString($el)
		{
		return $el->ownerDocument->saveHTML($el);
		}
	
	public static function bindValues($s, $values)
		{
		//$s = '1 {= var =} 2 \{= var =} 3 \\{= var =} 4 \\\{= var =} 5 \\\\{= var =} 6 \\\\\{= var =} 7 \\\\\\{= var =} 8 \\\\\\\{= var =} 9 \\\\\\\\{= var =} 10';
		foreach ($values as $name => $value)
			$s = preg_replace('@(?<!\\\\)((?:\\\\\\\\)*)\{= *'.preg_quote($name, '/').' *=\}@u', '$1'.$value, $s);
		return str_replace('\\\\', '\\', $s);
		}
	
	public static function parseParams(&$s, $closeBracket)
		{
		$res = array();
		$counter = 0;
		while ($counter++ < 1000)
			{
			$s = preg_replace('/^[,\s]+/u', '', $s);
			$paramName = self::getUntil($s, array('=', $closeBracket));
			$paramName->word = trim($paramName->word);
			if ($paramName->delimiter == $closeBracket || trim($paramName->word) == '')
				break;
			$paramName = $paramName->word;
			$paramValue = null;
			$s = ltrim($s);
			if ($s[0] == '"' || $s[0] == "'")
				{
				$delimiter = $s[0];
				$s = substr($s, 1);
				$paramValue = self::getUntil($s, $delimiter);
				$res[$paramName] = $paramValue->word;
				}
			else
				{
				$paramValue = self::getUntil($s, array(',', $closeBracket), $closeBracket);
				$paramValue = trim($paramValue->word);
				$temp = null;
				if (preg_match('/^(?:(true|1)|(false|0))$/ui', $paramValue, $temp))
					{
					$paramValue = !!$temp[1];
					$res[$paramName] = $paramValue;
					}
				else if ($paramValue)
					$res[$paramName] = $paramValue;
				}
			}
		
		return $res;
		}
	
	public static function cutComments($s)
		{
		while (preg_match('/^(\/\*|\/\/.*(?:[\r\n]|$))/u', $s, $match))
			{
			$s = ltrim(preg_replace('/^(\/\*[\s\S]*?(?:\*\/|$)|\/\/.*(?:[\r\n]|$))/u', '', ltrim($s)));
			//var_dump($match);
			//die($s);
			}
		return $s;
		}
	
	protected static function normAttrName($name)
		{
		return trim($name);
		}
	
	protected static function getDelimitersList($delimiter, $regexpDelimiter = '/')
		{
		if (!$delimiter || $delimiter === true)
			$delimiter = array('"');
		if (!is_array($delimiter))
			$delimiter = array($delimiter);
		$delimiter = array_filter($delimiter);
		foreach ($delimiter as &$v)
			$v = preg_quote($v, $regexpDelimiter);
		unset($v);
		return array_unique($delimiter);
		}
	
	public static function unescape($s, $delimiter = '"')
		{
		$delimiter = self::getDelimitersList($delimiter, '@');
		if (!in_array('\\\\', $delimiter))
			$delimiter[] = '\\\\';
		return preg_replace('@\\\\('.implode('|', $delimiter).')@ui', '$1', $s.'');
		}
	
	public static function getUntil(&$s, $delimiter = '"', $saveDelimiter = false)
		{
		$re = '/\\G(.*?(?<!\\\\)(?:\\\\\\\\)*)('.implode('|', self::getDelimitersList($delimiter)).'|$)/us';
		$match = null;
		$res = null;
		if (preg_match($re, $s, $match))
			{
			if (!is_array($saveDelimiter))
				{
				if (is_string($saveDelimiter))
					$saveDelimiter = $saveDelimiter == $match[2];
				else
					$saveDelimiter = !!$saveDelimiter;
				}
			else
				$saveDelimiter = in_array($match[2], $saveDelimiter);
			$saveDelimiter = $saveDelimiter ? strlen($match[2]) : 0;
			$s = substr($s, strlen($match[0]) - $saveDelimiter);
			return new SohaMatch(self::unescape($match[1], $delimiter), $match[2], $s);
			}
		else
			return new SohaMatch(self::unescape($match[1], $delimiter), '', '');
		}
	}

class Soha extends SohaBase
	{
	public $cfg = array(
		'charset' => 'utf-8',
		'delimText' => '@',
		);
	public $doc;
	protected $templateValues = array();
	
	
	public function __construct($cfg = null)
		{
		$this->cfg['delimText'] = self::DELIM_TEXT;
		$this->setCfg($cfg);
		$this->doc = new DOMDocument('1.0', $cfg['charset'] ? : 'utf-8');
		}
	
	
	public function setCfg($cfg = null, $value = null)
		{
		if (is_array($cfg))
			return $this->cfg = array_merge($this->cfg, $cfg);
		else
			{
			$cfg = trim($cfg .= '') ? $cfg : false;
			if ($cfg && $value)
				return $this->cfg[$cfg] = $value;
			elseif ($cfg)
				return $this->cfg[$cfg];
			else
				return $this->cfg;
			}
		}
	
	
	protected function append($node, DOMElement $appendTo = null)
		{
		if (!$appendTo)
			$appendTo = $this->doc;
		
		if (is_a($node, 'SohaNode'))
			$node = $node->create($appendTo->ownerDocument);
		elseif (!is_a($node, 'DOMElement'))
			{
			$node = htmlspecialchars_decode($node.'');
			$node = $this->doc->createTextNode(preg_replace('/^\s|\s$/ui', '', $node));
			}
		$appendTo->appendChild($node);
		}
	
	
	public function parse($s, DOMElement $parent = null, $alone = false)
		{
		$lastNode = null;
		
		$s = ltrim($s);
		$sop = null;
		
		while (strlen($s) > 0)
			{
			$delimText = $this->cfg['delimText'];
			$s = self::cutComments($s);
			
			$re = preg_quote('{'.self::DELIM_CFG, '/').'|'
				. preg_quote('{'.$delimText, '/').'|'
				. preg_quote('{'.self::DELIM_DEFINE, '/').'|'
				//. preg_quote('{'.self::DELIM_VALUE, '/').'|'
				. '\{|,|>|\}'
				;
			
			if (preg_match('/^('.$re.')/ui', $s, $sop))
				$sop = $sop[1];
			elseif (!$s)
				break;
			
			if ($sop == '}')
				{
				if (!$alone)
					$s = substr($s, 1);	#strlen($sop)
				break;
				}
			elseif ($sop == '{' || $sop == '>')
				{
				$s = substr($s, 1);	#strlen($sop)
				$s = $this->parse($s, $lastNode, $sop == '>');
				}
			elseif ($sop == '{'.self::DELIM_CFG)
				{
				$s = substr($s, 2);	#strlen($sop)
				$values = self::parseParams($s, self::DELIM_CFG.'}');
				$this->cfg = array_merge($this->cfg, $values);
				}
			elseif ($sop == '{'.self::DELIM_DEFINE)
				{
				$s = substr($s, 2);	#strlen($sop)
				$values = self::parseParams($s, self::DELIM_DEFINE.'}');
				$this->templateValues = array_merge($this->templateValues, $values);
				}
			elseif ($sop == '{'.$delimText)
				{
				$s = substr($s, strlen($delimText) + 1);	#strlen('{'.$delimText)
				//var_dump($s);
				$text = self::getUntil($s, $delimText.'}')->word;
				//var_dump($s, $text); die();
				$text = self::bindValues($text, $this->templateValues);
				$this->append($text, $lastNode ? $lastNode : $parent);
				}
			elseif ($sop == ',')
				{
				$lastNode = null;
				if ($alone)
					break;
				else
					$s = substr($s, 1);	#strlen($sop)
				}
			else
				{
				$node = new SohaNode();
				$s = $node->parse($s);
				$node->bindValuesAttr($this->templateValues);
				//var_dump('Parse: '.$node->getCode()); echo '<hr />';
				if (!$node->isEmpty())
					{
					$node = $node->create($this->doc);
					$this->append($node, $parent);
					$lastNode = $node;
					}
				else
					{
					preg_match('/^(.*)(?:[\r\n]|$)/ui', $s, $temp);
					$temp = $temp[1];
					if ($temp != $s)
						$temp .= '...';
					$temp .= '"';
					throw new Exception('Cannot parse node "' . $temp);
					}
				}
			
			$s = ltrim($s);
			}
		
		return $s;
		}
	
	
	public function getHtml()
		{
		return $this->doc->saveHTML();
		}
	}


class SohaMatch
	{
	public $word = '';
	public $delimiter = '';
	public $s = '';
	
	public function __construct($word = '', $delimiter = '', $s = '')
		{
		$this->word = $word;
		$this->delimiter = $delimiter;
		$this->s = $s;
		}
	}




class SohaNode extends SohaBase
	{
	public $name = 'div';
	protected $ops = array(
		'exit'			=> 'exit',
		'unknown'		=> 'unknown',
		'nodeName'		=> 'nodeName',
		'className'		=> 'className',
		'id'			=> 'id',
		'attr'			=> 'attr',
		'attrContinue'	=> 'attrContinue'
		);
	
	protected $attr = array();
	protected $_code = '';
	
	public function __construct($code = null)
		{
		if ($code)
			$this->parse($code);
		}
	
	public function getCode()
		{
		return $this->_code;
		}
	
	public function bindValuesAttr($values)
		{
		foreach ($this->attr as &$attr)
			$attr[1] = self::bindValues($attr[1], $values);
		unset($attr);
		return $this;
		}
	
	public function isEmpty()
		{
		return !trim($this->getCode());
		}
	
	public function attr($name = null, $value = null)
		{
		if ($name !== true)
			$name = self::normAttrName($name);
		
		if ($name && $name !== true && !is_null($value))
			{
			$name = self::normAttrName($name);
			$this->attr[strtolower($name)] = array($name, $value);
			if (strtolower($name) == 'class')
				$this->attr['class'][1] = trim($this->attr['class'][1]);
			
			return $this;
			}
		else if ($name && $name !== true)
			return (isset($this->attr[strtolower($name)])) ? $this->attr[strtolower($name)][1] : null;
		else
			{
			$res = array();
			if ($name === true)
				{
				foreach ($this->attr as $attr)
					$res[$attr[0]] = $attr[1];
				}
			else
				{
				foreach ($this->attr as $attrName => $attr)
					$res[$attrName] = $attr[1];
				}
			return $res;
			}
		}
	
	protected function getWord(&$s)
		{
		$match = null;
		if (!preg_match('/^([^#\.,\{\}\[\>\s]*)/', $s, $match))
			return new SohaMatch('', '', $s);
		
		$s = substr($s, strlen($match[1]));
		return new SohaMatch($match[1], '', $s);
		}
	
	public function parse($s)
		{
		$s .= '';
		$_s = $s;
		$op = $this->ops['nodeName'];
		
		while (strlen($s))
			{
			$s = ltrim(preg_replace('/^(?:\.|#|\[|\s+)*(\.|#|\[)/ui', '$1', $s));
			
			if ($op == $this->ops['unknown'])
				{
				switch ($s[0])
					{
					case '.':	$op = $this->ops['className']; break;
					case '#':	$op = $this->ops['id']; break;
					case '[':	$op = $this->ops['attr']; break;
					case ',':	$op = ($op == $this->ops['attrContinue'] ? $this->ops['attrContinue'] : $this->ops['exit']); break;
					default:	$op = $this->ops['exit']; break;
					}
				}
			
			if ($op == $this->ops['exit'])
				break;
			else if ($op != $this->ops['nodeName'])
				$s = substr($s, 1);
			
			if ($op == $this->ops['nodeName'])
				{
				$s = ltrim($s);
				$nodeName = null;
				if (!preg_match('/^[^\s\/\\\.\[#\{\}\>,]+/ui', $s, $nodeName))
					{
					$op = $this->ops['unknown'];
					continue;
					}
				$nodeName = $nodeName[0];
				if ($nodeName)
					{
					$s = substr($s, strlen($nodeName));
					$this->name = $nodeName;
					}
				}
			else if ($op == $this->ops['className'] || $op == $this->ops['id'])
				{
				$v = self::getWord($s);
				if (!$v->word)
					break;
				if ($op == $this->ops['className'])
					$this->attr('class', $this->attr('class').' '.$v->word);
				else if ($v->word && strlen(trim($v->word)) > 0)
					$this->attr('id', $v->word);
				}
			else if ($op == $this->ops['attr'] || $op == $this->ops['attrContinue'])
				{
				$s = ltrim($s);
				$attrName = null;
				if (!preg_match('/^(.*?)(=|,|\]|$)/u', $s, $attrName))
					{
					$op = $this->ops['unknown'];
					continue;
					}
				$opSet = $attrName[2] == '=';
				if ($attrName[1])
					$s = substr($s, strlen($attrName[1]));
				
				$attrName = $attrName[1];
				$s = preg_replace('/^\s*=\s*/u', '', $s);
				
				
				$attrValue = null;
				if ($s[0] == '"' || $s[0] == "'")
					{
					$quote = $s[0];
					$s = substr($s, 1);
					$attrValue = self::getUntil($s, $quote);
					$attrValue = $attrValue->word;
					unset($quote);
					$op = self::getUntil($s, array(',', ']'), ',');
					$op = ($op->delimiter == ',') ? $this->ops['attrContinue'] : $this->ops['attr'];
					}
				else
					{
					$attrValue = self::getUntil($s, array(',', ']'), ',');
					$op = ($attrValue->delimiter == ',') ? $this->ops['attrContinue'] : $this->ops['attr'];
					$s = $attrValue->s;
					$attrValue = $attrValue->word;
					}
				
				$s = ltrim($s);
				
				if (!$attrName)
					$attrName = $attrValue;
				elseif (!$attrValue && !$opSet && self::normAttrName($attrName) != 'class')
					$attrValue = $attrName;
				
				if ($attrName)
					{
					if (self::normAttrName($attrName) == 'class')
						{
						if (!$opSet || $attrValue)
							$this->attr($attrName, $attrValue);
						}
					else
						$this->attr($attrName, $attrValue);
					}
				}
			
			if ($op != $this->ops['attrContinue'])
				$op = $this->ops['unknown'];
			$s = self::cutComments(ltrim($s));
			}
		
		$this->_code = trim($this->_code."\n".substr($_s, 0, strlen($_s) - strlen($s)));
		
		return $s;
		}
	
	public function create($doc, $decodeHtmlEntities = false)
		{
		$res = $doc->createElement($this->name);
		foreach ($this->attr(true) as $attrName => $attrValue)
			$res->setAttribute($attrName, $attrValue);
		return $res;
		}
	}





__halt_compiler();
	

	
	
	var cfgDefault = {
		htmlEntitiesAttr: false,
		htmlEntitiesText: false,
		delimText: '@'
		};
	
	
	function buildSohson(o, _cfg)
		{
		var res = [];
		
		var cfg = {};
		for (var k in cfgDefault)
			cfg[k] = cfgDefault[k];
		
		if (typeof _cfg == 'object')
			{
			for (var k in _cfg)
				cfg[k] = _cfg[k];
			}
		
		function append(el)
			{
			if (typeof el != 'object')
				{
				el += '';
				if (!cfg.htmlEntitiesText)
					el = decodeEntities(el);
				//el = el.replace(/^ | $/g, '');
				append(document.createTextNode(el));
				}
			else if (el && (el.constructor === Array || el.constructor === jQuery))
				{
				for (var i = 0; i < el.length; i++)
					append(el[i]);
				}
			else
				{
				res.push(el);
				}
			};
		
		if (typeof o == 'object')
			{
			if (o.constructor === Array)
				{
				for (var i = 0; i < o.length; i++)
					{
					var elements = buildSohson(o[i], _cfg).elements;
					append(elements);
					}
				}
			else
				{
				for (var k in o)
					{
					var el;
					if (k && !k.match(/^[0-9]+$/))
						{
						el = new Node(k).create(!cfg.htmlEntitiesAttr);
						var v = o[k];
						if (['boolean', 'null', 'undefined'].indexOf(typeof v) < 0)
							{
							var elements = buildSohson(v, _cfg).elements;
							el.append($(elements));
							}
						}
					else
						{
						el = buildSohson(o[k], _cfg).elements;
						}
					append(el);
					}
				}
			}
		else
			append(['boolean', 'null', 'undefined'].indexOf(typeof o) > -1 ? '' : o + '');
		
		return {
			elements: $(res)
			};
		}
	
	
	$.soha = function(ex, cfg)
		{
		var res;
		if (typeof ex == 'object')
			res = buildSohson(ex, cfg);
		else
			res = buildSoha(ex + '', cfg);
		return $(res.elements);
		};
	})(jQuery);
