<?php
/**
 * Class Reporter клаcc выполняющий отправку результатов тестирования
 */
class Reporter
{
    /**
     * @var string путь к moodle
     */
    private static $moodle_url;

    /**
     * @var string почта с которой отсылать письма
     */
    private static $send_from_email;

    /**
     * Установить moodle_url
     */
    public static function setFromEmail($email)
    {
        Reporter::$send_from_email = $email;
    }

    /**
     * Установить moodle_url
     */
    public static function getFromEmail()
    {
        return Reporter::$send_from_email;
    }

    /**
     * Установить moodle_url
     */
    public static function setMoodleUrl($url)
    {
        $str = stripos($url, '/', 7);
        Reporter::$moodle_url = substr($url, 0, $str+1);
    }

    /**
     * Производит отправку сообщения с текстом на почту
     * @param string[] текст сообщения
     * @param string адрес электронной почты
     */
    public static function sendMail($text, $email)
    {
        date_default_timezone_set('Etc/GMT-3');
        $subject = "Автоматическое тестирование " . date("F j, Y, g:i a");

        $headers  = "Content-type: text/html;\r\n";
        $headers .= "From: " . Reporter::$send_from_email . "\r\n";
        $headers .= "Reply-To: " . Reporter::$send_from_email . "\r\n";
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
        $headers .= "From: " . Reporter::$send_from_email . "\r\n";
        $headers .= "Reply-To: " . Reporter::$send_from_email . "\r\n";

        $multipart = "--$boundary\r\n";
        $multipart .= "Content-Type: text/html; charset=windows-1251\r\n";
        $multipart .= "Content-Transfer-Encoding: base64\r\n";
        $multipart .= '';

        // Закачиваем файл
        $fp = fopen($filepath, "r");
        if (!$fp) {
            echo "Не удается открыть файл - " . $filepath;
            exit();
        }
        $file = fread($fp, filesize($filepath));
        fclose($fp);

        $message_part = "\r\n--$boundary\r\n";
        $message_part .= "Content-Type: application/octet-stream; name=\"$filename\"\r\n";
        $message_part .= "Content-Transfer-Encoding: base64\r\n";
        $message_part .= "Content-Disposition: attachment; filename=\"$filename\"\r\n";
        $message_part .= $file;
        $message_part .= "\r\n--$boundary--\r\n";
        $multipart .= $message_part;

       if(!mail($email, $subject, $multipart, $headers))
             echo "Не удалось отправить сообщение на электронную почту";

    }

