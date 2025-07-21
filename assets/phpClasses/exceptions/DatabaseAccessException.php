<?php

namespace exceptions;

use Exception;

/** Výjimka, která značí, že nastala chyba při přístupu k souboru. Může značit nesprávný přístup k souboru, chybný zápis či čtení apod.
 */
class DatabaseAccessException extends Exception {}