<?php
namespace TR\BotProtector\Contracts;

interface StorageInterface {
    public function load(string $file): array;
    public function save(string $file, array $data): void;
}
