<?if if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\ModuleManager;

class tr_botprotector extends CModule
{
    public $MODULE_ID = "tr.botprotector";
    public $MODULE_NAME = "Модуль BotProtector";
    public $MODULE_DESCRIPTION = "Блокировка нежелательных ботов (403 до загрузки страницы)";

    public function __construct()
    {
        $this->PARTNER_NAME = "TR";
        $this->MODULE_VERSION = "1.0.0";
        $this->MODULE_VERSION_DATE = "2025-08-13";
    }

    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallFiles();
        $this->InstallDB();
    }

    public function DoUninstall()
    {
        $this->UninstallDB();
        $this->UninstallFiles();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function InstallFiles()
    {
        return true;
    }

    public function InstallDB()
    {
        // Регистрируем обработчик события
        RegisterModuleDependences(
            "main",
            "OnBeforeProlog",
            $this->MODULE_ID,
            "\\TR\\BotProtector\\Main",
            "checkVisitor"
        );
        return true;
    }

    public function UninstallFiles()
    {
        return true;
    }

    public function UninstallDB()
    {
        // Снимаем обработчик события
        UnRegisterModuleDependences(
            "main",
            "OnBeforeProlog",
            $this->MODULE_ID,
            "\\TR\\BotProtector\\Main",
            "checkVisitor"
        );
        return true;
    }
}

?>