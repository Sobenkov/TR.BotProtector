<?php
namespace TR\BotProtector\Services;

use TR\BotProtector\Contracts\LoggerInterface;
use Bitrix\Main\Diag\Debug;

class FileLogger implements LoggerInterface {
    protected string $path;

    public function __construct(string $path) {
        $this->path = $path;
    }

    public function log(string $title, string $message): void {
        Debug::writeToFile($message, $title, $this->path);
    }
}
