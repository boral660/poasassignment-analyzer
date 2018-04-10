<?php

include_once 'Cleaner.php';
include_once  'Tester.php';
include_once  'Reporter.php';

/**
 * Class MoodleParser Выполняет парсинг страницы с ответами на мудл
 */
class MoodleParser
{

    /**
     * @var string разметка страницы с ответами
     */
    private $answers_html = '';

    /**
     * @var array список курсов, заданий и студентов с ответами для проверки
     */
    private $links = array();
	
	 /**
     * @var array следует ли отослать результат проверки на email
     */
    private $send_result_on_email = false;

    /**
     * @var string путь к winRar
     */
    private $path_to_winrar = '';

    /**
     * @var string страница авторизации
     */
    private $login_url = 'http://edu.vstu.ru/login/index.php';

    /**
     * @var string страница c заданиями
     */
    private $task_url = '';


    /**
     * @var array номера заданий, которые необходимо проверить
     */
    private $task_id = array();

    /**
     * @var string путь к файлу куки
     */
    private $cookie_file = '/cookie.txt';

    /**
     * @var string путь к папке с сохраненными ответами
     */
    private $files_download_to = '/anwsers';

    /**
     * @var string логин преподавателя
     */
    private $username = '';

    /**
     * @var string почта преподавателя, на которую придет письмо со списком студентов
     */
    private $email = '';

    /**
     * @var string пароль преподавателя
     */
    private $password = '';

	/**
     * @var string запуск на linux системах
     */
    private  $linux_client = false;
	
    /**
     * @var string следует ли распаковывать файлы
     */
    private $unpack_answers = false;

    /**
     * @var string следует ли тестировать
     */
    private $build_and_compile = false;

    /**
     * @var string сохранить ли ответы студентов
     */
    private $save_answers = false;

    public function __construct()
    {
        $this->init();
    }

    /**
     * Определяет, удалось ли залогиниться
     * @param $data HTML страницы главной страницы (страницы после логина)
     * @return bool удалось ли залогиниться
     */
    public function isAuth($data)
    {
        return (preg_match('/page-login-index/', $data) !== 1) && (preg_match('/page-/', $data) === 1);
    }
	
	/**
     * Позволяет получить email указанный в файле
     */
    public function getEmail()
    {
        return $this->email;
    }

	/**
     * Следует ли отправлять результат тестирования
     */
    public function getSendResultOnEmail()
    {
        return $this->send_result_on_email;
    }
    /**
     * Определяет, удалось ли получить страницу с заданиями
     * @param $data HTML страницы с ответами
     * @return bool удалось ли получить страницу с заданиями
     */
    public function isGetCourse($data)
    {
        return (preg_match('/page-mod-poasassignment-view/', $data) === 1);
    }

    /**
     * Возвращает разметку страницы с ответами
     * @return string
     */
    public function getAnswersHtml()
    {
        return $this->answers_html;
    }

