<?php

/* class MoodleParserCreate {
  public static function create_parser() {
  return new MoodleParser();
  }
  } */

/**
 * Class MoodleParser Выполняет парсинг страницы с ответами на мудл
 */
class MoodleParser {

    /**
     * @var string разметка страницы с ответами
     */
    private $answers_html = '';

    /**
     * @var array список курсов, заданий и студентов с ответами для проверки
     */
    private $links = array();

    /**
     * @var string путь к winRar
     */
    private $path_to_winrar = 'C:\\Program Files\\WinRAR\\WinRAR.exe';

    /**
     * @var string страница авторизации
     */
    private $login_url = 'http://edu.vstu.ru/login/index.php';

    /**
     * @var string страница c заданиями
     */
    private $task_url = '';

    /**
     * @var string путь к cMake
     */
    private $path_to_CMake = 'C:\\Program Files\\CMake\\bin';

  
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
    private $username = 'borzih.a';

    /**
     * @var string почта преподавателя, на которую придет письмо со списком студентов
     */
    private $email = 'boral660@gmail.com';

    /**
     * @var string пароль преподавателя
     */
    private $password = 'qweQwe1$,560';

    public function __construct() {
        $this->init();
    }

    /**
     * Определяет, удалось ли залогиниться
     * @param $data HTML страницы главной страницы (страницы после логина)
     * @return bool удалось ли залогиниться
     */
    public function isAuth($data) {
        return (preg_match('/page-login-index/', $data) !== 1) && (preg_match('/page-/', $data) === 1);
    }

    /**
     * Производит построение проекта используя CMakeLists файл.
     * @param string путь к файлу
     */
    public function building_project($path) {
        if (!is_dir($path . '/build')) {
            mkdir($path . '/build');
        }
        $cmake_path = $this->create_cmakelist($path);
        if ($cmake_path != NULL) {
            $comand = '"' . $this->path_to_CMake . '\\cmake.exe" -B"' . $path . '\\build" -H"' . $path . '\\build" ';
            exec($comand, $errors);
        }
    }

    /**
     * Создаёт файл для построенния проекта
     * @param string путь к файлам проекта
     */
    public function create_cmakelist($path) {
        $cmake_path = $path . "/build/CMakeLists.txt";
        $source_files = $this->recursiveGlob($path, '*.cpp');
        if (!empty($source_files)) {
            $header_files = $this->recursiveGlob($path, '*.h');
            // Создание файла
            $fp = fopen($cmake_path, 'w');
            // Наполнение файла
            $body = "";
            // Установление минимальной версии cmake
            $body .= "cmake_minimum_required(VERSION 2.8)\r\n";
            // Установление файлов с кодом
            $body .= "set(SOURCE";
            foreach ($source_files as $sfile) {
                $body .= " ";
                $res = substr($sfile, strlen($path) + 1);
                $res = str_replace('\\', '/', $res);
                $body .= '../' . $res;
            }
            $body .= ")\r\n";

            if (!empty($header_files)) {
                // Заголовочные файлы
                $body .= "set(HEADER";
                foreach ($header_files as $hfile) {
                    $body .= " ";
                    $res = substr($hfile, strlen($path) + 1);
                    $res = str_replace('\\', '/', $res);
                    $body .= '../' . $res;
                }
                $body .= ")\r\n";
            }
            $body .= "add_executable(main \${SOURCE} \${HEADER})\r\n";
            $body .="SET(MAKE_C_COMPILER C:/MinGW/bin/gcc.exe)\r\n";
            $body .="SET(MAKE_CXX_COMPILER C:/MinGW/bin/g++.exe)\r\n";
            fwrite($fp, $body);
            fclose($fp);
            return $cmake_path;
        }
        return NULL;
    }

    /**
     * Определяет, удалось ли получить страницу с заданиями
     * @param $data HTML страницы с ответами
     * @return bool удалось ли получить страницу с заданиями
     */
    public function isGetCourse($data) {
        return (preg_match('/page-mod-poasassignment-view/', $data) === 1);
    }

    /**
     * Возвращает разметку страницы с ответами
     * @return string
     */
    public function get_answers_html() {
        return $this->answers_html;
    }

