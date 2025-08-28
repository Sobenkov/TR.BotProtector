<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Application;

class tr_botprotector extends CModule
{
    public $MODULE_ID = "tr.botprotector";
    public $MODULE_NAME = "BotProtector";
    public $MODULE_DESCRIPTION = "Блокировка нежелательных ботов (403 до загрузки страницы)";

    public function __construct()
    {
        $this->PARTNER_NAME = "TR";
        $this->MODULE_VERSION = "1.0.2";
        $this->MODULE_VERSION_DATE = "2025-08-28";
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
        CopyDirFiles(
            __DIR__."/admin/",
            $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin/",
            true,
            true
        );
        return true;
    }

    public function InstallDB()
    {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();

        if(!$connection->isTableExists("b_botprotector_log"))
        {
            $connection->queryExecute("
                CREATE TABLE b_botprotector_log (
                    ID INT AUTO_INCREMENT PRIMARY KEY,
                    DATE_INSERT DATETIME DEFAULT CURRENT_TIMESTAMP,
                    IP VARCHAR(45) NOT NULL,
                    USER_AGENT TEXT,
                    REASON VARCHAR(255) NULL
                )
            ");
        }

        RegisterModuleDependences(
            "main",
            "OnProlog",
            $this->MODULE_ID,
            "TR\\BotProtector\\main",
            "checkVisitor"
        );
        return true;
    }

    public function UninstallFiles()
    {
        DeleteDirFiles(
            __DIR__."/admin/",
            $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin/"
        );
        return true;
    }

    public function UninstallDB()
    {
        $connection = Application::getConnection();

        if($connection->isTableExists("b_botprotector_log"))
        {
            $connection->queryExecute("DROP TABLE b_botprotector_log");
        }

        UnRegisterModuleDependences(
            "main",
            "OnProlog",
            $this->MODULE_ID,
            "TR\\BotProtector\\main",
            "checkVisitor"
        );
        return true;
    }
}

?>