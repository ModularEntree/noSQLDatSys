<?php

final class ErrorHandling {
    private const string logPath = __ROOT__ . "data/log/";
    public static function notifyError(int $errNo, string $errStr, string $errFile, int $errLine) {
        // TODO: implementovat mailto?
    }
    public static function notifyException(Throwable $exception) : void {
        // TODO: implementovat mailto?
    }
    public static function logException(Throwable $exception) : void {
        // TODO: implementovat logging výjimek
    }
    public static function logError(int $errNo, string $errStr, string $errFile, int $errLine) : void {
        // TODO: implementovat logging errorů
    }
    public static function logString(string $message) : void {
        // TODO: implementovat logging obecných zpráv
    }
    public static function formatExceptionMessage(Throwable $exception, ?bool $notified = true) : string {
        if ($notified) {
            return "Byla zachycena výjimka " . $exception::class . ", pokus o kontaktování správce proběhl.";
        }
        return "Výjimka, který nebyla zachycena a rozpoznána. Název: ". $exception::class . ", Zpráva: " . $exception->getMessage();
    }
}