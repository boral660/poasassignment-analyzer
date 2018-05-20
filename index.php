<?php
include_once 'Reporter.php';
include_once 'MoodleParser.php';

// Подготавливаем программу к анализу
error_reporting(E_ALL & ~E_NOTICE);
ob_start();
$succes_test=true;

// Пытаемся пропарсить Moodle и проанализировать работы
try {
	$mp = new MoodleParser();
	$is_auth = $mp->login();
	echo $is_auth ? 'Авторизация успешна' : 'Авторизация провалена';
	echo '<br>';
	$my_html = '';
	if ($is_auth === true) {
		$mp->parseAllTask();
	}
} catch (Exception $e) {	
	echo '<br>Выброшено исключение : ', $e->getMessage(), '<br>';
	$my_html = ob_get_clean();
	if (strnatcasecmp($mp->writeOn(), "console") == 0) {
		echo strip_tags(Reporter::replaseTag($my_html));
	} else if (strnatcasecmp($mp->writeOn(), "log") == 0) {
		echo 'Тестирование закончилось неудачей, результат сохранен в .log файле';
		Reporter::writeOnFile($my_html);
	} else{
		echo $my_html;
	}
	exit();
}

// Выводим отчет о работе
$my_html = ob_get_clean();
if (strnatcasecmp($mp->writeOn(), "log") == 0) {
	$logFile = Reporter::writeOnFile($my_html);
	echo 'Тестирование законченно, результат сохранен в .log файле';
} else if (strnatcasecmp($mp->writeOn(), "console") == 0) {
	echo strip_tags(Reporter::replaseTag($my_html));
}else{
	echo $my_html;
}
// Если стребуется, отправляем сообщение на email
if ($mp->getSendResultOnEmail()) {
	if($logFile == null)
		$logFile = Reporter::writeOnFile($my_html);
	Reporter::sendMailWithFile($logFile, $mp->getEmail());
}
// Записываем в файл время загрузки проверенных работ
Reporter::writeTimeOnFile(json_encode($mp->testTime(),JSON_PRETTY_PRINT));
