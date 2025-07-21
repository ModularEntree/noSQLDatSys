<?php

use exceptions\DatabaseAccessException;
use exceptions\FileManipulateException;

final class FileHandling {
    public static function createIfDoesNotExists(string $fileToCreate) : void {
        if (!file_exists($fileToCreate)) {
            $file = fopen($fileToCreate, "cb");
            if (!$file) throw new FileManipulateException("Nepodařilo se vytvořit soubor.");
            fclose($file);
        }
    }
    public static function archiveFolder(string $folderToSave, string $whereToSave) : void {
        if (!file_exists($whereToSave) || !file_exists($folderToSave)) {
            throw new FileManipulateException("Zadaná složka neexistuje.");
        }

        $archiveFilename = "database_backup_".date("Ymd_His").".tar";

        $phar = new PharData($whereToSave . $archiveFilename);

        if (!$phar->buildFromDirectory($folderToSave)) throw new FileManipulateException("Nepodařilo se vytvořir archiv.");
    }
}