<?php
namespace tr\BotProtector;

use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\File;
use Bitrix\Main\IO\Directory;

class Main
{
    protected $moduleId = 'tr.botprotector';

    public function checkVisitor()
    {
        // Проверяем, включен ли модуль в настройках
        $enabled = Option::get($this->moduleId, 'enabled', 'Y');
        if ($enabled !== 'Y') {
            return;
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $this->getIp();

        // Путь к данным
        $dataDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/botguard/';
        Directory::createDirectory($dataDir);

        $blockedIpFile = $dataDir . 'blocked_ip.php';
        $goodIpFile = $dataDir . 'good_ip.php';
        $botStatsFile = $dataDir . 'bots.php';
        $logFile = $dataDir . 'botguard.log';

        $blocked_ip = $this->loadArray($blockedIpFile);
        $good_ip = $this->loadArray($goodIpFile);

        $searchBots   = $botsValue ? array_map('trim', explode(',', $botsValue)) : [
            'YandexAdNet', 'YandexDirect', 'YaDirectFetcher', 'YandexMarket',
            'YandexMetrika', 'YandexRCA', 'YandexRenderResourcesBot', 'YandexSearchShop',
            'YandexWebmaster', 'Lighthouse', 'YandexBot', 'Yandex', 'Google',
        ];

        // Регулярка для технических ботов
        $is_tech_bot = preg_match("~(Google|Lighthouse|Yahoo|Rambler|Bot|Yandex|Spider|Snoopy|Crawler|Finder|Mail|curl)~i", $ua);

        // === Часть 1: проверка по IP (не бот по UA) ===
        if (!$is_tech_bot) {
            // IP в blacklist
            if (in_array($ip, $blocked_ip)) {
                $this->denyAccess();
            }
            // Проверка по API, если нет в whitelist
            elseif (!in_array($ip, $good_ip)) {
                $jsonIpData = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,isp,org,as,country,query");
                $jsonIpData = @json_decode($jsonIpData, true);
                if (is_array($jsonIpData)) {
                    if (
                        (isset($jsonIpData['isp']) && $this->isBadProvider($jsonIpData['isp'])) ||
                        (isset($jsonIpData['org']) && $this->isBadProvider($jsonIpData['org'])) ||
                        (isset($jsonIpData['as']) && $this->isBadProvider($jsonIpData['as']))
                    ) {
                        $blocked_ip[] = $ip;
                        $this->saveArray($blockedIpFile, $blocked_ip);
                        $this->log($logFile, "Find Bot {$jsonIpData['isp']} {$ip} was blocked!");
                        $this->denyAccess();
                    } else {
                        $good_ip[] = $ip;
                        $this->saveArray($goodIpFile, $good_ip);
                    }
                }
            }
        }


        // === Часть 2: учёт и блокировка известных ботов ===
        $botName = $this->detectBot($ua, $searchBots);
    }

    public function getIp()
    {
      return $_SERVER['HTTP_CLIENT_IP'] ??
            $_SERVER['HTTP_X_FORWARDED_FOR'] ??
            $_SERVER['REMOTE_ADDR'] ??
            '0.0.0.0';  
    }

    protected function isBadProvider($str)
    {
        return stripos($str, 'Biterika') !== false ||
               stripos($str, 'DigitalOcean') !== false;
    }

    protected function detectBot($ua, $searchBots)
    {
        foreach ($searchBots as $bot) {
            if (preg_match("~({$bot})~i", $ua)) {
                return $bot;
            }
        }
        return '';
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
