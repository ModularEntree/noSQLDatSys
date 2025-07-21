<?php

use exceptions\CypherFailedException;
use exceptions\CypherInvalidPathException;
use exceptions\DatabaseAccessException;
use exceptions\DatabaseDoesNotExistsException;
use exceptions\FileManipulateException;
use interfaces\ErrorHandle;

/**# Database
 * Třída starající se o vlastní databázový systém založený na šifrovaných binárních souborech.
 *
 * Každé spojení obsahuje:
 * - **id** - svůj jedinečný identifikátor
 * - **mód** - jakou kategorii akcí planuje v databázi provést
 * - **databázi** - jednoduché označení databáze, ke které je připojen/přidružen
 *
 * Spojení se první vytvoří a zapíše se do seznamu existujících spojení, aby nemohla vzniknout duplicita. Tím inicializace končí čeká na dotaz od uživatele skrze nový vzniklý objekt (spojení samotné).
 *
 * Při dotazu se podívá do aktivních spojení v již specifikované databázi a  zkontroluje, zda je vyžadovaný soubor využíván. Pokud ano, zapíše se do fronty a čeká, dokud spojení vyžadující kýžený soubor neskončí. Formát fronty je následující:
 * - id_spojení;mód_spojení;využívaný_soubor'\n'
 *
 * Když skončí, vykoná svůj dotaz (získat záznam/záznamy, zapsat záznam, upraví záznam, smaže záznam...) a smaže se z fronty databáze, aby uvolnil místo pro další spojení.
 *
 * Při přístupu k souboru se použije třída {@link Cyphering}, která se stará o šifrování. Databáze tak získá přístupná data, se kterými pracuje a po dokončení je vrátí zpět opět pomocí té samé třídy.
 *
 * Seznam existujících spojení je zapsán taktéž v CSV syntaxi se středníkem jako oddělovačem. Standard je vcelku jednoduchý:
 * - id_spojení;mód_spojení;databáze'\n'
 *
 * Pro vytvoření spojení je nutno tento soubor projít a zjistit, zda 6-místný číslový řetězec již neexistuje, v tom případě se jednoduše vygeneruje nový řetězec a opět proběhne kontrola.
 *
 * Samotné tabulky mají hlavičku na prvním řádků, ze které lze zjistit název sloupce, jeho datový typ a povinnost, obě odděleny od sebe čárkou, od dalších sloupců středníkem. Tedy:
 * - "název_sloupce","int","P";"název_sloupce2","string","N";etc.
 *
 * Přičemž "P" znamená povinný a "N" nepovinný.
 */
final class Database implements ErrorHandle {
    // Path konstanty
    /** @var string Root adresář všech databází relativní ku Database třídě */
    public const string datRoot = __ROOT__ . "database/";
    /** @var string Cesta k záloze databází před přešifrováním */
    public const string datBackupPath = __ROOT__ . "data/backup/databases/";

    /** @var string Relativní cesta k souboru se seznamem všech spojení */
    private const string datGlobalConnPath =  __ROOT__ . "database/connectionsList.bin";

    /** @var string Defaultní název souboru v databázové složce (exclusive), kde se ukládají právě aktivní spojení */
    private const string datConnFilenameDef = "activeConnectionList.bin";

    /** @var string Přípona souboru tabulky */
    private const string datTableExt = ".table";

    /** @var array|string[] Seznam všech existujících databází. */
    public const array listOfDatabases = array("main", "thehappiestday");

    // Systémové konstanty
    /** @var string Systémové ID, které oznamuje, že právě probíhá přešifrovávání tabulek */
    public const string systemReCypher = "000000";
    /** @var string Systémové značení všech tabulek */
    public const string systemAll = "ALL_TABLES";

    // Hlavičky souborů konstanty
    private const int GLOBALHEADCONNID = 0;
    private const int GLOBALHEADCONNMODE = 1;
    private const int GLOBALHEADCONNDATABASE = 2;
    private const int LOCALHEADCONNID = 0;
    private const int LOCALHEADCONNMODE = 1;
    private const int LOCALHEADCONNFILE = 2;

    // Konstanty způsobů zápisu
    public const string modeWrite = "w";
    public const string modeRead = "r";
    public const string modeWriteAndRead = "wr";

