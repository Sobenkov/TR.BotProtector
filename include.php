<?
use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses("tr.botprotector", [
    "TR\\BotProtector\\Main" => "lib/Main.php",
    "TR\\BotProtector\\BotProtectorTable" => "lib/BotProtectorTable.php",
]);
