<?php

namespace exceptions;

use Exception;

/** Jedná se o výjimku, která značí zadanou nevalidní cestu k souboru ve třídě {@link \Cyphering}. Zkontrolujte zadanou cestu.
 */
class CypherInvalidPathException extends Exception {}