    // Atributy cesty spojení

    /** @var string Relativní cesta k adresáři využívané databáze */
    private string $datPath;

    /** @var string Relativní cesta k souboru aktivních spojení ve využívané databázi */
    private string $datConnFilePath;
    // Atributy spojení

    /** @var string Jedinečný dentifikátor spojení */
    private string $connectionID;

    /** @var string Mód spojení, se kterým bude pracovat */
    private string $connectionMode;

    /** @var string Databáze, ke které je spojení vázáno */
    private string $connectionDatabase;

    /** @var string Atributy spojení ve formátu CSV pro zápis/čtení z seznamu všech spojení */
    private string $connectionCSVFormat;


    /** Vyvoří databázové spojení a zapíše své {@link $connectionID} do seznamu spojení.
     * @param string $database Název databáze, ke které se připojuju. Musí existovat ve složce Database, pokud ne, tak se vyhodí {@link DatabaseDoesNotExistsException}
     * @param string|null $mode Zjistí, co uživatel plánuje za operaci. Pokud není vybrána ani jedna z možností či není zadán žádný mód, operace se automaticky nastaví na čtení. Možnosti jsou:
     * - "w" - plánuje operaci zahrnující zápis
     * - "r" - plánuje operaci zarhnující čtení
     * - "wr" - plánuje operaci zarhnující čtení i zápis
     * @throws DatabaseDoesNotExistsException
     * @throws DatabaseAccessException
     */
    public function __construct(string $database, ?string $mode = null) {
        $this->setHandling();

        $this->checkIfDatabaseExists($database);
        $this->setConnectionMode($mode);
        $this->setSelfToGlobalConnections();
    }

    /** Při destrukci spojení se sám odstraní ze seznamu aktivních spojení. Pokud nebude moct přistoupit k seznamu aktivních spojení, vyhodí {@link DatabaseAccessException}.
     * @throws DatabaseAccessException
     */
    public function __destruct() {
        $fileContent = file_get_contents(self::datGlobalConnPath);

        if (!$fileContent) throw new DatabaseAccessException();

        $toWrite = str_replace($this->connectionCSVFormat,"", $fileContent);

        if(!file_put_contents(self::datGlobalConnPath, $toWrite)) throw new DatabaseAccessException();
    }

    /** Vygeneruje unikátní identifikátor spojení a nastaví si ho jako atribut. Následně se zapíše do seznamu spojení, aby nedošlo k duplicitě spojení. Taktéž nastaví svůj atribut {@link $connectionCSVFormat}. Pokud nebude moct přistoupit k seznamu všech spojení, vyhodí {@link DatabaseAccessException}.
     * @throws DatabaseAccessException
     */
    private function setSelfToGlobalConnections() : void {
        FileHandling::createIfDoesNotExists(self::datGlobalConnPath);

        do {
            $id = mt_rand(1, 999999);
        }
        while($this->checkIfConnectionExists($id));

        $this->connectionID = $id;

        $toWrite = $this->connectionID . ";" . $this->connectionMode . ";" . $this->connectionDatabase . '\n';

        $this->connectionCSVFormat = $toWrite;

        $file = fopen(self::datGlobalConnPath, "ab");

        if (!$file) throw new DatabaseAccessException();

        if (!fwrite($file, $toWrite)) throw new FileManipulateException("Nedokázal sebe zapsat do souboru");

        fclose($file);
    }

    /** Zkontroluje, zda vygenerovaný identifikátor ke spojení neexistuje již v seznamu všech aktivních spojení. Pokud nebude moct přistoupit k souboru všech spojení, vyhodí {@link DatabaseAccessException}.
     * @param string $id Vygenerovaný identifikátor ke kontrole.
     * @return bool Pokud identifikátor spojení existuje, vrací true, pokud ne, false.
     * @throws DatabaseAccessException
     */
    private function checkIfConnectionExists(string $id) : bool {
        $file = fopen(self::datGlobalConnPath, "rb");

        if (!$file) throw new DatabaseAccessException();

        while (!($line = fgets($file))) {
            $connValues = explode( ";", $line);
            if ($connValues[self::GLOBALHEADCONNID] == $id) {
                fclose($file);
                return true;
            }
        }

        fclose($file);

        return false;
    }

