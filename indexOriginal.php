<?php

include 'Cleaner.php';

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
     * @var string путь к cMake
     */
    private $path_to_CMake = '';

    /**
     * @var string путь к qMake
     */
    private $path_to_QMake = '';

    /**
     * @var string путь к Make
     */
    private $path_to_Make = '';

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

    /**
     * @var string запуск на linux системах
     */
    private $linux_client = false;

    /**
     * @var string следует ли распаковывать файлы
     */
    private $unpack_answers = false;

    /**
     * @var string следует ли тестировать
     */
    private $build_and_compil = false;

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
     * Производит построение проекта используя CMakeLists файл.
     * @param string путь к файлу
     * @param errors массив ошибок
     * @return 0 - собран qt проект, 1 - собран проект без qt, 2 - проект не был собран
     */
    public function buildProject($path, &$errors)
    {
        $result = 2;
        //Удаляем директорию
        if (is_dir($path . '/build')) {
            $this->removeDirectory($path . '/build');
        }
        mkdir($path . '/build');
        $qtfiles = $this->recursiveGlob($path, '*.pro');
        // Если это не qt проект
        if (empty($qtfiles)) {
            // Формируем cmake лист
            $cmake_path = $this->createCmakelist($path);
            if ($cmake_path != null) {
                // Составляем команду
                if ($this->linux_client) {
                    $comand = 'cmake  -G "Unix Makefiles"';
                } else {
                    $comand = '"' . $this->path_to_CMake . '\\cmake.exe" -G "MinGW Makefiles"';
                }
                $comand .= ' -B"' . $path . '/build" -H"' . $path . '/build"' . ' 2> cmakeError.txt';
                exec($comand, $error);
                $result = 1;

                // Выводим ошибки
                $header = "Error during build on cmake: ";
                $this->readErrorOnFile("./cmakeError.txt", $header, $errors);
            }
        } else {
            // Составляем команду
            foreach ($qtfiles as $qfile) {
                if ($this->linux_client) {
                    $comand = "qmake";
                } else {
                    $comand = '"' . $this->path_to_QMake . '\\qmake.exe"';
                }
                $comand .= ' "' . __DIR__ . '/' . dirname($qfile) . '" 2> qmakeError.txt';
                exec($comand, $error);
            }
            $result = 0;
            // Выводим ошибки
            $header = "Error during build on qmake: ";
            $this->readErrorOnFile("./qmakeError.txt", $header, $errors);
        }


        return $result;
    }
    /**
     * Производит компиляцию проекта используя MakeFile
     * @param string путь к файлу
     * @param int  0 - проект qt, 1 - проект без qt
     * @param string[] массив ошибок
     */
    public function compileProject($path, $qt, &$errors)
    {
        // Составляем команду для компиляции
        if ($this->linux_client) {
            $comand = "make";
        } else {
            $comand = '"' . $this->path_to_Make . '\\make.exe"';
        }
        // Если файл не qt указываем откуда брать файлы
        if ($qt == 1) {
            $comand .= ' --directory="' . $path . '/build"';
        }

        $comand .= ' > makeLog.txt 2> makeError.txt';
        exec($comand, $error);
        // Вывести сообщение об ошибке
        $header = "Error during compilation with make: ";
        $this->readErrorOnFile("./makeError.txt", $header, $errors);
    }
    /**
     * Позволяет выводить на экран сообщения об ошибке из файла
     * @param string путь к файлу
     * @param string заголовок ошибки
     * @param string массив ошибок
     */
    public function readErrorOnFile($path, $header_string, &$errors)
    {
        $lines = file($path);
        if (!empty($lines)) {
            array_push($errors, $header_string);
        }
        foreach ($lines as $line) {
            $line = iconv('CP866', 'UTF-8', $line);
            array_push($errors, $line);
        }
    }

    /**
     * Создаёт файл для построенния проекта
     * @param string путь к файлам проекта
     */
    public function createCmakelist($path)
    {
        $cmake_path   = $path . "/build/CMakeLists.txt";
        $source_files = $this->recursiveGlob($path, '*.cpp');
        if (!empty($source_files)) {
            $header_files = $this->recursiveGlob($path, '*.h');
            // Создание файла
            $fp           = fopen($cmake_path, 'w');
            // Наполнение файла
            $body         = "";
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
            # Widgets finds its own dependencies.
            $body .= "add_executable(main \${SOURCE} \${HEADER})\r\n";
            fwrite($fp, $body);
            fclose($fp);
            return $cmake_path;
        }
        return null;
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
     * Рекурсивный проход по каталогу
     * @param string - путь к папке, в которой должен осуществлятся поиск
     * @param string - маска, по которой осуществляется поиск
     * @return array - полный список найденных файлов
     */
    public function recursiveGlob($startDir, $fileMask)
    {
        $found = glob($startDir . DIRECTORY_SEPARATOR . $fileMask);
        $dirs  = glob($startDir . DIRECTORY_SEPARATOR . "*", GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $found = array_merge($found, $this->recursiveGlob($dir, $fileMask));
        }
        return $found;
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
                    $this->removeDirectory('./' . $this->files_download_to);
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

        while ($row_index < 10) { // Конечно увеличить !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
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
     * Выполняет отправку на почту письма со списком студентов, работы которых нужно проверить
     */
    public function sendMail()
    {
        // Не реализованна
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
                $this->path_to_CMake = $ini_array['path_to_CMake'];
            }

            if ($ini_array['path_to_QMake'] !== null) {
                $this->path_to_QMake = $ini_array['path_to_QMake'];
            }

            if ($ini_array['path_to_Make'] !== null) {
                $this->path_to_Make = $ini_array['path_to_Make'];
            }
            if ($ini_array['linux_client'] !== null) {
                $this->linux_client = $ini_array['linux_client'];
            }
            if ($ini_array['save_answers'] !== null) {
                $this->save_answers = $ini_array['save_answers'];
            }
            if ($ini_array['unpack_answers'] !== null) {
                $this->unpack_answers = $ini_array['unpack_answers'];
            }
            if ($ini_array['build_and_compil'] !== null) {
                $this->build_and_compil = $ini_array['build_and_compil'];
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
            $this->readErrorOnFile("./rarError.txt", $header, $errors);
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
                    echo "<h3>Тестирование работы студента " . $name . ":</h3>";
                    foreach ($student['answers'] as $answer) {
                        echo "<h4>  Тестирование файла " . $answer['answer_name'] . ":</h4>";
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

                        if ($this->build_and_compil) {
                            $result = $this->buildProject($file_path, $errors);
                            if ($result != 2) {
                                $this->compileProject($file_path, $result, $errors);
                            }
                        }
                        if (empty($errors)) {
                            echo "Testing success";
                        }

                        foreach ($errors as $error) {
                            echo($error . "<br>");
                        }
                    }

                    echo("<br>");
                    Cleaner::clearDir($file_path);
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
    $mp->parseAllTask();
}

