<?php
include_once  'Cleaner.php';
/**
 * Class Tester класс выполняющий сборку и компиляцию работ
 */
class Tester
{
    /**
     * @var string путь к cMake
     */
    private static $path_to_CMake = '';

    /**
     * @var string путь к qMake
     */
    private static $path_to_QMake = '';

    /**
     * @var string путь к Make
     */
    private static $path_to_Make = '';

    /**
     * @var string запуск на linux системах
     */
    private static $linux_client = false;

    /**
     * @var string генератор для cmake
     */
    private static $generator_for_CMake = "MinGW Makefiles";

    /**
     * @var string установить программу, по которой будет показывать ошибки
     */
    private static $main_builder = "any";


    /**
      * Установить программу, по которой будет показывать ошибки
     * @param string название программы
     */
    public static function setMainBuilder($str)
    {
        Tester::$main_builder=$str;
    }

    /**
     * @param string путь к CMake
     */
    public static function getCMakePath()
    {
        return Tester::$path_to_CMake;
    }

    /**
     * @param название генератора
     */
    public static function getGeneratorForCMake()
    {
        return Tester::$generator_for_CMake;
    }


    /**
     * @param string путь к QMake
     */
    public static function getQMakePath()
    {
        return Tester::$path_to_QMake;
    }

    /**
     * @param string путь к Make
     */
    public static function getMakePath()
    {
        return Tester::$path_to_Make;
    }


    /**
     * Устанавливает значение для пути к CMake
     * @param string путь к CMake
     */
    public static function setCMakePath($path)
    {
        Tester::$path_to_CMake = $path;
    }

    /**
     * Устанавливает генератор для cmake
     * @param название генератора
     */
    public static function setGeneratorForCMake($generator)
    {
        Tester::$generator_for_CMake =$generator;
    }


    /**
     * Устанавливает значение для пути к QMake
     * @param string путь к QMake
     */
    public static function setQMakePath($path)
    {
        Tester::$path_to_QMake = $path;
    }

    /**
     * Устанавливает значение для пути к Make
     * @param string путь к Make
     */
    public static function setMakePath($path)
    {
        Tester::$path_to_Make = $path;
    }

    /**
     * Устанавливает значение для пути к Make
     * @param bool true - тестирование происходит на linux, false - не на linux
     */
    public static function setLinuxClient($isLinux)
    {
        Tester::$linux_client = $isLinux;
    }
    /**
     * Создать .pro файл
     * @param string путь к файлам проекта
     */
    public static function makeProFile($path)
    {
        $fileName =  __DIR__ . '/' . $path . '\\buildTemp\\project.pro';
        if (Tester::$linux_client) {
            $comand = "qmake";
        } else {
            $comand = '"' .  Tester::$path_to_QMake . '\\qmake.exe"';
        }
        $comand .= ' -project -nopwd "' . __DIR__ . '/' . $path . '" -o "'. $fileName .'"  2> qmakeError.txt';
        exec($comand, $error);

        $fp = fopen($fileName, "a");
        $text = "QT += core gui \r\ngreaterThan(QT_MAJOR_VERSION, 4): QT += widgets\r\n";
        $text .= "CONFIG += warn_off";
        fwrite($fp, $text);
        fclose($fp);
    }


    /**
     * Производит построение проекта с помощью cmake
     * @param string путь к файлу
     * @param errors массив ошибок
     * @return 0 - собран проект, 1 - проект не собран
     */
    public static function buildOnCmake($path, &$errors)
    {
        // Очищаем рабочую папку
        if (is_dir($path . '/buildTemp')) {
            Cleaner::removeDirectory($path . '/buildTemp');
        }
        mkdir($path . '/buildTemp');
        // Формируем cmake лист
        $cmake_path = Tester::createCmakelist($path);
        if ($cmake_path != null) {
            // Составляем команду
            if (Tester::$linux_client) {
                $comand = 'cmake  -G "' . Tester::$generator_for_CMake;
            } else {
                $comand = '"' .  Tester::$path_to_CMake . '\\cmake.exe" -G "' . Tester::$generator_for_CMake;
            }
            $comand .= '" -B"' . $path . '/buildTemp" -H"' . $path . '/buildTemp"' . ' 2> cmakeError.txt';
            exec($comand, $error);
            $result = 1;

            // Выводим ошибки
            $header = "[Fail] Ошибка во время построения на cmake: ";
            Tester::readErrorOnFile("./cmakeError.txt", $header, $errors);
            return 0;
        } else {
            array_push($errors, "\r\n[Fail] Ошибка во время построения на cmake: \r\nНеудалось создать CMakeList файл");
        }
        return 1;
    }

