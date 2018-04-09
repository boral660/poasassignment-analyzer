<?php
/**
 * Class Reporter клаcc выполняющий отправку результатов тестирования
 */
class Reporter
{
	   /**
     * Производит отправку сообщения с текстом на почту
     * @param string[] текст сообщения
     * @param email адресс электронной почты
     */
	public static function sendMail($text, $email)
	{
		date_default_timezone_set('Etc/GMT-3');
		$subject = "Автоматическое тестирование " . date("F j, Y, g:i a"); 

		$headers  = "Content-type: text/html;\r\n"; 
		$headers .= "From: autotestermoodle@mail.ru\r\n"; 
		$headers .= "Reply-To: autotestermoodle@mail.ru\r\n"; 

		mail($email, $subject, $text, $headers); 
	}
	
}
