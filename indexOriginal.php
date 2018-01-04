<?php

/*class MoodleParserCreate {
  public static function create_parser() {
    return new MoodleParser();
  }
}*/

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
   * @var string страница авторизации
   */
  private $login_url = 'http://edu.vstu.ru/login/index.php';

  /**
   * @var string страница с курсом
   */
  private $course_url = 'http://edu.vstu.ru/mod/poasassignment/view.php?id=2991&page=submissions';

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

  public function __construct(){
    $this->init();
  }

  /**
   * Определяет, удалось ли залогиниться
   * @param $data HTML страницы главной страницы (страницы после логина)
   * @return bool удалось ли залогиниться
   */
  public function isAuth($data){
    return (preg_match('/page-login-index/', $data) !== 1) &&(preg_match('/page-/', $data) === 1);
  }

  /**
   * Определяет, удалось ли получить страницу с заданиями
   * @param $data HTML страницы с ответами
   * @return bool удалось ли получить страницу с заданиями
   */
  public function isGetCourse($data){
    return preg_match('/Задание/', $data) === 1;
  }

  /**
   * Возвращает разметку страницы с ответами
   * @return string
   */
  public function get_answers_html() {
    return $this->answers_html;
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
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);// таймаут4
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// просто отключаем проверку сертификата
    curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file); // сохранять куки в файл
    curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);
    curl_setopt($ch, CURLOPT_POST, 1); // использовать данные в post
    curl_setopt($ch, CURLOPT_POSTFIELDS, array(
        'username' => $this->username,
        'password' => $this->password,
    ));
	echo($this->login_url);
	echo '<br>';
	 echo($this->username);
	echo '<br>';
	 echo($this->password);
	echo '<br>';
	$ex=curl_exec($ch);
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
        $this->send_mail();
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
    curl_setopt($ch, CURLOPT_URL, "http://edu.vstu.ru/mod/poasassignment/view.php?id={$task_id}&page=submissions"); // отправляем на
    curl_setopt($ch, CURLOPT_HEADER, 0); // пустые заголовки
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);// таймаут4
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// просто отключаем проверку сертификата
    curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file); // сохранять куки в файл
    curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);

    $is_get_course = $this->isGetCourse($this->answers_html = curl_exec($ch));
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
    $course_name = $xpath->query('//*[@id="page-header"]/div[1]/div/h1')->item(0)->nodeValue;

    $this->links[$course_name] = array();
    $this->links[$course_name][$task_id] = array();

    while($row_index < 1000) {
      $row_index++;
      $task = $xpath->query('//*[@id="mod-poasassignment-submissions_r' . $row_index . '_c7"]/a')->item(0);
      if ($task !== null && ($task->nodeValue === 'Добавить оценку' || preg_match('/Оценка устарела/', $task->nodeValue) === 1)) {
        $name = $xpath->query('//*[@id="mod-poasassignment-submissions_r' . $row_index . '_c1"]/a')->item(0);
        $student_name = $name->nodeValue;
        $this->links[$course_name][$task_id][$student_name] = array();
        $this->links[$course_name][$task_id][$student_name]['profile'] = $name->getAttribute('href'); // ссылка на его профиль

        $this->links[$course_name][$task_id][$student_name]['answers'] = array();
        $task_index = 0;
        while(true) {
          if ($xpath->query('.//*[@id="mod-poasassignment-submissions_r' . $row_index . '_c3"]//@href')->item($task_index) !== null) {
            $this->links[$course_name][$task_id][$student_name]['answers'][$task_index]['answer_link']
                = $xpath->query('.//*[@id="mod-poasassignment-submissions_r' . $row_index . '_c3"]//@href')->item($task_index)->nodeValue; // ссылка на ответ
            $this->links[$course_name][$task_id][$student_name]['answers'][$task_index]['answer_name']
                = $xpath->query('.//*[@id="mod-poasassignment-submissions_r' . $row_index . '_c3"]//text()')->item($task_index)->nodeValue; // наименование ответа
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

    echo "<h3>Ответ на <<{$task_name}>> в курсе <<{$course_name}>> предоставили {$students_count} студентов:</h3>";
    foreach($this->links[$course_name][$task_id] as $key => $value) {
      echo '<p><a href="'. $value['profile'] .'">' . $key . '</a> предоставил для проверки ';

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

    foreach($this->links as $course_name => $course) {
      foreach($course as $task_name => $task) {
        $students_count = count($task);
        $students_list .= "<h3>Ответ на <<{$task_name}>> в курсе <<{$course_name}>> предоставили {$students_count} студентов:</h3>";
        foreach ($task as $name => $student) {
          $students_list .= '<p><a href="'. $student['profile'] .'">' . $name . '</a> предоставил для проверки ';
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

    $headers  = "Content-type: text/html; charset=windows-1251 \r\n";
    $headers .= "From: MoodleParser <grvlter@gmail.com>\r\n";
    /*$headers .= "Bcc: birthday-archive@example.com\r\n";*/

    if (mail($this->email, $subject, $message, $headers)) {
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
   * Скачивает выполненные работы
   */
  public function save_answers() {
    foreach($this->links as $course_name => $course) {
      foreach($course as $task_name => $task) {
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
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file); // сохранять куки в файл
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);

            $result = curl_exec($ch);
            curl_close($ch);

            $dir = $this->files_download_to;
            if (!is_dir($dir)) {
              mkdir($dir);
            }

            if (!is_dir($dir . '\\' . iconv("UTF-8", "Windows-1251", $course_name))) {
              mkdir($dir . '\\' . iconv("UTF-8", "Windows-1251", $course_name));
            }

            if (!is_dir($dir . '\\' . iconv("UTF-8", "Windows-1251", $course_name) . '\\' . iconv("UTF-8", "Windows-1251", $task_name))) {
              mkdir($dir . '\\' . iconv("UTF-8", "Windows-1251", $course_name) . '\\' . iconv("UTF-8", "Windows-1251", $task_name));
            }

            if (!is_dir($dir . '\\' . iconv("UTF-8", "Windows-1251", $course_name) . '\\' . iconv("UTF-8", "Windows-1251", $task_name) . '\\' . iconv("UTF-8", "Windows-1251", $name))) {
              mkdir($dir . '\\' . iconv("UTF-8", "Windows-1251", $course_name) . '\\' . iconv("UTF-8", "Windows-1251", $task_name) . '\\' . iconv("UTF-8", "Windows-1251", $name));
            }

            $fp = fopen($dir . '\\' . iconv("UTF-8", "Windows-1251", $course_name) . '\\' . iconv("UTF-8", "Windows-1251", $task_name) . '\\' . iconv("UTF-8", "Windows-1251", $name) . '\\' . iconv("UTF-8", "Windows-1251", $output_filename), 'w');
            fwrite($fp, $result);
            fclose($fp);
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