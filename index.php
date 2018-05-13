<?php
include_once 'Cleaner.php';
include_once 'Tester.php';
include_once 'Reporter.php';

/**
 * Class MoodleParser Выполняет парсинг страницы с ответами на мудл.
 */
class MoodleParser
{
	/**
     * @var string разметка страницы с ответами
     */
	private $answers_html = '';

	/**
     * @var string разметка страницы с протоколами
     */
	private $protocols_html = '';

	/**
     * @var array список курсов, заданий и студентов с ответами для проверки
     */
	private $links = array();

	/**
     * @var array следует ли отослать результат проверки на email
     */
	private $send_result_on_email = false;

	/**
     * @var array следует ли отослать результат в комментарии
     */
	private $write_on_comment = false;

	/**
     * @var sting куда следует записывать результат
     */
	private $write_on = 'console';

	/**
     * @var string путь к winRar
     */
	private $path_to_winrar = '';

	/**
     * @var string страница авторизации
     */
	private $login_url = '';

	/**
     * @var string страница c заданиями
     */
	private $task_url = '';

	/**
     * @var array номера заданий, которые необходимо проверить
     */
	private $task_id = array();

	/**
     * @var string страница c протоколами
     */
	private $protocol_url = '';

	/**
     * @var array номера заданий для которых необходимо проверить наличие протоколов
     */
	private $protocol_id = array();

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
	private $linux_client = false;

	/**
     * @var string следует ли распаковывать файлы
     */
	private $unpack_answers = false;

	/**
     * @var string следует ли тестировать
     */
	private $build_and_compile = false;

	/**
     * @var int оценка в случае провала
     */
	private $grade_if_fail = -1;

	/**
     * @var string сохранить ли ответы студентов
     */
	private $save_answers = false;

	public function __construct()
	{
		try {
			$this->init();
		} catch (Exception $e) {
			echo 'Выброшено исключение : ', $e->getMessage(), '';
			exit;
		}
	}

	/**
     * Определяет, удалось ли залогиниться.
     *
     * @param $data HTML страницы главной страницы (страницы после логина)
     *
     * @return bool удалось ли залогиниться
     */
	public function isAuth($data)
	{
		return (preg_match('/page-login-index/', $data) !== 1) && (preg_match('/page-/', $data) === 1);
	}
	/**
     * Позволяет получить информацию о том, какой вывод считать приорететным
     */
	public function writeOn()
	{
		return $this->write_on;
	}

	/**
     * Позволяет получить email указанный в файле.
     */
	public function getEmail()
	{
		return $this->email;
	}

	/**
     * Следует ли отправлять результат тестирования.
     */
	public function getSendResultOnEmail()
	{
		return $this->send_result_on_email;
	}
	/**
     * Определяет, удалось ли получить страницу с заданиями.
     *
     * @param $data HTML страницы с ответами
     *
     * @return bool удалось ли получить страницу с заданиями
     */
	public function isGetCourse($data)
	{
		return preg_match('/page-mod-poasassignment-view/', $data) === 1;
	}

	/**
     * Определяет, удалось ли получить страницу с протоколами
     *
     * @param $data HTML страницы с протоколами
     *
     * @return bool удалось ли получить страницу с протоколами
     */
	public function isGetCourseProtocol($data)
	{
		return preg_match('/page-mod-assign-grading/', $data) === 1;
	}

	/**
     * Возвращает разметку страницы с ответами.
     *
     * @return string
     */
	public function getAnswersHtml()
	{
		return $this->answers_html;
	}

