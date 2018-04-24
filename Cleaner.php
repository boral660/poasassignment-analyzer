<?php
/**
 * Class Cleaner класс выполняющий очистку рабочего простраства от временных файлов
 */
class Cleaner
{
	
    /**
     * Очистить от ненужных файлов папку со скриптом
     * @param string - маска, по которой осуществляется поиск
     */
    public static function clearDir($path)
    {
      //  Cleaner::removeDirectory($path . '/build');
        Cleaner::removeDirectory('./debug');
        Cleaner::removeDirectory('./release');
        Cleaner::removeFile('./cmakeError.txt');
        Cleaner::removeFile('./makeError.txt');
        Cleaner::removeFile('./makeLog.txt');
        Cleaner::removeFile('./rarError.txt');
        Cleaner::removeFile('./qmakeError.txt');
        Cleaner::removeFile('./Makefile');
        Cleaner::removeFile('./Makefile.Debug');
        Cleaner::removeFile('./Makefile.Release');
        Cleaner::removeFileOnMask("./",'*.cpp');
        Cleaner::removeFileOnMask("./",'*.c');
        Cleaner::removeFileOnMask("./",'*.h');
    }
	
	    /**
     * Удаление папки со всем содержимым
     * @param $dir путь к папке
     */
    public static function removeDirectory($dir)
    {
        if (is_dir($dir)) {
            if ($objs = glob($dir . "/*")) {
                foreach ($objs as $obj) {
                    is_dir($obj) ? Cleaner::removeDirectory($obj) : Cleaner::removeFile($obj);
                }
            }
            if(!rmdir($dir))
                 throw new Exception('Невозможно удалить папку: ' . $dir);
        }
    }
	
    /**
     * Удаление файла
     * @param $file путь к файлу
     */
    public static function removeFile($file)
    {
        if (file_exists($file)) {
            if(!unlink($file))
              throw new Exception('Невозможно удалить файл: ' . $file);
        }
    }

    /**
     * Удаление файлов по расширению
     * @param $file путь к файлу
     * @param $mask расширение файлов
     */
    public static function removeFileOnMask($dir,$mask)
    {
        $found = glob($dir . $mask);
        foreach ($found as $file)
            Cleaner::removeFile($file);
        
    }
}