    /**
     * Производит построение проекта с помощью qt
     * @param string путь к файлу
     * @param errors массив ошибок
     * @return 0 - собран проект, 1 - проект не собран
     */
    public static function buildOnQMake($path, &$errors)
    {
        // Очищаем рабочую папку
        if (is_dir($path . '/buildTemp')) {
            Cleaner::removeDirectory($path . '/buildTemp');
        }
        mkdir($path . '/buildTemp');
        // Создаем .pro файл.
        Tester::makeProFile($path);
        // Получаем про файл
        $proFile = Tester::recursiveGlob($path . '/buildTemp', '*.pro');
        if (!empty($proFile)) {
            if (Tester::$linux_client) {
                $comand = "qmake";
            } else {
                $comand = '"' .  Tester::$path_to_QMake . '\\qmake.exe"';
            }
            $comand .= ' "' . __DIR__ . '/' . dirname($proFile[0]) . '" 2> qmakeError.txt';
            exec($comand, $error);

            return 0;
            // Выводим ошибки
            $header = "\r\n[Fail] Ошибка во время построения на qmake: ";
            Tester::readErrorOnFile("./qmakeError.txt", $header, $errors);
        } else {
            array_push($errors, "\r\n[Fail] Ошибка во время построения на qmake: \r\nНеудалось создать .pro файл");
        }
        return 1;
    }
    /**
     * Производит построение и компилирование проекта
     * @param string путь к файлу
     * @param errors массив ошибок
     */
    public static function buildAndCompilProject($path, &$errors)
    {
        $qmakeEr=[];
        $cmakeEr=[];
        // Если основным сборщиком назван qmake
        if (strnatcasecmp(Tester::$main_builder, "qmake") == 0  || strnatcasecmp(Tester::$main_builder, "any") == 0) {
            if (!Tester::buildOnQMake($path, $qmakeEr)) {
                Tester::compileProject($path, 0, $qmakeEr);
            }
        }
        // Если основным сборщиком назван cmake
        if (strnatcasecmp(Tester::$main_builder, "cmake") == 0 || (strnatcasecmp(Tester::$main_builder, "any") == 0
                                                                 && count($qmakeEr)>1)) {
            if (!Tester::buildOnCmake($path, $cmakeEr)) {
                Tester::compileProject($path, 1, $cmakeEr);
            }
        }
        // Проверка ошибок
        if (strnatcasecmp(Tester::$main_builder, "qmake") == 0) {
            $errors=array_merge($errors, $qmakeEr);
        } elseif (strnatcasecmp(Tester::$main_builder, "cmake") == 0) {
            $errors=array_merge($errors, $cmakeEr);
        } elseif (strnatcasecmp(Tester::$main_builder, "any") == 0) {
            if (count($cmakeEr)>1) {
                $errors= array_merge($errors, $qmakeEr, $cmakeEr);
            }
        } else {
            throw new Exception('Установлен некорректный основной сборщик, необходимо указать "qmake" или "cmake" или "any"');
        }
    }
    /**
     * Производит компиляцию проекта используя MakeFile
     * @param string путь к файлу
     * @param int  0 - проект qt, 1 - проект без qt
     * @param string[] массив ошибок
     */
    public static function compileProject($path, $qt, &$errors)
    {
        // Составляем команду для компиляции
        if (Tester::$linux_client) {
            $comand = "make";
        } else {
            $comand = '"' .  Tester::$path_to_Make . '\mingw32-make.exe"';
        }
        // Если файл не qt указываем откуда брать файлы
        if ($qt == 1) {
            $comand .= ' --directory="' . $path . '/buildTemp"';
        }

        $comand .= ' > makeLog.txt 2> makeError.txt';
        exec($comand, $error);
        // Вывести сообщение об ошибке
        $header = "[Fail] Ошибка во время компиляции ";
        if (!$qt) {
            $header .= "при сборке qt: ";
        } else {
            $header .= "при сборке cmake: ";
        }
        Tester::readErrorOnFile("./makeError.txt", $header, $errors);
    }