    /**
     * Создает файл с логами
     * @param string[] логи
     * @return возращает путь к файлу
     */
    public static function writeOnFile($text)
    {
        date_default_timezone_set('Etc/GMT-3');
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
     * Записывает в файл с датами загрузки
     * @param string строка в формате json
     * @return возращает путь к файлу
     */
    public static function writeTimeOnFile($str)
    {
        $fileName = "TestingTime.json";
        $fp = fopen($fileName, "w");
        fwrite($fp, $str);
        fclose($fp);
        return $fileName;
    }


    /**
     * Заменяет теги в коде
     * @param string[] текст
     */
    public static function replaseTag($text)
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
     * @param string адрес сайта с комментарием
     */
    public static function sendComment($errors, $task, $cookie_file)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,  Reporter::$moodle_url . "comment/comment_ajax.php");
        curl_setopt($ch, CURLOPT_HEADER, 0); // пустые заголовки
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // таймаут
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // просто отключаем проверку сертификата
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file); // сохранять куки в файл
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, Reporter::arrayForAjax($errors, $task, $cookie_file));
        $ex            = curl_exec($ch);
        $stderr = fopen("curl.log", "a");
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $stderr);
        curl_close($ch);
        fclose($stderr);
    }
    /**
     * Ставит указанную оценку за работу
     * @param string $task - адрес, где следует проставить оценку
     * @param int   $grage - оценка
     */
    public static function gradeAnswer($task, $grade, $cookie_file)
    {
        // Получаем страницу для парсинга
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Reporter::$moodle_url . "mod/poasassignment/view.php"); // отправляем на
        curl_setopt($ch, CURLOPT_HEADER, 0); // пустые заголовки
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // таймаут
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // просто отключаем проверку сертификата
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file); // сохранять куки в файл
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($ch, CURLOPT_POST, 1); // использовать данные в post
        curl_setopt($ch, CURLOPT_POSTFIELDS, Reporter::arrayForGrage($task, $grade, $cookie_file));
        $stderr = fopen("curl.log", "a");
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $stderr);

        $ex            = curl_exec($ch);
        $taskhtml = $ex;
        curl_close($ch);
        fclose($stderr);
    }
    /**
     * Парсит страницу, для того что бы проставить оценку за протокол
          * @param string $task - адрес со всеми ответами
     * @param string $taskGrade - адрес, где следует проставить оценку
     * @param int   $grage - оценка
     */
    public static function arrayForGradeProtocol($task, $taskGrade, $grade, $cookie_file)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $taskGrade); // отправляем на
        curl_setopt($ch, CURLOPT_HEADER, 0); // пустые заголовки
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // таймаут
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // просто отключаем проверку сертификата
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file); // сохранять куки в файл
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        $stderr = fopen("curl.log", "a");
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $stderr);

        $ex            = curl_exec($ch);
        $taskhtml = $ex;
        curl_close($ch);
        fclose($stderr);

        $result = array();

        // Парсим html
        $dom = new DOMDocument();
        @$dom->loadHTML($taskhtml);
        $xpath       = new DOMXPath($dom);
        $result = array();

        Reporter::sesskeyForProtocol($task, $cookie_file, $result);

        $result['assignmentid'] = $xpath->query('//*[@data-region="user-info"]')->item(0)->attributes->item(2)->nodeValue;
        $result['userid'] = $xpath->query('//*[@data-region="grading-navigation-panel"]')->item(0)->attributes->item(1)->nodeValue;

        $result['editpdf_source_userid'] = 0;

        return($result);
    }
    /**
     * Парсит страницу, для того что бы получить sesskey
     * @param string $task - адрес, где следует проставить оценку
     * @param int   $grage - оценка
     */
    public static function sesskeyForProtocol($task, $cookie_file, &$result)
    {
        // Получаем страницу для парсинга
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $task); // отправляем на
        curl_setopt($ch, CURLOPT_HEADER, 0); // пустые заголовки
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // таймаут
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // просто отключаем проверку сертификата
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file); // сохранять куки в файл
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        $stderr = fopen("curl.log", "a");
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $stderr);

        $ex            = curl_exec($ch);
        $taskhtml = $ex;
        curl_close($ch);
        fclose($stderr);

        // Парсим html
        $dom = new DOMDocument();
        @$dom->loadHTML($taskhtml);
        $xpath       = new DOMXPath($dom);

        $temp=$xpath->query('//*[@data-title="logout,moodle"]')->item(0)->attributes->item(0)->nodeValue;
        $parts = parse_url($temp);
        parse_str($parts['query'], $query);
        $result['sesskey'] = $query['sesskey'];
    }
    /**
     * Парсит страницу, для того что бы проставить оценку за протокол
     * @param string $task - адрес со всеми ответами
     * @param string $taskGrade - адрес, где следует проставить оценку
     * @param int   $grage - оценка
     */
    public static function grageProtocol($task, $taskGrade, $grade, $cookie_file)
    {
        $ch = curl_init();
        $ar=Reporter::arrayForGradeProtocol($task, $taskGrade, $grade, $cookie_file);
        $json_string='[{"index":0,"methodname":"mod_assign_submit_grading_form","args":{"assignmentid":"'. $ar['assignmentid'] .'","userid":'. $ar['userid'] .',"jsonformdata":"\"editpdf_source_userid='. $ar['editpdf_source_userid'] .'&id='. $ar['id'] .'&rownum=0&useridlistid=&attemptnumber=-1&ajax=0&userid=0&sendstudentnotifications=true&action=submitgrade&sesskey=' . $ar['sesskey'] .'&_qf__mod_assign_grade_form_'.$ar['userid'].'=1&grade='.$grade.'&assignfeedbackcomments_editor%5Btext%5D=&assignfeedbackcomments_editor%5Bformat%5D=1&assignfeedback_editpdf_haschanges=false\""}}]';
        $url = Reporter::$moodle_url . 'lib/ajax/service.php?sesskey=' . $ar['sesskey'] .'&info=mod_assign_submit_grading_form';
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_string))
                   );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // таймаут
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // просто отключаем проверку сертификата
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file); // сохранять куки в файл
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        curl_setopt($ch, CURLOPT_POST, 1); // использовать данные в post
        $stderr = fopen("curl.log", "a");
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $stderr);

        $ex            = curl_exec($ch);
        $taskhtml = $ex;
        curl_close($ch);
        fclose($stderr);
    }


    /**
     * Парсит страницу, для того что бы проставить оценку за проект
     * @param string $task - адрес, где следует проставить оценку
     * @param int   $grage - оценка
     */
    public static function arrayForGrage($task, $grade, $cookie_file)
    {

        // Получаем страницу для парсинга
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $task); // отправляем на
        curl_setopt($ch, CURLOPT_HEADER, 0); // пустые заголовки
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // таймаут
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // просто отключаем проверку сертификата
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file); // сохранять куки в файл
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        $stderr = fopen("curl.log", "a");
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $stderr);

        $ex            = curl_exec($ch);
        $taskhtml = $ex;
        curl_close($ch);
        fclose($stderr);

        $result = array();

        // Парсим html
        $dom = new DOMDocument();
        @$dom->loadHTML($taskhtml);
        $xpath       = new DOMXPath($dom);
        $result = array();
        $result['sesskey'] = $xpath->query('//*[@name="sesskey"]')->item(0)->attributes->item(2)->nodeValue;

        $result['id'] =  $xpath->query('//*[@name="id"]')->item(0)->attributes->item(2)->nodeValue;
        $result['poasassignmentid'] =  $xpath->query('//*[@name="poasassignmentid"]')->item(0)->attributes->item(2)->nodeValue;
        $result['assigneeid'] =  $xpath->query('//*[@name="assigneeid"]')->item(0)->attributes->item(2)->nodeValue;

        $result['page'] = 'grade';
        $result['_qf__grade_form'] = '1';
        $result['mform_isexpanded_id_studentsubmission'] = '1';
        $result['mform_isexpanded_id_prevattemptsheader'] = '0';
        $result['grade'] = (string)$grade;
        $result['content'] = '';
        $result['commentfiles_filemanager'] = '';
        $result['submitbutton'] = "Save changes";

        return($result);
    }
    /**
     * Парсит страницу, для того что бы получить данные для запроса отправки комментов
     * @param string адрес сайта с комментарием
     * @return массив с полями и их значениями
     */
    public static function arrayForAjax($text, $task, $cookie_file)
    {
        // Получаем страницу для парсинга
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $task); // отправляем на
        curl_setopt($ch, CURLOPT_HEADER, 0); // пустые заголовки
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // таймаут
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // просто отключаем проверку сертификата
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file); // сохранять куки в файл
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        $stderr = fopen("curl.log", "a");
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $stderr);

        $ex            = curl_exec($ch);
        $taskhtml = $ex;
        curl_close($ch);
        fclose($stderr);

        // Парсим html
        $dom = new DOMDocument();
        @$dom->loadHTML($taskhtml);
        $xpath       = new DOMXPath($dom);
        $result = array();
        $result['sesskey'] = $xpath->query('//*[@name="sesskey"]')->item(0)->attributes->item(2)->nodeValue;

        $result['action'] = "add";

        $result['client_id'] = explode("-", $xpath->query('//*[@class="fd"]')->item(0)->attributes->item(1)->nodeValue)[2];

        $str = $xpath->query('//*[@class="poasassignment-table"]//a/@href')->item(0)->nodeValue;
        $result['itemid'] =  explode('/', $str)[count(explode('/', $str))-2];

        $result['area'] = 'poasassignment_comment';

        $result['courseid'] =  explode("?", $xpath->query('//*[@class="list-group-item list-group-item-action "]')->item(0)->attributes->item(1)->nodeValue)[1];
        $result['courseid'] =  explode("=", $result['courseid'])[1];

        $result['contextid'] =  explode('/', $str)[count(explode('/', $str))-5];

        $result['component'] = 'mod_poasassignment';
        $result['content'] = implode($text);

        return($result);
    }
}
