<?php
namespace TR\BotProtector\Contracts;

interface LoggerInterface {
    public function log(string $title, string $message): void;
}
