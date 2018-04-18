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
                    is_dir($obj) ? Cleaner::removeDirectory($obj) : unlink($obj);
                }
            }
            rmdir($dir);
        }
    }
	
    /**
     * Удаление файла
     * @param $file путь к файлу
     */
    public static function removeFile($file)
    {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}