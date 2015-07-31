(function(exports) {
	var DELIM_TEXT = '@';
	var DELIM_CFG = '~';
	var DELIM_DEFINE = '$';
	var DELIM_VALUE = '=';

	function getCodePosition(current, full) {
		var near = current.match(/^.+/);
		near = near ? near[0] : '';
		if (near.length > 30)
			near = near.substr(0, 27) + '...';
		if (!current.length || full.length < current.length)
			return {
				line: 0,
				offset: 0,
				near: near
			};
		var part = full.substr(0, full.length - current.length);
		part = part.split(/\r\n|\r|\n/);
		return {
			line: part.length,
			offset: part[part.length - 1].length + 1,
			near: near
		};
	}

	// this prevents any overhead from creating the object each time
	var decodeEntitiesElement = document.createElement('div');

	function decodeEntities(str) {
		if (str && typeof str === 'string') {
			// strip script/html tags
			str = str.replace(/<script[^>]*>([\S\s]*?)<\/script>/gmi, '');
			//str = str.replace(/<\/?\w(?:[^"'>]|"[^"]*"|'[^']*')*>/gmi, '');
			decodeEntitiesElement.innerHTML = str;
			str = decodeEntitiesElement.textContent;
			decodeEntitiesElement.textContent = '';
		}
		return str;
	}

	function regexpEscape(s) {
		return s.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");
	}

	function getUntil(s, delimiter, saveDelimiter) {
		//var re = '^(.*?(?:\\\\\\\\)*(!?\\\\))';
		//var re = '(.*?(?<!\\\\)(?:\\\\\\\\)*)';
		var re = '^([\\s\\S]*?)(\\\\+)?';
		if (!delimiter)
			delimiter = '"';
		if (typeof delimiter != 'object')
			delimiter = [delimiter];
		for (var i = 0; i < delimiter.length; i++)
			delimiter[i] = regexpEscape(delimiter[i]);
		re += '(' + delimiter.join('|') + '|$)';

		re = new RegExp(re);
		var res = '';
		var match;
		while (match = re.exec(s)) {
			res += match[0];
			s = s.substr(match[0].length);
			if (!match[2] || match[2].length % 2 == 0)
				break;
		}

		if (!match)
			res = [s, '', ''];
		else
			res = [res, res.substr(0, res.length - match[3].length), match[3]];

		res[1] = res[1].replace(new RegExp('\\\\(' + delimiter.join('|') + '|\\\\)', 'g'), '$1');

		if (saveDelimiter) {
			if (typeof saveDelimiter != 'object') {
				if (typeof saveDelimiter == 'string')
					saveDelimiter = [saveDelimiter];
				else
					saveDelimiter = delimiter;
			}

			if (saveDelimiter.indexOf(res[2]) > -1)
				saveDelimiter = res[2];
			else
				saveDelimiter = '';
		}
		else
			saveDelimiter = '';

		return {
			re: re,
			match: res[0],
			s: saveDelimiter + s,
			word: res[1],
			delimiter: res[2]
		};
	}

	function ltrim(s) {
		return s.replace(/^\s+/, '');
	}

	function trim(s) {
		return s.replace(/^\s+|\s+$/g, '');
	}

	function cutComments(s) {
		while (s.match(/^(\/\*|\/\/.*(?:\n|$))/))
			s = ltrim(s.replace(/^(\/\*[\s\S]*?(?:\*\/|$)|\/\/.*(?:\n|$))/, ''));
		return s;
	}

	function Node(code) {
		this.name = 'div';

		var ops = {
			exit: 'exit',
			unknown: 'unknown',
			nodeName: 'nodeName',
			className: 'className',
			id: 'id',
			attr: 'attr',
			attrContinue: 'attrContinue'
		};

		var attr = {};
		this.a = attr;
		var _code = '';

		this.getCode = function() {
			return _code;
		};

		function normAttrName(name) {
			if (!name || name === true)
				return false;
			return name.toString()
				.replace(/^\s+|\s+$/g, '')
				.toLowerCase();
		}

		this.attr = function(name, value) {
			name = normAttrName(name);

			if (name && typeof value != 'undefined') {
				name = normAttrName(name);
				attr[name] = value;
				if (name == 'class')
					attr[name] = trim(attr[name]);

				return this;
			}
			else if (name)
				return (typeof attr[name] != 'undefined') ? attr[name] : undefined;
			else
				return attr;
		};

		function getWord(s, additionalDelimiter) {
			/*
			var add = additionalDelimiter;
			var deliiters = [
				'#', '.', ',', '{', '}', '[', '>'
				];
			if (typeof add == 'object' && add.constructor == Array)
				delimiters = delimiters.concat(add);
			else if (['string', 'number'].indexOf(typeof add) > -1)
				delimiters.push(add);
			else if (add)
				delimiters.push(' ');
			*/

			//var res = /^\s*([\S]*?)(?:#|\.|,|\{|\}|\[|\>| |$)/.exec(s);
			var res = /^([^#\.,\{\}\[\>\s]*)/.exec(s);
			if (!res) {
				return {
					word: '',
					s: s
				};
			}

			return {
				word: res[1],
				s: s.substr(res[1].length)
			};
		}

		this.parse = function(s) {
			s = s + '';
			var _s = s;
			var op = ops.nodeName;

			while (s.length > 0) {
				s = ltrim(s.replace(/^(?:\.|#|\[|\s+)*(\.|#|\[)/, '$1'));

				if (op == ops.unknown) {
					switch (s[0]) {
						case '.':
							op = ops.className;
							break;
						case '#':
							op = ops.id;
							break;
						case '[':
							op = ops.attr;
							break;
						case ',':
							op = (op == ops.attrContinue ? ops.attrContinue : ops.exit);
							break;
						default:
							op = ops.exit;
							break;
					}
				}

				if (op == ops.exit)
					break;
				else if (op != ops.nodeName)
					s = s.substr(1);

				if (op == ops.nodeName) {
					s = ltrim(s);
					var nodeName = /^[^\s\/\\\.\[#\{\}\>,]+/.exec(s); //getWord(s);
					if (!nodeName || !nodeName[0]) {
						op = ops.unknown;
						continue;
					}
					nodeName = nodeName[0];
					if (nodeName) {
						s = s.substr(nodeName.length);
						this.name = nodeName;
					}
					/*
					s = ltrim(s);
					var nodeName = /^[^\s\/\\\.\[#\{\}\>]+/.exec(s);//getWord(s);
					if (!nodeName.word)
						break;
					s = nodeName.s;
					this.name = nodeName.word;
					*/
				}
				else if (op == ops.className || op == ops.id) {
					var v = getWord(s);
					if (!v.word)
						break;
					s = v.s;
					if (op == ops.className)
						this.attr('class', (this.attr('class') || '') + ' ' + v.word);
					else if (v.word && trim(v.word).length > 0)
						this.attr('id', v.word);
				}
				else if (op == ops.attr || op == ops.attrContinue) {
					s = ltrim(s);
					var attrName = /^(.*?)(=|,|\]|$)/.exec(s);
					if (!attrName) {
						op = ops.unknown;
						continue;
					}
					var opSet = attrName[2] == '=';
					var delimiterPos = s.indexOf(attrName[2]);
					attrName = attrName[1];
					if (delimiterPos > 0)
						s = s.substr(delimiterPos);
					s = s.replace(/^\s*=\s*/, '');

					var attrValue;
					if (['"', "'"].indexOf(s[0]) > -1) {
						var quote = s[0];
						s = s.substr(quote.length);
						attrValue = getUntil(s, quote);
						s = attrValue.s;
						attrValue = attrValue.word;
						delete quote;
						var temp = getUntil(s, [',', ']'], ',');
						s = temp.s;
						op = temp.delimiter == ',' ? ops.attrContinue : ops.attr;
						delete temp;
					}
					else {
						attrValue = getUntil(s, [',', ']'], ',');
						op = attrValue.delimiter == ',' ? ops.attrContinue : ops.attr;
						s = attrValue.s;
						attrValue = attrValue.word;
					}

					s = ltrim(s);

					if (!attrName)
						attrName = attrValue;
					else if (!attrValue && !opSet && normAttrName(attrName) != 'class')
						attrValue = attrName;

					if (attrName) {
						if (normAttrName(attrName) == 'class') {
							if (!opSet || attrValue)
								this.attr(attrName, attrValue);
						}
						else
							this.attr(attrName, attrValue);
					}
				}

				if (op != ops.attrContinue)
					op = ops.unknown;
				s = cutComments(ltrim(s));
			}

			_code += _s.substr(0, _s.length - s.length);

			return s;
		};

		this.bindValuesAttr = function(values) {
			for (var i in attr)
				attr[i] = bindValues(attr[i], values);
			return this;
		};

		this.create = function(decodeHtmlEntities) {
			var attrs = this.attr(),
				element = document.createElement(this.name);
			if (decodeHtmlEntities) {
				for (var k in attrs)
					element.setAttribute(k, decodeEntities(attr[k]));
			}
			else {
				for (var k in attrs)
					element.setAttribute(k, attr[k]);
			}
			
			return element;
		};

		if (code)
			this.parse(code);

		return this;
	}

	function parseParams(s, closeBracket, callback) {
		if (typeof callback != 'function')
			callback = false;
		var res = {};
		if (!closeBracket || typeof closeBracket != 'string')
			return {
				s: s,
				params: res,
				length: 0
			};

		function addRes(name, value) {
			//console.log(paramName, paramValue);
			if (callback)
				callback(name, value, closeBracket);
			res[name] = value;
			length++;
		}

		var counter = 0;
		while (counter++ < 1000) {
			s = s.replace(/^[,\s]+/, '');
			var paramName = getUntil(s, ['=', closeBracket]);
			paramName.word = trim(paramName.word);
			s = ltrim(paramName.s);
			if (paramName.delimiter == closeBracket || trim(paramName.word) == '')
				break;
			paramName = paramName.word;
			var paramValue;
			if (s[0] == '"' || s[0] == "'") {
				paramValue = getUntil(s.substr(1), s[0]);
				s = paramValue.s;
				addRes(paramName, paramValue.word);
			}
			else {
				paramValue = getUntil(s, [',', closeBracket], closeBracket);
				s = paramValue.s;
				paramValue = trim(paramValue.word);
				if (paramValue.match(/^(true|false|1|0)$/i)) {
					paramValue = paramValue.toLowerCase() == 'true' || paramValue == '1';
					addRes(paramName, paramValue);
				}
				else if (paramValue)
					addRes(paramName, paramValue);
			}
		}

		return {
			s: s,
			params: res,
			length: length
		};
	}

	function bindValues(s, values) {
		var re;
		var delimValue = regexpEscape(DELIM_VALUE);
		for (var i in values) {
			re = '(?:^|([^\\\\])((?:\\\\(?:\\\\))*))\{' + delimValue + ' *' + regexpEscape(i) + ' *' + delimValue + '\}';
			s = s.replace(new RegExp(re, 'g'), '$1$2' + values[i]);
		}

		return s;
	}

	var cfgDefault = {
		htmlEntitiesAttr: false,
		htmlEntitiesText: false,
		delimText: '@'
	};

	function buildSoha(s, _cfg, alone, _s) {
		_s = _s || s;
		var res = [];
		var lastNode = false;

		var cfg = {};
		for (var k in cfgDefault)
			cfg[k] = cfgDefault[k];

		if (typeof _cfg == 'object') {
			for (var k in _cfg)
				cfg[k] = _cfg[k];
		}

		function append(el) {
			if (typeof el != 'object') {
				el += '';
				if (!cfg.htmlEntitiesText)
					el = decodeEntities(el);
				el = el.replace(/^ | $/g, '');
				el = document.createTextNode(el);
				append(el);
			}
			else if (el && (el.constructor === Array || el.constructor === jQuery)) {
				for (var i = 0; i < el.length; i++)
					append(el[i]);
			}
			else {
				if (lastNode)
					lastNode.appendChild(el);
				else
					res.push(el);
			}
			return el;
		};

		var values = {};
		if (typeof cfg.values == 'object' && cfg.values)
			values = cfg.values;
		s = ltrim(s);
		var delimCfg = regexpEscape(DELIM_CFG),
			delimDefine = regexpEscape(DELIM_DEFINE),
			delimValue = regexpEscape(DELIM_VALUE);

		while (s.length > 0) {
			cfg.delimText = cfg.delimText || cfgDefault.delimText;
			s = cutComments(s);

			var sop = new RegExp('^(\\{' + delimCfg + '|\\{' + delimDefine + '|' + regexpEscape('{' + cfg.delimText) + '|\\{|,|>|\\})');
			sop = sop.exec(s);

			if (sop)
				sop = sop[1];
			else if (!s.length)
				break;

			if (sop == '}') {
				if (!alone)
					s = s.substr(sop.length);
				break;
			}
			else if (sop == '{' || sop == '>') {
				s = s.substr(sop.length);
				cfg.values = values;
				var children = buildSoha(s, cfg, sop == '>', _s);
				s = children.remainder;
				append(children.elements);
			}
			else if (sop == '{' + DELIM_CFG) {
				s = s.substr(sop.length);
				s = parseParams(s, DELIM_CFG + '}', function(name, value) {
						cfg[name] = value;
					})
					.s;
			}
			else if (sop == '{' + DELIM_DEFINE) {
				s = s.substr(sop.length);
				s = parseParams(s, DELIM_DEFINE + '}', function(name, value) {
						values[name] = value;
					})
					.s;
			}
			else if (sop == '{' + cfg.delimText) {
				s = s.substr(sop.length);
				var textNode = getUntil(s, cfg.delimText + '}');
				s = textNode.s;
				textNode = bindValues(textNode.word, values);
				append(textNode);
			}
			else if (sop == ',') {
				lastNode = false;
				if (alone)
					break;
				else
					s = s.substr(sop.length);
			}
			else {
				var node = new Node();
				s = node.parse(s);
				node.bindValuesAttr(values);

				if (trim(node.getCode()).length > 0) {
					lastNode = false;
					try {
						node = node.create(!cfg.htmlEntitiesAttr);
						lastNode = append(node);
					}
					catch (exception) {
						var codePosition = getCodePosition(node.getCode() + s, _s);
						throw new Error('Parse exception' + ' on line ' + codePosition.line
							//+' at offset ' + codePosition.offset
							+ ': ' + exception + (codePosition.near ? ' (near "' + codePosition.near + '")' : '')
						);
					}
				}
				else {
					/*
					var temp = /^(.*)(?:\n|$)/.exec(s)[1];
					if (temp != s)
						temp += '...';
					else
						temp += '"';
					*/
					var codePosition = getCodePosition(s, _s);
					throw new Error('Parse exception' + ' on line ' + codePosition.line + ' at offset ' + codePosition.offset + (codePosition.near ? ' near "' + codePosition.near + '"' : ''));
				}
			}

			s = ltrim(s);
		}

		//_cfg = cfg;
		if (typeof _cfg == 'object') {
			for (var k in cfg)
				_cfg[k] = cfg[k];
		}

		return {
			elements: res,
			remainder: s
		};
	}

	function buildSohson(o, _cfg) {
		var res = [];

		var cfg = {};
		for (var k in cfgDefault)
			cfg[k] = cfgDefault[k];

		if (typeof _cfg == 'object') {
			for (var k in _cfg)
				cfg[k] = _cfg[k];
		}

		function append(el) {
			if (typeof el != 'object') {
				el += '';
				if (!cfg.htmlEntitiesText)
					el = decodeEntities(el);
				//el = el.replace(/^ | $/g, '');
				append(document.createTextNode(el));
			}
			else if (el && (el.constructor === Array || el.constructor === jQuery)) {
				for (var i = 0; i < el.length; i++)
					append(el[i]);
			}
			else {
				res.push(el);
			}
		};

		if (typeof o == 'object') {
			if (o.constructor === Array) {
				for (var i = 0; i < o.length; i++) {
					var elements = buildSohson(o[i], _cfg).elements;
					append(elements);
				}
			}
			else {
				for (var k in o) {
					var el;
					if (k && !k.match(/^[0-9]+$/)) {
						el = new Node(k).create(!cfg.htmlEntitiesAttr);
						var v = o[k];
						if (['boolean', 'null', 'undefined'].indexOf(typeof v) < 0) {
							var elements = buildSohson(v, _cfg).elements;
							for (var i = 0; i < elements.length; i++)
								el.appendChild(elements[i]);
						}
					}
					else {
						el = buildSohson(o[k], _cfg).elements;
					}
					append(el);
				}
			}
		}
		else
			append(['boolean', 'null', 'undefined'].indexOf(typeof o) > -1 ? '' : o + '');

		return {
			elements: res
		};
	}

	exports.buildSoha = buildSoha;
	exports.buildSohson = buildSohson;
})(window);
