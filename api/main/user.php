<?php
include "../../setup.php";

// Test things
echo $_SERVER["REQUEST_METHOD"]."\n";
echo $_SERVER["REQUEST_URI"]."\n";
echo $_GET["username"]."\n";

// Příklad url k parsování:
// modularentree.eu/api/main/user?username=ModularEntree&api_key=XXXXX


// TODO: Přepsat na objektivní přístup
//require_once $_SERVER["DOCUMENT_ROOT"]."/assets/phpClasses/MainAPIManager.php";

// TODO: Zatím komentář, kvůli testování
// header("Content-type: application/json");
// if (!isset($_GET["api_key"])) exit;

$apiKey = $_GET["api_key"];
$username = $_GET["username"];
$method = $_SERVER["REQUEST_METHOD"];
$table = explode("/", $_SERVER["REQUEST_URI"])[2];

switch ($method) {
    case "GET": {
        $database = new Database("main", "r");
        // TODO: Vytvořit GET metodu
        break;
    }
    case "POST": {
        $database = new Database("main", "w");
        // TODO: Vytvořit POST metodu
        break;
    }
    case "PUT": {
        $database = new Database("main", "wr");
        // TODO: Vytvořit PUT metodu
        break;
    }
    case "DELETE": {
        $database = new Database("main", "wr");
        // TODO: Vytvořit DELETE metodu
        break;
    }
    default: {
        header("HTTP/1.0 405 Method Not Allowed");
    }
}