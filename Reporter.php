<?php
/**
 * Class Reporter клаcc выполняющий отправку результатов тестирования
 */
class Reporter
{
	   /**
     * Производит отправку сообщения с текстом на почту
     * @param string[] текст сообщения
     * @param string адресс электронной почты
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
	
	  /**
     * Создает файл с логами
     * @param string[] логи
     */
	public static function writeOnFile($text)
	{
		 if (!is_dir("./Logs")) {
            mkdir("./Logs");
          }
		 $fp = fopen("./Logs/Log " . date('j.F.Y g-i-s') . ".txt","w");
		$text = Reporter::replaseTag($text);
		fwrite($fp, $text);
         fclose($fp);
	}
	
	
	  /**
     * Заменяет теги в коде
     * @param string[] текст
     */
	private static function replaseTag($text)
	{

		 $patterns = array();
		 $patterns[0] = '<<br>>';
		 $patterns[1] = '<<p>>';
		 $patterns[2] = '<</p>>';
		 $text = preg_replace($patterns,"\r\n", $text);
		 
		 $patterns = array();
		 $patterns[0] = "<<h\d>>";
		 $patterns[1] = "<</h\d>>";
		 $text = preg_replace($patterns,"", $text);
		return $text;
	}
	
	   /**
     * Производит отправку сообщения в комментарий к задаче
     * @param string[] сообщения об ошибке
     * @param string адресс сайта с комментарием
     */
	public static function sendComment($errors, $task, $cookie_file)
	{
		//TODO
	}
	
}