    /** Zkontroluje, zda zadaná databáze existuje v již vytvořených databázích. Pokud ne, vyhodí {@link DatabaseDoesNotExistsException DatabaseDoesNotExistsException}. Pokud existuje, zapíše do {@link $datPath} cestu k adresáři databáze a nastaví cestu k souboru se spojeními.
     * @param string $database Název databáze, který se kontroluje.
     * @throws DatabaseDoesNotExistsException
     */
    private function checkIfDatabaseExists(string $database): void {
        $databasePath = self::datRoot.$database;

        if (!file_exists($databasePath)) {
            throw new DatabaseDoesNotExistsException();
        }

        $this->datPath = $databasePath . "/";
        $this->datConnFilePath = $this->datPath."/".self::datConnFilenameDef;
        $this->connectionDatabase = $database;
    }

    /** Nastaví mód zadaný uživatelem na jeden z existujících možných módů. Pokud je mód null, prázdný či neodpovídá možnostem, automaticky se nastaví na čtení (r).
     * @param string|null $mode Mód zadaný uživatelem.
     */
    private function setConnectionMode(?string $mode) : void {
        switch ($mode) {
            case self::modeWrite: {
                $this->connectionMode = self::modeWrite;
                break;
            }
            case self::modeWriteAndRead: {
                $this->connectionMode = self::modeWriteAndRead;
                break;
            }
            default: {
                $this->connectionMode = self::modeRead;
            }
        }
    }

    /**
     * @param string $table
     * @param array $params
     * @return array
     * @throws DatabaseAccessException
     * @throws CypherInvalidPathException
     * @throws CypherFailedException
     */
    public function selectQuery(string $table, array $params) : array {
        $this->writeYourselfToQuery($table);

        while($this->checkIfUsed($table)) {
            usleep(1000);
        }

        $data = (new Cyphering($this->datPath . $table . self::datTableExt))->decypher();

        // TODO: Dodělat select
    }
    private function checkIfUsed(string $table) : bool {
        FileHandling::createIfDoesNotExists($this->datConnFilePath);

        $file = file_get_contents($this->datConnFilePath);

        $records = explode('\n', $file);

        if (count($records) != null) {
            if (explode(";", $records[0])[self::LOCALHEADCONNID] == self::systemReCypher) {
                Cyphering::reCypher();
                return true;
            }

            foreach ($records as $record) {
                $recordValues = explode(";", $record);
                if ($recordValues[self::LOCALHEADCONNID] == $this->connectionID) return false;
                if ($recordValues[self::LOCALHEADCONNFILE] === $table) return true;
            }
        }

        return false;
    }
    private function writeYourselfToQuery(string $table) : void {
        FileHandling::createIfDoesNotExists($this->datConnFilePath);

        $file = fopen($this->datConnFilePath, "ab");

        if (!$file) throw new DatabaseAccessException();

        $toWrite = $this->connectionID . ";" . $this->connectionMode . ";" . $table . '\n';

        if (!fwrite($file, $toWrite)) throw new DatabaseAccessException();

        fclose($file);
    }
    public static function announceReCyphering() : void {
        // TODO: Tady by mohly vzniknout problémy kvůli zapouzdření?

        foreach (self::listOfDatabases as $databaseName) {
            $fileQueryOfConnections = self::datRoot . $databaseName . "/" . self::datConnFilenameDef;

            FileHandling::createIfDoesNotExists($fileQueryOfConnections);

            $fileContent = file_get_contents($fileQueryOfConnections);

            if (!$fileContent) throw new DatabaseAccessException();

            $toWrite = self::systemReCypher . ";" . self::modeWriteAndRead . ";" . self::systemAll . '\n';

            if (!file_put_contents($fileQueryOfConnections, $toWrite . $fileContent)) throw new DatabaseAccessException();
        }
    }


    public function errorHandle(int $errNo, string $errStr, string $errFile, int $errLine): bool {
        // TODO: Implement errorHandle() method.
    }

    public function exceptionHandle(Throwable $exception): void
    {
        // TODO: Implement exceptionHandle() method.
    }

    public function setHandling(): void {
        set_error_handler("errorHandle");
        set_exception_handler("exceptionHandle");
    }

    public function __toString(): string
    {
        // TODO: Implement __toString() method.
    }
}