    /**
     * Создаёт файл для построенния проекта
     * @param string путь к файлам проекта
     */
    private static function createCmakelist($path)
    {
        $cmake_path   = $path . "/buildTemp/CMakeLists.txt";

        $source_files = array_merge(Tester::recursiveGlob($path, '*.cpp'), Tester::recursiveGlob($path, '*.c'));
        if (!empty($source_files)) {
            $header_files = Tester::recursiveGlob($path, '*.h');
            // Создание файла
            $fp           = fopen($cmake_path, 'w');
            // Наполнение файла
            $body         = "";
            // Установление минимальной версии cmake
            $body .= "cmake_minimum_required(VERSION 2.8)\r\n";
            // Установление файлов с кодом
            $body .= "set(SOURCE";
            foreach ($source_files as $sfile) {
                $body .= ' "';
                $res = substr($sfile, strlen($path) + 1);
                $res = str_replace('\\', '/', $res);
                $body .= '../' . $res . '"';
            }
            $body .= ")\r\n";

            if (!empty($header_files)) {
                // Заголовочные файлы
                $body .= "set(HEADER";
                foreach ($header_files as $hfile) {
                    $body .= ' "';
                    $res = substr($hfile, strlen($path) + 1);
                    $res = str_replace('\\', '/', $res);
                    $body .= '../' . $res. '"';
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
     * Рекурсивный проход по каталогу
     * @param string - путь к папке, в которой должен осуществлятся поиск
     * @param string - маска, по которой осуществляется поиск
     * @return array - полный список найденных файлов
     */
    public static function recursiveGlob($startDir, $fileMask)
    {
        $found = glob($startDir . DIRECTORY_SEPARATOR . $fileMask, GLOB_BRACE);
        $dirs  = glob($startDir . DIRECTORY_SEPARATOR . "*", GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $found = array_merge($found, Tester::recursiveGlob($dir, $fileMask));
        }
        return $found;
    }

    /**
     * Позволяет выводить на экран сообщения об ошибке из файла
     * @param string путь к файлу
     * @param string заголовок ошибки
     * @param string массив ошибок
     */
    public static function readErrorOnFile($path, $header_string, &$errors)
    {
        $lines = file($path);
        if (!empty($lines)) {
            array_push($errors, $header_string);
        }
        foreach ($lines as $line) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $line = iconv('WINDOWS-1251', 'UTF-8', $line);
            }
            array_push($errors, $line);
        }
        foreach ($errors as $key => $error) {
            if (stripos($error, 'RCC: Warning:')!==false) {
                unset($errors[$key]);
            }
        }
        if (count($errors)==1) {
            unset($errors[0]);
        }
    }

    /**
     * Тестирует работу
     * @param string путь к работе
     * @param string[] массив ошибок
     */
    public static function testOnPath($file_path, &$errors)
    {
        $source_files = array_merge(Tester::recursiveGlob($file_path, '*.cpp'), Tester::recursiveGlob($file_path, '*.c'));
        if (!empty($source_files)) {
            Tester::buildAndCompilProject($file_path, $errors);
            if (empty($errors)) {
                echo "[Pass] Тестирование прошло успешно";
            }
        } else {
            echo "[Fail] Файлы с кодом расширения .c или .cpp не были найдены";
        }

        foreach ($errors as $error) {
            echo($error . "<br>");
        }
    }
}
