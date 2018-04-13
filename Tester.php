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
     * Устанавливает значение для пути к CMake
     * @param string путь к CMake
     */
    public static function setCMakePath($path)
    {
        Tester::$path_to_CMake = $path;
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
     * Производит построение проекта используя CMakeLists файл.
     * @param string путь к файлу
     * @param errors массив ошибок
     * @return 0 - собран qt проект, 1 - собран проект без qt, 2 - проект не был собран
     */
    public static function buildProject($path, &$errors)
    {
        $result = 2;
        //Удаляем директорию
        if (is_dir($path . '/build')) {
            Cleaner::removeDirectory($path . '/build');
        }
        mkdir($path . '/build');
        $qtfiles = Tester::recursiveGlob($path, '*.pro');
        // Если это не qt проект
        if (empty($qtfiles)) {
            // Формируем cmake лист
            $cmake_path = Tester::createCmakelist($path);
            if ($cmake_path != null) {
                // Составляем команду
                if (Tester::$linux_client) {
                    $comand = 'cmake  -G "Unix Makefiles"';
                } else {
                    $comand = '"' .  Tester::$path_to_CMake . '\\cmake.exe" -G "MinGW Makefiles"';
                }
                $comand .= ' -B"' . $path . '/build" -H"' . $path . '/build"' . ' 2> cmakeError.txt';
                exec($comand, $error);
                $result = 1;

                // Выводим ошибки
                $header = "Error during build on cmake: ";
                Tester::readErrorOnFile("./cmakeError.txt", $header, $errors);
            }
        } else {
            // Составляем команду
            foreach ($qtfiles as $qfile) {
                if (Tester::$linux_client) {
                    $comand = "qmake";
                } else {
                    $comand = '"' .  Tester::$path_to_QMake . '\\qmake.exe"';
                }
                $comand .= ' "' . __DIR__ . '/' . dirname($qfile) . '" 2> qmakeError.txt';
                exec($comand, $error);
            }
            $result = 0;
            // Выводим ошибки
            $header = "Error during build on qmake: ";
            Tester::readErrorOnFile("./qmakeError.txt", $header, $errors);
        }


        return $result;
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
            $comand = '"' .  Tester::$path_to_Make . '\\make.exe"';
        }
        // Если файл не qt указываем откуда брать файлы
        if ($qt == 1) {
            $comand .= ' --directory="' . $path . '/build"';
        }

        $comand .= ' > makeLog.txt 2> makeError.txt';
        exec($comand, $error);
        // Вывести сообщение об ошибке
        $header = "Error during compilation with make: ";
        Tester::readErrorOnFile("./makeError.txt", $header, $errors);
    }

    /**
     * Создаёт файл для построенния проекта
     * @param string путь к файлам проекта
     */
    private static function createCmakelist($path)
    {
        $cmake_path   = $path . "/build/CMakeLists.txt";
        $source_files = Tester::recursiveGlob($path, '*.cpp');
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
     * Рекурсивный проход по каталогу
     * @param string - путь к папке, в которой должен осуществлятся поиск
     * @param string - маска, по которой осуществляется поиск
     * @return array - полный список найденных файлов
     */
    private static function recursiveGlob($startDir, $fileMask)
    {
        $found = glob($startDir . DIRECTORY_SEPARATOR . $fileMask);
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
    }

    /**
     * Тестирует работу
     * @param string путь к работе
     * @param string[] массив ошибок
     */
    public static function testOnPath($file_path, &$errors)
    {
        $result = Tester::buildProject($file_path, $errors);
        if ($result != 2) {
            Tester::compileProject($file_path, $result, $errors);
        }
        if (empty($errors)) {
            echo "Testing success";
        }

        foreach ($errors as $error) {
            echo($error . "<br>");
        }
    }
}
