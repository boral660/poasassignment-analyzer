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
     * Производит отправку сообщения с файлом
     * @param string путь к файлу
     * @param string адресс электронной почты
     */
    public static function sendMailWithFile($filepath, $email)
    {
        date_default_timezone_set('Etc/GMT-3');
        $subject = "Автоматическое тестирование " . date("F j, Y, g:i a");

        $filename = basename($filepath);
        $boundary = "--".md5(uniqid(time()));

        $headers = "MIME-Version: 1.0;\r\n";
        $headers .="Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
        $headers .= "From: autotestermoodle@mail.ru\r\n";
        $headers .= "Reply-To: autotestermoodle@mail.ru\r\n";

        $multipart = "--$boundary\r\n";
        $multipart .= "Content-Type: text/html; charset=windows-1251\r\n";
        $multipart .= "Content-Transfer-Encoding: base64\r\n";
        $multipart .= '';

        // Закачиваем файл
        $fp = fopen($filepath, "r");
        if (!$fp) {
            print "Не удается открыть файл22";
            exit();
        }
        $file = fread($fp, filesize($filepath));
        fclose($fp);

        $message_part = "\r\n--$boundary\r\n";
        $message_part .= "Content-Type: application/octet-stream; name=\"$filename\"\r\n";
        $message_part .= "Content-Transfer-Encoding: base64\r\n";
        $message_part .= "Content-Disposition: attachment; filename=\"$filename\"\r\n";
        $message_part .= '\r\n';
        $message_part .= chunk_split(base64_encode($file));
        $message_part .= "\r\n--$boundary--\r\n";
        $multipart .= $message_part;

        mail($email, $subject, $multipart, $headers);
    }

    /**
     * Создает файл с логами
     * @param string[] логи
     * @return возращает путь к файлу
     */
    public static function writeOnFile($text)
    {
        if (!is_dir("./Logs")) {
            mkdir("./Logs");
        }
        $fileName = "./Logs/Log " . date('j.F.Y g-i-s') . ".log";
        $fp = fopen($fileName, "w");
        $text = Reporter::replaseTag($text);
        $text = strip_tags($text);
        fwrite($fp, $text);
        fclose($fp);
        return $fileName;
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
        $text = preg_replace($patterns, "\r\n", $text);

        $patterns = array();
        $patterns[0] = "<<h\d>>";
        $patterns[1] = "<</h\d>>";
        $text = preg_replace($patterns, "", $text);
        return $text;
    }

    /**
     * Производит отправку сообщения в комментарий к задаче
     * @param string[] сообщения об ошибке
     * @param string адресс сайта с комментарием
     */
    public static function sendComment($errors, $task, $cookie_file)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://edu.vstu.org/comment/comment_ajax.php"); // отправляем на
        curl_setopt($ch, CURLOPT_HEADER, 0); // пустые заголовки
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // таймаут
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // просто отключаем проверку сертификата
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file); // сохранять куки в файл
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            'sesskey'=>'fsm7VgbF1M',
            'action' => 'add',
            'content' => "LLelelel"
        ));
        $strs = file($cookie_file);
        print_r($strs);
        print_r($ch);

        $ex            = curl_exec($ch);
        curl_close($ch);

        return $ex;
    }
}