	/**
     * Выполняет авторизацию на мудл.
     *
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
			'password' => $this->password,
		));
		$stderr = fopen('curl.log', 'w');
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_STDERR, $stderr);

		$ex = curl_exec($ch);
		$is_auth = $this->isAuth($ex);
		curl_close($ch);
		fclose($stderr);

		return $is_auth;
	}

	/**
     * Выполняет парсинг всех заданий, идентификаторы которых указаны в конфигуранционном файле.
     */
	public function parseAllTask()
	{
		Cleaner::removeDirectory('.'. DIRECTORY_SEPARATOR .$this->files_download_to);
		Cleaner::clearDir($file_path);
		foreach ($this->task_id as $task_id) {
			$is_get_course = $this->goToCourseAnswers($task_id);
			echo $is_get_course ? 'Курс получен' : 'Курс получить не удалось';
			echo '<br>';
			if ($is_get_course === true) {
				$this->parse($task_id);
				$this->testAnswers();
			}
			echo '<br><br>';
		}
		foreach ($this->protocol_id as $protocol_id) {
			$is_get_course = $this->goToCourseProtocols($protocol_id);
			echo $is_get_course ? 'Курс получен' : 'Курс получить не удалось';
			echo '<br>';
			if ($is_get_course === true) {
				$this->parseProtocols($protocol_id);
				//$this->testAnswers();
			}
			echo '<br><br>';
		}
		if (!$this->save_answers) {
			Cleaner::removeDirectory('.'. DIRECTORY_SEPARATOR .$this->files_download_to);
		}
	}


