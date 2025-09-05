<?php
namespace TR\BotProtector\Services;

use TR\BotProtector\Contracts\StorageInterface;

class FileStorage implements StorageInterface {
    public function load(string $file): array {
        return file_exists($file) ? include $file : [];
    }

    public function save(string $file, array $data): void {
        file_put_contents($file, "<?php\nreturn " . var_export($data, true) . ";\n");
    }
}
