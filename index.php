<?

/*
$s = '1 {= var =} 2 \{= var =} 3 \\{= var =} 4 \\\{= var =} 5 \\\\{= var =} 6 \\\\\{= var =} 7 \\\\\\{= var =} 8 \\\\\\\{= var =} 9 \\\\\\\\{= var =} 10';
$s = preg_replace('@(?<!\\\\)((?:\\\\\\\\)*)\{= *var *=\}@u', '$1!!!', $s);
$s = str_replace('\\\\', '\\', $s);
die(htmlspecialchars($s));
*/

/*
$re = '/^(?:(true|1)|(false|0))$/ui';

preg_match($re, 'ololo', $temp); var_dump($temp);
preg_match($re, 'true', $temp); var_dump($temp);
preg_match($re, 'false', $temp); var_dump($temp);
preg_match($re, 'ololo', $temp); var_dump($temp);
preg_match($re, '1', $temp); var_dump($temp);
preg_match($re, '0', $temp); var_dump($temp);

die();
*/

set_time_limit(5);
include_once 'Soha.php';

/*
$s = <<<HTML
div {@ ololo @},
{\$ delimText="#" $}
div {# 22222 #}
HTML;
$soha = new Soha();
$soha->parse($s);
$markup = $soha->getHtml();

die('<pre style="white-space:pre-wrap">'.htmlspecialchars($markup));
*/

if (@$_GET['action'] == 'soha')
	{
	$s = @$_POST['s'];
	//$markup = new Soha($s);
	//$markup = Soha::getUntil($s, '"');//'<p>Ololo</p>';
	
	//$markup = new SohaNode($s);
	//$markup = SohaBase::domElementToString($markup->create());
	
	$soha = new Soha();
	$soha->parse($s);
	$markup = $soha->getHtml();
		
	header('Content-Type: application/json', 1, 200);
	die(json_encode(array('s' => $s, 'markup' => $markup)));
	}

?>
<!DOCTYPE html>
<html>
<head>
<title>Soha &mdash; ShOrtHAnd</title>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
<script src="jquery.soha.js"></script>
<script>

$(function()
	{
	var $in = $('#in');
	var $out = $('#out');
	var $outEls = $('#outEls');
	var $cfgShowMarkup = $('#cfg-show-markup');
	var $cfgPhpSide = $('#cfg-php-side');
	var timer = false;
	
	function updateResult()
		{
		if ($cfgPhpSide.is(':checked'))
			{
			$.ajax({
				url: '?action=soha',
				method: 'post',
				dataType: 'json',
				data: { s: $in.val() },
				success: function(data)
					{
					try
						{
						//data = JSON.parse(data);
						}
					catch (exception)
						{
						}
					//console.log(data);
					setResult(data.markup);
					}
				});
			}
		else
			{
			var val = $in.val();
			try
				{
				val = JSON.parse(val);
				}
			catch (exception)
				{
				//console.log('Soha mode because of:', exception);
				}
			setResult($.soha(val));
			}
		}
	
	var lastElements;
	function setResult(elements)
		{
//$out.val(typeof elements == 'object' ? JSON.stringify(elements) : elements); return;
		if (typeof elements == 'undefined')
			elements = lastElements;
		if (typeof elements == 'string')
			{
			try
				{
				elements = $(elements + '');
				}
			catch (exception)
				{
				elements += '';
				}
			}
		
		if (typeof elements != 'object')
			{
			$out.val('<!-- Error -->\n\n' + elements);
			$outEls.find('*').remove();
			$outEls.html('');
			return;
			}
		lastElements = elements;
		
		if ($out.is(':visible'))
			{
			var html = $('<div/>').append(elements).html();
			$out.val(html);
			}
		
		if ($outEls.is(':visible'))
			{
			$outEls.find('*').remove();
			$outEls.html('');
			$outEls.append(elements);
			$outEls.find('style:not([scoped])').attr('scoped', true).appendTo($outEls);
			}
		}
	
	function updateResultDelayed()
		{
		if (timer !== false)
			{
			clearTimeout(timer);
			timer = false;
			}
		timer = setTimeout(updateResult, 200);
		}
	
	$in.on('keyup change', updateResultDelayed);
	$cfgShowMarkup.on('change', function() { setResult(); });
	$cfgPhpSide.on('change', function() { updateResult(); });
	
	$cfgShowMarkup.change(function()
		{
		$out.toggle($cfgShowMarkup.is(':checked'));
		$outEls.toggle(!$cfgShowMarkup.is(':checked'));
		});
	
	updateResult();
	$cfgShowMarkup.change();
	
	$(document).delegate('textarea', 'keydown', function(e)
		{
		var res = false;
		var keyCode = e.keyCode || e.which;
		
		var start = this.selectionStart;
		var end = this.selectionEnd;
		
		if (keyCode == 9)
			{
			// set textarea value to: text before caret + tab + text after caret
			$(this).val($(this)
				.val().substring(0, start)
				+ "\t"
				+ $(this).val().substring(end));
			
			// put caret at right position again
			this.selectionStart = this.selectionEnd = start + 1;
			}
		else if (keyCode == 10 || keyCode == 13)
			{
			var line = $(this).val().substring(0, start);
			line = /.*$/.exec(line);
			var padding = /^\t+/.exec(line);
			if (!padding)
				return;
			else
				padding = padding[0];
			
			$(this).val($(this)
				.val().substring(0, start)
				+ "\n" + padding
				+ $(this).val().substring(end));
			
			// put caret at right position again
			this.selectionStart = this.selectionEnd = start + padding.length + 1;
			}
		else
			res = true;
		
		if (!res)
			{
			e.preventDefault();
			return res;
			}
		});
	});