    /**
     * Выполняет авторизацию на мудл
     * @return bool удалось ли авторизоваться
     */
    public function login()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->login_url); // отправляем на
        curl_setopt($ch, CURLOPT_HEADER, 0); // пустые заголовки
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // таймаут4
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // просто отключаем проверку сертификата
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file); // сохранять куки в файл
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);
        curl_setopt($ch, CURLOPT_POST, 1); // использовать данные в post
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            'username' => $this->username,
            'password' => $this->password
        ));
        $ex      = curl_exec($ch);
        $is_auth = $this->isAuth($ex);
        curl_close($ch);

        return $is_auth;
    }

    /**
     * Выполняет парсинг всех заданий, идентификаторы которых указаны в конфигуранционном файле
     */
    public function parseAllTask()
    {
        foreach ($this->task_id as $task_id) {
            $is_get_course = $this->goToCourseAnswers($task_id);
            echo $is_get_course ? 'Course success' : 'Course failed';
            echo '<br>';
            if ($is_get_course === true) {
                $this->parse($task_id);
                $this->testAnswers();
                if (!$this->save_answers) {
                    Cleaner::removeDirectory('./' . $this->files_download_to);
                }
                //  $this->sendMail();
            }
            echo "<br><br>";
        }
    }

    /**
     * Выполняет переход на страницу с ответами
     * @param $task_id идентификатор задания для парсинга
     * @return bool удалось ли перейти на страницу с ответами
     */
    public function goToCourseAnswers($task_id)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->task_url}{$task_id}&page=submissions"); // отправляем на
        curl_setopt($ch, CURLOPT_HEADER, 0); // пустые заголовки
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // таймаут
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // просто отключаем проверку сертификата
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file); // сохранять куки в файл
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);

        $ex            = curl_exec($ch);
        $is_get_course = $this->isGetCourse($this->answers_html = $ex);
        curl_close($ch);

        return $is_get_course;
    }

    /**
     * Выполняет перевод в траслит
     * @param $str строка которую необходимо перевести
      * @param $onEng true - перевод с английского, false - перевод с русского
     */
    public function translit($str, $onEng)
    {
        $rus = array('А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ъ', 'Ы', 'Ь', 'Э', 'Ю', 'Я', 'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я');
        $lat = array('A', 'B', 'V', 'G', 'D', 'E', 'E', 'Gh', 'Z', 'I', 'Y', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'F', 'H', 'C', 'Ch', 'Sh', 'Sch', 'Y', 'Y', 'Y', 'E', 'Yu', 'Ya', 'a', 'b', 'v', 'g', 'd', 'e', 'e', 'gh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'ch', 'sh', 'sch', 'y', 'y', 'y', 'e', 'yu', 'ya');
        if ($onEng) {
            return str_replace($rus, $lat, $str);
        } else {
            return str_replace($lat, $rus, $str);
        }
    }

    /**
     * Выполняет парсинг страницы с ответами
     * @param $task_id идентификатор задания для парсинга
     */
    public function parse($task_id)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($this->answers_html);
        $xpath       = new DOMXPath($dom);
        $this->links = array();
        $row_index   = -1;
        $name        = null;
        $task        = null;

        $task_name                 = $xpath->query('//*[@id="region-main"]/div/div/h2/text()')->item(0)->nodeValue;
        $course_name               = $xpath->query('//*[@class="page-header-headings"]/h1')->item(0)->nodeValue;
        $this->links[$course_name] = array();

        $this->links[$course_name][$task_id] = array();

        while ($row_index < 200) { 
            $row_index++;
            $task = $xpath->query('//*[@id="mod-poasassignment-submissions_r' . $row_index . '_c7"]/a')->item(0);
            if ($task !== null && ($task->nodeValue === 'Add grade' || $task->nodeValue === 'Добавить оценку' || preg_match('/Оценка устарела/', $task->parentNode->nodeValue) === 1 || preg_match('/Outdated/', $task->parentNode->nodeValue) === 1)) {
                $name                                                          = $xpath->query('//*[@id="mod-poasassignment-submissions_r' . $row_index . '_c1"]/a')->item(0);
                $student_name                                                  = $this->translit($name->nodeValue, true);
                $this->links[$course_name][$task_id][$student_name]            = array();
                $this->links[$course_name][$task_id][$student_name]['profile'] = $name->getAttribute('href'); // ссылка на его профиль

                $this->links[$course_name][$task_id][$student_name]['answers'] = array();
                $task_index                                                    = 0;
                while (true) {
                    if ($xpath->query('.//*[@id="mod-poasassignment-submissions_r' . $row_index . '_c3"]//@href')->item($task_index) !== null) {
                        $this->links[$course_name][$task_id][$student_name]['answers'][$task_index]['answer_link'] = $xpath->query('.//*[@id="mod-poasassignment-submissions_r' . $row_index . '_c3"]//@href')->item($task_index)->nodeValue; // ссылка на ответ
                        $this->links[$course_name][$task_id][$student_name]['answers'][$task_index]['answer_name'] = $xpath->query('.//*[@id="mod-poasassignment-submissions_r' . $row_index . '_c3"]//text()')->item($task_index * 2)->nodeValue; // наименование ответа
                        $task_index++;
                    } else {
                        break;
                    }
                }
            }
            $name = null;
            $task = null;
        }

        $students_count = count($this->links[$course_name][$task_id]);
        echo "<h3>Ответ на << {$task_name} >>";
        echo "    в курсе << {$course_name} >>";
        echo "    предоставили {$students_count} студентов:</h3>";
        foreach ($this->links[$course_name][$task_id] as $key => $value) {
            echo '<p><a href="' . $value['profile'] . '">' . $key . '</a> предоставил для проверки ';

            foreach ($value['answers'] as $answer) {
                echo '<a href="' . $answer['answer_link'] . '">' . $answer['answer_name'] . '</a> ';
            }

            echo '</p>';
        }
    }

    /**
     * Инициализирует данными из конфигурационного файла
     */
    public function init()
    {
        $ini_array = parse_ini_file("conf.ini");
        if ($ini_array !== null && $ini_array !== false) {
            if ($ini_array['username'] !== null) {
                $this->username = $ini_array['username'];
            }

            if ($ini_array['password'] !== null) {
                $this->password = $ini_array['password'];
            }

            if ($ini_array['email'] !== null) {
                $this->email = $ini_array['email'];
            }

            if ($ini_array['login_url'] !== null) {
                $this->login_url = $ini_array['login_url'];
            }
            if ($ini_array['task_url'] !== null) {
                $this->task_url = $ini_array['task_url'];
            }

            if ($ini_array['task_id'] !== null) {
                $this->task_id = $ini_array['task_id'];
            }

            if ($ini_array['cookie_file'] !== null) {
                $this->cookie_file = $ini_array['cookie_file'];
            }

            if ($ini_array['files_download_to'] !== null) {
                $this->files_download_to = $ini_array['files_download_to'];
            }

            if ($ini_array['path_to_winrar'] !== null) {
                $this->path_to_winrar = $ini_array['path_to_winrar'];
            }

            if ($ini_array['path_to_CMake'] !== null) {
                Tester::setCMakePath($ini_array['path_to_CMake']);
            }

            if ($ini_array['path_to_QMake'] !== null) {
                Tester::setQMakePath($ini_array['path_to_QMake']);
            }

            if ($ini_array['path_to_Make'] !== null) {
               Tester::setMakePath($ini_array['path_to_Make']);
            }
            if ($ini_array['linux_client'] !== null) {
                Tester::setLinuxClient ($ini_array['linux_client']);
				$this->linux_client = $ini_array['linux_client'];
            }
            if ($ini_array['save_answers'] !== null) {
                $this->save_answers = $ini_array['save_answers'];
            }
            if ($ini_array['unpack_answers'] !== null) {
                $this->unpack_answers = $ini_array['unpack_answers'];
            }
            if ($ini_array['build_and_compile'] !== null) {
                $this->build_and_compile = $ini_array['build_and_compile'];
            }
			if ($ini_array['send_result_on_email'] !== null) {
                $this->send_result_on_email = $ini_array['send_result_on_email'];
            }
        }
    }

    /**
     * Распаковывает один файл
     * @param $file_path путь к файлу для распаковки
     * @param sting[] массив ошибок
     */
    public function unpackFile($file_path, &$errors)
    {
        $errors   = array();
        $file_ext = strrchr($file_path, '.');
        if ($file_ext == '.rar' || $file_ext == '.zip' || $file_ext == '.tar' || $file_ext == '.gz' || $file_ext == '.bz2' || $file_ext == '.7z' || $file_ext == '.z') {
            $name = strrchr($file_path, '/');
            $path = substr($file_path, 0, strlen($file_path) - strlen($name) + 1);
            if ($this->linux_client) {
                $comand = 'unrar x -o+ "' . $file_path . '" "' . $path . '"';
            } else {
                $comand = '"' . $this->path_to_winrar . '" x -o+ "' . $file_path . '" "' . $path . '" 2> rarError.txt';
            }
            exec($comand, $error);
            $header = "Error during unpacking file: ";
            Tester::readErrorOnFile("./rarError.txt", $header, $errors);
        }
    }

    /**
     * Скачивает выполненные работы
     */
    public function testAnswers()
    {
        foreach ($this->links as $course_name => $course) {
            foreach ($course as $task_name => $task) {
                foreach ($task as $name => $student) {
                    echo "<h3>Тестирование работы по задаче <<" . $task_name . ">> студента " . $name . ":</h3>";
                    foreach ($student['answers'] as $answer) {
                        $host            = $answer['answer_link'];
                        $output_filename = $answer['answer_name'];
                        $ch              = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $host);
                        curl_setopt($ch, CURLOPT_VERBOSE, 1);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_AUTOREFERER, false);
                        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file);
                        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);

                        $result = curl_exec($ch);
                        curl_close($ch);
                        $dir = $this->files_download_to;

                        if (!is_dir($dir)) {
                            mkdir($dir);
                        }

                        if (!is_dir($dir . '/' . $course_name)) {
                            mkdir($dir . '/' . $course_name);
                        }

                        if (!is_dir($dir . '/' . $course_name . '/' . $task_name)) {
                            mkdir($dir . '/' . $course_name . '/' . $task_name);
                        }

                        if (is_dir($dir . '/' . $course_name . '/' . $task_name . '/' . $name)) {
                            Cleaner::removeDirectory($dir . '/' . $course_name . '/' . $task_name . '/' . $name);
                        }
						mkdir($dir . '/' . $course_name . '/' . $task_name . '/' . $name);
                        $fp = fopen($dir . '/' . $course_name . '/' . $task_name . '/' . $name . '/' . $output_filename, 'w');
                        fwrite($fp, $result);
                        fclose($fp);
                        $errors    = array();
                        $file_path = $dir . '/' . $course_name . '/' . $task_name . '/' . $name;
                        if ($this->unpack_answers) {
                            $this->unpackFile($file_path . '/' . $output_filename, $errors);
                        }
                    }
                    if ($this->build_and_compile) {
                         Tester::testOnPath($file_path,$errors);
                    }
                    echo("<br>");
                    Cleaner::clearDir($file_path);
                }
            }
        }
    }
}

$mp = new MoodleParser();
ob_start();
$is_auth = $mp->login();
echo $is_auth ? 'Login success' : 'Login failed';
echo '<br>';
$my_html ='';
if ($is_auth === true) {	
    $mp->parseAllTask();
}
$my_html = ob_get_clean();
if($mp->getSendResultOnEmail()){
	Reporter::sendMail($my_html,$mp->getEmail());
}
echo $my_html;



