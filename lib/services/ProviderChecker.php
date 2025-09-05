<?php
namespace TR\BotProtector\Services;

class ProviderChecker {
    protected array $badProviders = ['Biterika', 'DigitalOcean'];

    public function isBad(string $str): bool {
        foreach ($this->badProviders as $provider) {
            if (stripos($str, $provider) !== false) {
                return true;
            }
        }
        return false;
    }
}