    /**
     * Рекурсивный проход по каталогу
     * @param string - путь к папке, в которой должен осуществлятся поиск
     * @param string - маска, по которой осуществляется поиск
     * @return array - полный список найденных файлов
     */
    function recursiveGlob($startDir, $fileMask) {
        $found = glob($startDir . DIRECTORY_SEPARATOR . $fileMask);
        $dirs = glob($startDir . DIRECTORY_SEPARATOR . "*", GLOB_ONLYDIR);
        foreach ($dirs as $dir)
            $found = array_merge($found, $this->recursiveGlob($dir, $fileMask));
        return $found;
    }

    /**
     * Рекурсивный проход по каталогу с вызовом $callback на каждом файле
     * @param string - путь к папке, в которой должен осуществлятся поиск
     * @param string - маска, по которой осуществляется поиск
     * @return $callback - найденный файл
     */
    function globWalk($startDir, $fileMask, $callback) {
        $found = glob($startDir . DIRECTORY_SEPARATOR . $fileMask);
        foreach ($found as $path)
            $callback($path);
        $dirs = glob($startDir . DIRECTORY_SEPARATOR . "*", GLOB_ONLYDIR);
        foreach ($dirs as $dir)
            $this->globWalk($dir, $fileMask, $callback);
    }

    /**
     * Выполняет авторизацию на мудл
     * @return bool удалось ли авторизоваться
     */
    public function login() {

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
            'password' => $this->password,
        ));
        $ex = curl_exec($ch);
        $is_auth = $this->isAuth($ex);
        curl_close($ch);

        return $is_auth;
    }

    /**
     * Выполняет парсинг всех заданий, идентификаторы которых указаны в конфигуранционном файле
     */
    public function parse_all() {
        foreach ($this->task_id as $task_id) {
            $is_get_course = $this->go_to_course_answers($task_id);
            echo $is_get_course ? 'Course success' : 'Course failed';
            echo '<br>';

            if ($is_get_course === true) {
                $this->parse($task_id);

                $this->save_answers();
                //  $this->send_mail();
            }
        }
    }

    /**
     * Выполняет переход на страницу с ответами
     * @param $task_id идентификатор задания для парсинга
     * @return bool удалось ли перейти на страницу с ответами
     */
    public function go_to_course_answers($task_id) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->task_url}{$task_id}&page=submissions"); // отправляем на
        curl_setopt($ch, CURLOPT_HEADER, 0); // пустые заголовки
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // таймаут4
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // просто отключаем проверку сертификата
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file); // сохранять куки в файл
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);

        $ex = curl_exec($ch);
        $is_get_course = $this->isGetCourse($this->answers_html = $ex);

        curl_close($ch);

        return $is_get_course;
    }

    /**
     * Выполняет парсинг страницы с ответами
     * @param $task_id идентификатор задания для парсинга
     */
    public function parse($task_id) {
        $dom = new DOMDocument();
        @$dom->loadHTML($this->answers_html);
        $xpath = new DOMXPath($dom);

        $this->links = array();
        $row_index = -1;
        $name = null;
        $task = null;

        $task_name = $xpath->query('//*[@id="region-main"]/div/h2/text()')->item(0)->nodeValue;
        $course_name = $xpath->query('//*[@id="page-header"]/div/div/h1')->item(0)->nodeValue;
        $this->links[$course_name] = array();
        $this->links[$course_name][$task_id] = array();

        while ($row_index < 10) { // Конечно увеличить !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            $row_index++;
            $task = $xpath->query('//*[@id="mod-poasassignment-submissions_r' . $row_index . '_c7"]/a')->item(0);
            if ($task !== null && ($task->nodeValue === 'Add grade' || $task->nodeValue === 'Добавить оценку' || preg_match('/Оценка устарела/', $task->parentNode->nodeValue) === 1 || preg_match('/Outdated/', $task->parentNode->nodeValue) === 1)) {
                $name = $xpath->query('//*[@id="mod-poasassignment-submissions_r' . $row_index . '_c1"]/a')->item(0);
                $student_name = $name->nodeValue;
                $this->links[$course_name][$task_id][$student_name] = array();
                $this->links[$course_name][$task_id][$student_name]['profile'] = $name->getAttribute('href'); // ссылка на его профиль

                $this->links[$course_name][$task_id][$student_name]['answers'] = array();
                $task_index = 0;
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
        echo "	в курсе << {$course_name} >>";
        echo"	предоставили {$students_count} студентов:</h3>";
        foreach ($this->links[$course_name][$task_id] as $key => $value) {
            echo '<p><a href="' . $value['profile'] . '">' . $key . '</a> предоставил для проверки ';

            foreach ($value['answers'] as $answer) {
                echo '<a href="' . $answer['answer_link'] . '">' . $answer['answer_name'] . '</a> ';
            }

            echo '</p>';
        }
    }

    /**
     * Выполняет отправку на почту письма со списком студентов, работы которых нужно проверить
     */
    public function send_mail() {
        $subject = "Список студентов";

        $students_list = '';

        foreach ($this->links as $course_name => $course) {
            foreach ($course as $task_name => $task) {
                $students_count = count($task);
                $students_list .= "<h3>Ответ на <<{$task_name}>> в курсе <<{$course_name}>> предоставили {$students_count} студентов:</h3>";
                foreach ($task as $name => $student) {
                    $students_list .= '<p><a href="' . $student['profile'] . '">' . $name . '</a> предоставил для проверки ';
                    foreach ($student['answers'] as $answer) {
                        $students_list .= '<a href="' . $answer['answer_link'] . '">' . $answer['answer_name'] . '</a> ';
                    }
                }
            }
        }

        $message = '
      <html>
          <head>
              <title>Студенты, работы которых нужно проверить</title>
          </head>
          <body>
              ' . $students_list . '
          </body>
      </html>';

        $headers = "Content-type: text/html; charset=windows-1251 \r\n";
        $headers .= "From: MoodleParser <boral6601@gmail.com>\r\n";
        /* $headers .= "Bcc: birthday-archive@example.com\r\n"; */

        //  if (mail($this->email, $subject, $message, $headers)) {
        //  echo '<p>Письмо успешно отправлено</p>';
        // }
        if (mail("boral660@gmail.com", "My Subject", "Line 1\nLine 2\nLine 3", $headers)) {
            echo '<p>Письмо успешно отправлено</p>';
        }
    }

    /**
     * Инициализирует данными из конфигурационного файла
     */
    public function init() {
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
        }
    }

    /**
     * Распаковывает один файл
     * @param $file_path путь к файлу для распаковки
     */
    public function unpack_file($file_path) {

        $errors = array();
        $file_ext = strrchr($file_path, '.');
        if ($file_ext == '.rar' || $file_ext == '.zip' || $file_ext == '.tar' || $file_ext == '.gz' || $file_ext == '.bz2' || $file_ext == '.7z' || $file_ext == '.z') {
            $name = strrchr($file_path, '\\');
            $path = substr($file_path, 0, strlen($file_path) - strlen($name) + 1);
            exec('"' . $this->path_to_winrar . '" x -o+ "' . $file_path . '" "' . $path . '"', $errors);
        }
    }

    /**
     * Тестирует выполненные работы
     */
    public function test_answers() {
        
    }

    /**
     * Скачивает выполненные работы
     */
    public function save_answers() {
        foreach ($this->links as $course_name => $course) {
            foreach ($course as $task_name => $task) {
                foreach ($task as $name => $student) {
                    foreach ($student['answers'] as $answer) {
                        $host = $answer['answer_link'];
                        $output_filename = $answer['answer_name'];
                        $ch = curl_init();
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

                        if (!is_dir($dir . '\\' . $course_name)) {
                            mkdir($dir . '\\' . $course_name);
                        }

                        if (!is_dir($dir . '\\' . $course_name . '\\' . $task_name)) {
                            mkdir($dir . '\\' . $course_name . '\\' . $task_name);
                        }

                        if (!is_dir($dir . '\\' . $course_name . '\\' . $task_name . '\\' . $name)) {
                            mkdir($dir . '\\' . $course_name . '\\' . $task_name . '\\' . $name);
                        }

                        $fp = fopen($dir . '\\' . $course_name . '\\' . $task_name . '\\' . $name . '\\' . $output_filename, 'w');
                        fwrite($fp, $result);
                        fclose($fp);
                        $file_path = $dir . '\\' . $course_name . '\\' . $task_name . '\\' . $name . '\\' . $output_filename;
                        $this->unpack_file($file_path);
                        $this->building_project($dir . '\\' . $course_name . '\\' . $task_name . '\\' . $name);
                    }
                }
            }
        }
    }

}

$mp = new MoodleParser();

$is_auth = $mp->login();
echo $is_auth ? 'Login success' : 'Login failed';
echo '<br>';

if ($is_auth === true) {
    $mp->parse_all();
}