</script>
<style>
html,
body {
	margin: 0;
	padding: 0;
	width: 100%;
	height: 100%;
	display: block;
	font-size: 0;
	font-family: 'PT Sans', sans-serif;
}
body > .panel {
	font-size: 4mm;
	display: inline-block;
	float: left;
	width: 50%;
	height: 100%;
	box-sizing: border-box;
	border: #ccc 5px solid;
	border-style: none solid;
}
body > :first-child {
	border-left: none;
}
body > :last-child {
	border-right: none;
}
body > .panel > #outEls {
	box-sizing: border-box;
	max-width: 100%;
	max-height: 100%;
	height: 100%;
	overflow: auto;
	padding: 1em;
}
body > .panel > textarea {
	display: block;
	width: 100%;
	height: 100%;
	border: none;
	box-sizing: border-box;
	padding: 1em;
	resize: none;
	tab-size: 4;
}
body > .panel > aside {
	background: #ccc;
	padding: 5px;
	position: fixed;
	z-index: 10;
	left: 0px;
	bottom: 20px;
	width: 240px;
	margin-left: -250px;
	transition: all 0.5s ease;
}
body > .panel > aside:hover {
	margin-left: 0;
	box-shadow: rgba(0,0,0, 0.5) 0px 1px 2px,
				rgba(0,0,0, 0.5) 0px 0px 120px;
}
body > .panel > aside:after {
	content: '';
	position: absolute;
	width: 5px;
	top: 0;
	left: 100%;
	bottom: 0;
	border-radius: 0 5px 5px 0;
	background: #c24;
	transition: all 0.5s ease;
}
body > .panel > aside:hover:after {
	width: 10px;
	box-shadow: rgba(0,0,0, 0.5) 0px 1px 2px;
}
body > .panel > aside:before {
	content: '';
	position: absolute;
	z-index: -1;
	left: 0;
	right: 0;
	top: 0;
	bottom: 0;
}
body > .panel > aside:hover:before {
	right: -2em;
	top: -1em;
	bottom: -1em;
}

body > .panel > aside.hider .hide {
	position: fixed;
	top: -9999px;
}
body > .panel > aside.hider:hover .hide {
	position: static;
}
body > .panel > aside input[type="checkbox"] {
	display: inline-block;
	vertical-align: middle;
}
body > .panel > aside .block {
	display: block;
}
body > .panel > aside .miblock {
	display: inline-block;
	vertical-align: middle;
}
body > .panel > aside .pr {
	padding-right: 5px;
}
</style>
</head>
<body>
<div class="panel">
	<textarea id="in">{$ formAction = "?action=feedback" $}
form[action="{=formAction=}"]
	{
	dl
		{
		{~ delimText="#" ~}
		dt > label {# Имя: #},
		dd > input[type="text"].jinput
		{~ delimText="@" ~}
		},
	dl
		{
		dt > label {@ Тема: @},
		dd > select.jinput
			{
			option {@ Покупка/продажа недвижимости @}
			option {@ Квартиры в новостройках @}
			option {@ Коммерческая недвижимость @}
			option {@ Покупка/продажа недвижимости @}
			option {@ Юридическая консультация @}
			option {@ Ипотечная консультация @}
			}
		}
	dl
		{
		dt > label {@ Номер телефона: @},
		dd > input[type="tel"].jinput
		},
	dl
		{
		dt > label {@ Сообщение: @},
		dd > textarea[placeholder="Не обязательно — мы вам просто перезвоним"].jinput.jinput-autosize
		},
	p > {@ Мы вам перезвоним, как только обработаем вашу заявку @},
	div.st > button[type=submit] {@ Отправить @}
	}
</textarea>
	<aside class="hider">
		<p>
			<label><input type="checkbox" id="cfg-php-side"><span class="hide miblock pr">Декодировать на  сервере</span></label>
		</p>
		<p>
			<label><input type="checkbox" id="cfg-show-markup" checked="checked"><span class="hide miblock pr">Показывать разметку</span></label>
		</p>
	</aside>
</div>
<div class="panel">
	<textarea id="out"></textarea>
	<div id="outEls"></div>
</div>
</body>
</html>