<?php

namespace interfaces;

use Throwable;

/** Jednoduše rozhraní, které se stará o implementaci všech funkcí, které se starají o chyby a výjimky. Obvykle by měli buď nastavit nějakou defaultní hodnotu, aby nedošlo k pádu, anebo mi napsat mail o chybě.
 */
interface ErrorHandle {
    /** Stará se o řešení PHP Compile chyb. Neptejte se mě, prostě to nějak funguje.
     * @param $errNo int Číslo chyby, je to kdyžtak konstanta (E_CHYBANÁZEV))
     * @param $errStr string Hláška chyby, kterou uživatel zadatal, nebo nějaká defaultní.
     * @param $errFile string Název souboru, ve kterém proběhla chyba. Vzhledem k tomu, že každá třída to bude mít implementované jinak (aspoň by měla), tak je to asi jedno.
     * @param $errLine int Řádek, na kterém se chyba vyskytuje.
     * @return bool true, pokud je error vyřeřešen, pokud ne, tak false
     */
    public function errorHandle(int $errNo, string $errStr, string $errFile, int $errLine): bool;

    /** Stará se o řešení nechycených výjímek. Taky nemám šajn, jak to přesně funguje.
     * @param Throwable $exception Objekt výjimky, kterou to chytí.
     * @return void
     */
    public function exceptionHandle(Throwable $exception): void;

    /** Jednoduše metoda, který pomocí funkcí {@link set_error_handler()} a {@link set_exception_handler()} nastaví callbacky na další funkce v rozhraní.
     * @return void
     */
    public function setHandling() : void;
}