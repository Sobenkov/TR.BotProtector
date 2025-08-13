<?php
namespace tr\BotGuard;

use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\File;
use Bitrix\Main\IO\Directory;

class Main
{
    protected $moduleId = 'tr.botguard';

    public function checkVisitor()
    {

    }

    public function getIp()
    {
        
    }

    protected function log($file, $message)
    {
        file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }

    protected function denyAccess()
    {
        header('HTTP/1.1 403 Forbidden');
        exit();
    }
}