	/**
     * Выполняет переход на страницу с протоколами.
     *
     * @param $protocol_id идентификатор задания для которого необходимо проверить протокол
     *
     * @return bool удалось ли перейти на страницу с ответами
     */
	public function goToCourseProtocols($protocol_id)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "{$this->protocol_url}{$protocol_id}&action=grading"); // отправляем на
		curl_setopt($ch, CURLOPT_HEADER, 0); // пустые заголовки
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // возвратить то что вернул сервер
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // следовать за редиректами
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // таймаут
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // просто отключаем проверку сертификата
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file); // сохранять куки в файл
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);

		$stderr = fopen('curl.log', 'a');
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_STDERR, $stderr);

		$ex = curl_exec($ch);
		$is_get_course =$this->isGetCourseProtocol($this->protocols_html = $ex);
		curl_close($ch);
		fclose($stderr);

		return $is_get_course;
	}
	/**
     * Выполняет переход на страницу с ответами.
     *
     * @param $task_id идентификатор задания для парсинга
     *
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

		$stderr = fopen('curl.log', 'a');
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_STDERR, $stderr);

		$ex = curl_exec($ch);
		$is_get_course = $this->isGetCourse($this->answers_html = $ex);
		curl_close($ch);
		fclose($stderr);

		return $is_get_course;
	}

	/**
     * Выполняет перевод в траслит
     *
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
     * Выполняет перевод в транслит названия всех папок
     *
     * @param $sdir - папка с в которой необходимо рекурсивно провести операцию
     * @throws \Exception если не удалось переименовать
     */
	public function translitAllDir($sdir)
	{
		if ($this->linux_client) {
			$sdir = str_replace('\\', '/', $sdir);
		} else {
			$sdir = str_replace('/', '\\', $sdir);
		}

		$dirs  = glob($sdir . DIRECTORY_SEPARATOR . "*", GLOB_ONLYDIR);
		foreach ($dirs as $dir) {
			$this->translitAllDir($dir);
		}
		$str = strripos($sdir, DIRECTORY_SEPARATOR) + 1;
		/** @var string $dirname */
		$dirname = substr($sdir,$str);
		$str = strlen($sdir) - $str;
		$destDirectory = substr($sdir,0,$str*(-1)) . $this->translit($dirname,true);

		if ($sdir != $destDirectory) {
			if (!rename($sdir, $destDirectory))
				throw new Exception('Невозможно переименовать папку: ' . $sdir);
		}
	}
	/**
     * Выполняет перевод в транслит названия всех файлов в папке
     *
     * @param $dir - папка с в которой необходимо рекурсивно провести операцию
     */
	public function translitAllFiles($dir)
	{
		$this->translitAllDir($dir);
		$dirs  = glob($dir . DIRECTORY_SEPARATOR . "*", GLOB_ONLYDIR); 
		$found = Tester::recursiveGlob($dir, "*.{c,cpp,h}");

		foreach ($found as $file) {
			if(!rename($file, $this->translit($file,true)))
				throw new Exception('Невозможно переименовать файл: ' . $file);
		}
		return $found;
	}
	/**
     * Выполняет парсинг страницы с протоколами
     *
     * @param $protocol_id идентификатор для парсинга
     */
	public function parseProtocols($protocol_id)
	{
		$dom = new DOMDocument();
		@$dom->loadHTML($this->protocols_html);
		$xpath = new DOMXPath($dom);
		$this->links = array();
		$row_index = -1;
		$name = null;
		$task = null;

		$task_name = $xpath->query('//*[@id="region-main"]//h2/text()')->item(0)->nodeValue;
		$course_name = $xpath->query('//*[@class="page-header-headings"]/h1')->item(0)->nodeValue;

		$this->links[$course_name] = array();
		$this->links[$course_name][$task_id] = array();

		$pos = strripos($this->protocol_url, '/');
		$moodle_url = substr($this->protocol_url, 0, $pos + 1);
		while ($row_index < 200) {
			++$row_index;
			$task = $xpath->query('//*[@id="mod_assign_grading_r'.$row_index.'_c4"]/div')->item(0);
			if ($task !== null) {
				$name = $xpath->query('//*[@id="mod_assign_grading_r'.$row_index.'_c2"]/a')->item(0);
				$student_name = $name->nodeValue;
				$this->links[$course_name][$task_id][$student_name] = array();
				$this->links[$course_name][$task_id][$student_name]['profile'] = $name->getAttribute('href'); // ссылка на его профиль
				$this->links[$course_name][$task_id][$student_name]['grade'] =  $xpath->query('//*[@id="mod_assign_grading_r'.$row_index.'_c5"]//@href')->item(0)->nodeValue;
				$this->links[$course_name][$task_id][$student_name]['answers'] = array();
				$this->links[$course_name][$task_id][$student_name]['lastModified'] =  $xpath->query('//*[@id="mod_assign_grading_r'.$row_index.'_c7"]')->item(0)->nodeValue;
				$this->links[$course_name][$task_id][$student_name]['lastGrade'] = $xpath->query('//*[@id="mod_assign_grading_r'.$row_index.'_c10"]')->item(0)->nodeValue;
				$task_index = 0;
				while (true) {
					if ($xpath->query('.//*[@id="mod_assign_grading_r'.$row_index.'_c8"]//@href')->item($task_index) !== null) {
						$this->links[$course_name][$task_id][$student_name]['answers'][$task_index]['answer_link'] = $xpath->query('.//*[@id="mod_assign_grading_r'.$row_index.'_c8"]//@href')->item($task_index)->nodeValue; // ссылка на ответ
						$this->links[$course_name][$task_id][$student_name]['answers'][$task_index]['answer_name'] = $this->translit($xpath->query('.//*[@id="mod_assign_grading_r' .$row_index.'_c8"]//text()')->item($task_index * 2)->nodeValue, true); // наименование ответа
						++$task_index;
					} else {
						break;
					}
				}
				if($xpath->query('.//*[@id="mod_assign_grading_r'.$row_index.'_c8"]//@href')->item(0) == null)
					Reporter::grageProtocol("{$this->protocol_url}{$protocol_id}$action=grading",$this->links[$course_name][$task_id][$student_name]['grade'],$this->grade_if_fail,$this->cookie_file);
			}
			$name = null;
			$task = null;
		}

		$students_count = count($this->links[$course_name][$task_id]);
		echo "<h3>Протоколы {$task_name}  ";
		echo " в курсе  {$course_name}:</h3>";
		foreach ($this->links[$course_name][$task_id] as $key => $value) {
			if($value['answers'] != null ){
				echo '<p><a href="'.$value['profile'].'">'.$key.'</a> предоставил для проверки ';

				foreach ($value['answers'] as $answer) {
					echo '<a href="'.$answer['answer_link'].'">'.$answer['answer_name'].'</a> ';
				}
				echo '<br>Дата последней загрузки: '. $value['lastModified'] .' <br>Дата последней оценки: '.  $value['lastGrade'] ;

				echo '</p>';
			} else {
				echo '<p><a href="'.$value['profile'].'">'.$key.'</a> не предоставил протокол для проверки ';
			}
		}
		echo '<br>';

	}
	/**
     * Выполняет парсинг страницы с ответами.
     *
     * @param $task_id идентификатор задания для парсинга
     */
	public function parse($task_id)
	{
		$dom = new DOMDocument();
		@$dom->loadHTML($this->answers_html);
		$xpath = new DOMXPath($dom);
		$this->links = array();
		$row_index = -1;
		$name = null;
		$task = null;

		$task_name = $xpath->query('//*[@id="region-main"]//h2/text()')->item(0)->nodeValue;
		$course_name = $xpath->query('//*[@class="page-header-headings"]/h1')->item(0)->nodeValue;

		$this->links[$course_name] = array();

		$this->links[$course_name][$task_id] = array();
		$pos = strripos($this->task_url, '/');
		$moodle_url = substr($this->task_url, 0, $pos + 1);
		while ($row_index < 200) {
			++$row_index;
			$task = $xpath->query('//*[@id="mod-poasassignment-submissions_r'.$row_index.'_c7"]/a')->item(0);
			if ($task !== null && ($task->nodeValue === 'Add grade' || $task->nodeValue === 'Добавить оценку' || preg_match('/Оценка устарела/', $task->parentNode->nodeValue) === 1 || preg_match('/Outdated/', $task->parentNode->nodeValue) === 1)) {
				$name = $xpath->query('//*[@id="mod-poasassignment-submissions_r'.$row_index.'_c1"]/a')->item(0);
				$student_name = $name->nodeValue;
				$this->links[$course_name][$task_id][$student_name] = array();
				$this->links[$course_name][$task_id][$student_name]['profile'] = $name->getAttribute('href'); // ссылка на его профиль
				$this->links[$course_name][$task_id][$student_name]['grade'] = $moodle_url.$task->getAttribute('href');
				$this->links[$course_name][$task_id][$student_name]['answers'] = array();
				$task_index = 0;
				while (true) {
					if ($xpath->query('.//*[@id="mod-poasassignment-submissions_r'.$row_index.'_c3"]//@href')->item($task_index) !== null) {
						$this->links[$course_name][$task_id][$student_name]['answers'][$task_index]['answer_link'] = $xpath->query('.//*[@id="mod-poasassignment-submissions_r'.$row_index.'_c3"]//@href')->item($task_index)->nodeValue; // ссылка на ответ
						$this->links[$course_name][$task_id][$student_name]['answers'][$task_index]['answer_name'] = $this->translit($xpath->query('.//*[@id="mod-poasassignment-submissions_r'.$row_index.'_c3"]//text()')->item($task_index * 2)->nodeValue, true); // наименование ответа
						++$task_index;
					} else {
						break;
					}
				}
			}
			$name = null;
			$task = null;
		}

		$students_count = count($this->links[$course_name][$task_id]);
		echo "<h3>Ответ на {$task_name}  ";
		echo " в курсе  {$course_name} ";
		echo " предоставили {$students_count} студентов:</h3>";
		foreach ($this->links[$course_name][$task_id] as $key => $value) {
			echo '<p><a href="'.$value['profile'].'">'.$key.'</a> предоставил для проверки ';

			foreach ($value['answers'] as $answer) {
				echo '<a href="'.$answer['answer_link'].'">'.$answer['answer_name'].'</a> ';
			}

			echo '</p>';
		}
		echo '<br>';
	}

	/**
     * Инициализирует данными из конфигурационного файла.
     */
	public function init()
	{
		$ini_array = parse_ini_file('conf.ini');
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
				Reporter::setMoodleUrl($ini_array['login_url']);
			}
			if ($ini_array['task_url'] !== null) {
				$this->task_url = $ini_array['task_url'];
			}

			if ($ini_array['task_id'] !== null) {
				$this->task_id = $ini_array['task_id'];
			}
			if ($ini_array['protocol_url'] !== null) {
				$this->protocol_url = $ini_array['protocol_url'];
			}

			if ($ini_array['protocol_id'] !== null) {
				$this->protocol_id = $ini_array['protocol_id'];
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
			if ($ini_array['generator_for_CMake'] !== null) {
				Tester::setGeneratorForCMake($ini_array['generator_for_CMake']);
			}
			if ($ini_array['main_builder'] !== null) {
				Tester::setMainBuilder($ini_array['main_builder']);
			}

			if ($ini_array['path_to_Make'] !== null) {
				Tester::setMakePath($ini_array['path_to_Make']);
			}
			if ($ini_array['linux_client'] !== null) {
				Tester::setLinuxClient($ini_array['linux_client']);
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
			if ($ini_array['send_from_email'] !== null) {
				Reporter::setFromEmail($ini_array['send_from_email']);
			}
			if ($ini_array['grade_if_fail'] !== null) {
				if($ini_array['grade_if_fail']<101 && $ini_array['grade_if_fail']>-1)
					$this->grade_if_fail = $ini_array['grade_if_fail'];
				else
					throw new Exception('Оценка в случае провала (grade_if_fail) должна быть задана в пределах от 0 до 100');
			}
			if ($ini_array['write_on_comment'] !== null) {
				$this->write_on_comment = $ini_array['write_on_comment'];
			}
			if ($ini_array['write_on'] !== null) {
				$this->write_on = $ini_array['write_on'];
				if(strnatcasecmp($this->write_on, "console") != 0 &&
				   strnatcasecmp($this->write_on, "log") != 0 && 
				   strnatcasecmp($this->write_on, "browser") != 0) {
					throw new Exception('Установлен некорректный способ вывода, необходимо указать "browser" или "log" или "console"');
				}
			}
			$misOptions = $this->checkOptions();
			if (!empty($misOptions)) {
				throw new Exception('Для работы необходимо установить следующие опции: '.implode(',', $misOptions));
			}
		} else {
			throw new Exception('Невозможно открыть файл conf.ini, или данный файл пустой');
		}
	}
	/**
     * Проверить, все ли необходимые параметры установлены.
     *
     * @return параметры, которые не были установлены
     */
	public function checkOptions()
	{
		$params = [];
		if ($this->username == null) {
			array_push($params, 'username');
		}

		if ($this->password == null) {
			array_push($params, 'password');
		}

		if ($this->login_url == null) {
			array_push($params, 'login_url');
		}

		if ($this->task_url == null && $this->protocol_url == null) {
			if($this->task_url == null)
				array_push($params, 'task_url');

			if($this->$this->protocol_url == null)
				array_push($params, 'protocol_url');
		}

		if ($this->send_result_on_email == true && $this->email == null) {
			array_push($params, 'email');
		}
		if ($this->send_result_on_email == true && Reporter::getFromEmail()== null) {
			array_push($params, 'send_from_email');
		}

		if ($this->unpack_answers == true && $this->path_to_winrar == null) {
			array_push($params, 'path_to_winrar');
		}

		if ($this->build_and_compile == true) {
			if (Tester::getCMakePath() == null) {
				array_push($params, 'path_to_CMake');
			}

			if (Tester::getGeneratorForCMake() == null) {
				array_push($params, 'generator_for_CMake');
			}

			if (Tester::getQMakePath() == null) {
				array_push($params, 'path_to_QMake');
			}

			if (Tester::getMakePath() == null) {
				array_push($params, 'path_to_Make');
			}
		}

		return $params;
	}

	/**
     * Распаковывает один файл.
     *
     * @param $file_path путь к файлу для распаковки
     * @param sting[] массив ошибок
     */
	public function unpackFile($file_path, &$errors)
	{
		$errors = array();
		$file_ext = strrchr($file_path, '.');
		if ($file_ext == '.rar' || $file_ext == '.zip' || $file_ext == '.tar' || $file_ext == '.gz' || $file_ext == '.bz2' || $file_ext == '.7z') {
			$name = strrchr($file_path, DIRECTORY_SEPARATOR);
			$path = substr($file_path, 0, strlen($file_path) - strlen($name) + 1);
			if ($this->linux_client) {
				switch ($file_ext){
					case '.rar':
						$comand = 'unrar x -o+ "'.$file_path.'" "'.$path.'"';
						break;

					case '.zip':
						$comand = 'unzip "'.$file_path.'" -d "'.$path.'"';
						break;

					case '.tar':
						$comand = 'tar xf "'.$file_path.'" "'.$path.'"';
						break;

					case '.gz':
						$comand = 'tar zxf "'.$file_path.'" -C "'.$path.'"';
						break;

					case '.bz2':
						$comand = 'tar jxf "'.$file_path.'" -C "'.$path.'"';
						break;

					case '.7z':
						$comand = '7z e "'.$file_path.'" -o"'.$path.'"';
						break;
				}		
			} else {
				$comand = '"'.$this->path_to_winrar.'" x -o+ "'.$file_path.'" "'.$path.'" 2> rarError.txt';
			}
			exec($comand, $error);
			$header = '[Fail] Ошибка при распаковке файла: ';
			Tester::readErrorOnFile('./rarError.txt', $header, $errors);
		}
	}

	/**
     * Скачивает выполненные работы.
     */
	public function testAnswers()
	{
		foreach ($this->links as $course_name => $course) {
			$or_course_name =  $course_name;
			foreach ($course as $task_name => $task) {
				$or_task_name =  $task_name;
				foreach ($task as $name => $student) {
					$or_name =  $name;
					if ($this->build_and_compile) {
						echo '<h3>Тестирование работы по задаче '.$task_name.' студента '.$name.':</h3>';
					}
					foreach ($student['answers'] as $answer) {
						$name =  $this->translit($name,true);
						$course_name =  $this->translit($course_name,true);
						$task_name =  $this->translit($task_name,true);

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
						$stderr = fopen('curl.log', 'a');
						curl_setopt($ch, CURLOPT_VERBOSE, true);
						curl_setopt($ch, CURLOPT_STDERR, $stderr);

						$result = curl_exec($ch);
						curl_close($ch);
						fclose($stderr);
						$dir = $this->files_download_to;

						if (!is_dir($dir)) {
							mkdir($dir);
						}

						if (!is_dir($dir.DIRECTORY_SEPARATOR.$course_name)) {
							mkdir($dir.DIRECTORY_SEPARATOR.$course_name);
						}

						if (!is_dir($dir.DIRECTORY_SEPARATOR.$course_name.DIRECTORY_SEPARATOR.$task_name)) {
							mkdir($dir.DIRECTORY_SEPARATOR.$course_name.DIRECTORY_SEPARATOR.$task_name);
						}

						if (!is_dir($dir.DIRECTORY_SEPARATOR.$course_name.DIRECTORY_SEPARATOR.$task_name.DIRECTORY_SEPARATOR.$name)) {
							mkdir($dir.DIRECTORY_SEPARATOR.$course_name.DIRECTORY_SEPARATOR.$task_name.DIRECTORY_SEPARATOR.$name);
						}
						$fp = fopen($dir.DIRECTORY_SEPARATOR.$course_name.DIRECTORY_SEPARATOR.$task_name.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.$output_filename, 'w');
						fwrite($fp, $result);
						fclose($fp);
						$errors = array();
						$file_path = $dir.DIRECTORY_SEPARATOR.$course_name.DIRECTORY_SEPARATOR.$task_name.DIRECTORY_SEPARATOR.$name;
						if ($this->unpack_answers) {
							$this->unpackFile($file_path.DIRECTORY_SEPARATOR.$output_filename, $errors);
						}

						$this->translitAllFiles($dir.DIRECTORY_SEPARATOR.$course_name.DIRECTORY_SEPARATOR.$task_name.DIRECTORY_SEPARATOR.$name);

					}

					if ($this->build_and_compile) {
						Tester::testOnPath($file_path, $errors);
						if(!empty($errors)) {
							$task = $this->links[$or_course_name][$or_task_name][$or_name]['grade'];
							if($this->grade_if_fail!=-1) {
								Reporter::gradeAnswer($task,$this->grade_if_fail,$this->cookie_file);
							}
							if ($this->write_on_comment) {
								Reporter::sendComment($errors, $task, $this->cookie_file);
							}
						}
					}
					echo '<br>';

					Cleaner::clearDir($file_path);
				}
			}
		}
	}
}
error_reporting(E_ALL & ~E_NOTICE);
ob_start();
$succes_test=true;
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

$my_html = ob_get_clean();
if (strnatcasecmp($mp->writeOn(), "log") == 0) {
	$logFile = Reporter::writeOnFile($my_html);
	echo 'Тестирование законченно, результат сохранен в .log файле';
} else if (strnatcasecmp($mp->writeOn(), "console") == 0) {
	echo strip_tags(Reporter::replaseTag($my_html));
}else{
	echo $my_html;
}
if ($mp->getSendResultOnEmail()) {
	if($logFile == null)
		$logFile = Reporter::writeOnFile($my_html);
	Reporter::sendMailWithFile($logFile, $mp->getEmail());
}