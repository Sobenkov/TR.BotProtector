<?php
namespace TR\BotProtector\Services;

class IpResolver {
    public function getIp(): string {
        return $_SERVER['HTTP_CLIENT_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }
}
