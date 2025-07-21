<?php

namespace exceptions;

use Exception;

/** Výjimka oznamující, že požadovaná databáze v existujících databázích neexistuje.
 */
class DatabaseDoesNotExistsException extends Exception {}