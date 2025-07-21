<?php

use exceptions\CypherFailedException;
use exceptions\CypherInvalidPathException;
use exceptions\DatabaseAccessException;
use exceptions\FileManipulateException;
use interfaces\ErrorHandle;

final class Cyphering implements ErrorHandle {
    private const int encryptKeyLength = 512;
    private const string encryptAlgorithm = "aes-256-cbc";
    private const int encryptKeyRegenerateDelay = 30;
    private static string $encryptKey = "V+bribEjRqvy4ckRBYgQJ93cphxw0DyF3D9ILzav/sPJcr0LVmXACRxx6N7rbu+YVEnwgDD6BrScXJtYIssp7w2Qw4QY0P+NCqN9Ugyavu7T4dHBlNax/NCX4qjrz6uYn9RJSfDIbMIDA/ucxz8OgTEmAh33tMzdYrItqUuRDgyeM5Blk2IY0qcHHddAW0esvNhnDtCgOynScEqyEklY7jx6dbavR1JnBZnmq9/5J4e6A0bl6Paf1vghDoIc9xqvyomJTEs7vyh1XGZR9n/+L/W5DRgvTrJaGLqODrSOfgJGEBwleJoLWm1Fx3JCw6wDkKsVj/5GG/QD0R1G1bF/eHLjcInhTNq0sn3971M8KXw/ZIgaChfbLreM4eX1wabJY5DI8j0mGawX7EeZRSPTKeLze/6qMcJUZvbklVr0tuyo7GyBz12lDOp8+3f2HZihOT7GcM6H6QuWwXIJG98rDqI4ZuAowFjcB28KC4ft9JPvAROvjrvWbX8oy6wG5IHwf0h0RTRmo8nUh2J54iFx7XvpFzt//d3JRwruGRdWYiwm1zqptnWd1MwvnMYYplsMi1DbYNPJWBjyNGOwNcG6fwrlUoHL6NwCVoZqj9K7tepCXgYErJwGJ4f32RZ0dP0UAxnDc+FKyowWYlb80cQxOT8ryUYoMb41NtFQq5cVvio=";
    private static string $encryptKeyLastChange = "31-03-2025";
    private static bool $reCypheringWaitingToBeStarted = false;
    private static bool $reCypheringInProgress = false;
    private string $path;
    public function __construct(string $pathToFile) {
        $this->setHandling();

        if (!is_file($pathToFile)) throw new CypherInvalidPathException("Cesta k souboru není validní.");

        $this->path = $pathToFile;
    }
    public function decypher() : string {
        $file = file_get_contents($this->path);

        if (!$file) throw new DatabaseAccessException();

        if (!self::$reCypheringInProgress) self::checkValidnessOfKey();

        $key = self::getKey();
        $alg = self::encryptAlgorithm;

        $decryptedData = openssl_decrypt($file, $alg, $key);

        if (!$decryptedData) throw new CypherFailedException();

        return $decryptedData;
    }
    public function cypher(string $data) : void {
        $key = self::getKey();
        $alg = self::encryptAlgorithm;

        $encryptedData = openssl_encrypt($data, $alg, $key);

        if (!$encryptedData) throw new CypherFailedException();

        if (!file_put_contents($this->path, $encryptedData)) throw new DatabaseAccessException();
    }
    private function cypherWithDifferentKey(string $data, string $keyNew) : void {
        $key = $keyNew;
        $alg = self::encryptAlgorithm;

        $encryptedData = openssl_encrypt($data, $alg, $key);

        if (!$encryptedData) throw new CypherFailedException();

        if (!file_put_contents($this->path, $encryptedData)) throw new DatabaseAccessException();
    }
    private static function generateKey(int $length) : string {
        $isStrong = false;

        do {
            $key = openssl_random_pseudo_bytes($length, $isStrong);
            if (!$key) $isStrong = false;
        } while (!$isStrong);

        return $key;
    }
    private static function checkValidnessOfKey() : void {
        if (abs(date_diff(date_create(self::$encryptKeyLastChange), date_create(date("Y-m-d")))->format("%a")) < self::encryptKeyRegenerateDelay) {
            return;
        }

        self::$reCypheringWaitingToBeStarted = true;

        Database::announceReCyphering();
    }

    /** Získá klíč. Ještě před tím ho však dekóduje z Base64 formátu. Pokud nastane chyba, vyhodí {@link ParseError}.
     * @return string Dekódovaný klíč určený k šifrování/dešifrování.
     * @throws ParseError
     */
    private static function getKey() : string {
        $decoded = base64_decode(self::$encryptKey);

        if (!$decoded) throw new ParseError();

        return $decoded;
    }

    /** Nastaví klíč, přičemž ho ještě před tím převede na Base64 formát. Pokud nastane chyba při převodu, vyhodí {@link ParseError}.
     * @param string $key Klíč k nastavení. Měl by být vygenerovaný funkcí {@link self::generateKey generateKey()}.
     * @return void
     * @throws ParseError
     */
    private static function setKey(string $key) : void {
        $encoded = base64_encode($key);

        if (!$encoded) throw new ParseError();

        self::$encryptKey = $encoded;
    }

    /**
     * @throws CypherInvalidPathException
     * @throws CypherFailedException
     * @throws FileManipulateException
     * @throws DatabaseAccessException
     */
    public static function reCypher() : void {
        if (!self::$reCypheringWaitingToBeStarted) return;
        self::$reCypheringWaitingToBeStarted = false;

        self::$reCypheringInProgress = true;

        $newKey = self::generateKey(self::encryptKeyLength);

        try {
            FileHandling::archiveFolder(Database::datRoot, Database::datBackupPath);
        } catch (FileManipulateException $e) {
            ErrorHandling::notifyException($e);
            self::$reCypheringInProgress = false;
            die();
        }

        foreach (Database::listOfDatabases as $databaseName) {
            if (!($filesAll = scandir(__ROOT__ . "database/" . $databaseName))) {
                throw new FileManipulateException("Scandir selhal.");
            }

            $files = array_diff($filesAll, array(".", ".."));

            foreach ($files as $file) {
                $path = __ROOT__ . "database/" . $databaseName . "/" . $file;

                $decyphered = (new Cyphering($path))->decypher();

                (new Cyphering($path))->cypherWithDifferentKey($decyphered, $newKey);
            }
        }

        self::$reCypheringInProgress = false;
        self::$encryptKeyLastChange = date("Y-m-d");
        self::setKey($newKey);
    }

    public function errorHandle(int $errNo, string $errStr, string $errFile, int $errLine): bool {
        if (!(error_reporting() & $errno)) return false;

        switch ($errNo) {
            case E_PARSE: {
                ErrorHandling::notifyError($errNo, $errStr, $errFile, $errLine);
                break;
            }
            default: {
                ErrorHandling::logError($errNo, $errStr, $errFile, $errLine);
                return false;
            }
        }

        return true;
    }
    public function exceptionHandle(Throwable $exception): void {
        if ($exception instanceof DatabaseAccessException) {
            ErrorHandling::notifyException($exception);
            trigger_error(ErrorHandling::formatExceptionMessage($exception), E_ERROR);
        }
        else {
            ErrorHandling::logException($exception);
            trigger_error(ErrorHandling::formatExceptionMessage($exception, false), E_ERROR);
        }
    }
    public function setHandling(): void {
        set_error_handler("errorHandle");
        set_exception_handler("exceptionHandle");
    }
}