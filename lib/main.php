<?php
namespace TR\BotProtector;

use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\File;
use Bitrix\Main\IO\Directory;

class Main
{
    protected static $moduleId = 'tr.botprotector';

    public static function checkVisitor()
    {
        // Проверяем, включен ли модуль в настройках
        $enabled = Option::get($this->moduleId, 'enabled', 'Y');
        if ($enabled !== 'Y') {
            return;
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $this->getIp();

        // Путь к данным
        $dataDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/botprotector/';
        Directory::createDirectory($dataDir);

        $blockedIpFile = $dataDir . 'blocked_ip.php';
        $goodIpFile = $dataDir . 'good_ip.php';
        $botStatsFile = $dataDir . 'bots.php';
        $logFile = $dataDir . 'botprotector.log';

        $blocked_ip = self::loadArray($blockedIpFile);
        $good_ip = self::loadArray($goodIpFile);

        $searchBots   = $botsValue ? array_map('trim', explode(',', $botsValue)) : [
            'YandexAdNet', 'YandexDirect', 'YaDirectFetcher', 'YandexMarket',
            'YandexMetrika', 'YandexRCA', 'YandexRenderResourcesBot', 'YandexSearchShop',
            'YandexWebmaster', 'Lighthouse', 'YandexBot', 'Yandex', 'Google',
        ];

        // Регулярка для технических ботов
        $is_tech_bot = preg_match("~(Google|Lighthouse|Yahoo|Rambler|Bot|Yandex|Spider|Snoopy|Crawler|Finder|Mail|curl)~i", $ua);

        // временное решение, принудительная блокировка для тестов
        // $blacklist = ['192.168.0.1','203.0.113.45'];


        // === Часть 1: проверка по IP (не бот по UA) ===
        if (!$is_tech_bot) {
            // Проверка по API, если нет в whitelist
            if (in_array($ip, $blocked_ip)) {
                self::denyAccess();
            }
            // IP в blacklist
            // if (in_array($ip, $blacklist)) {
            //     $this->denyAccess();
                
            //     $this->log($logFile, "Тестовая блокировка прошла успешно.");
            // }
            elseif (!in_array($ip, $good_ip)) {
                $jsonIpData = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,isp,org,as,country,query");
                $jsonIpData = @json_decode($jsonIpData, true);
                if (is_array($jsonIpData)) {
                    if (
                        (isset($jsonIpData['isp']) && self::isBadProvider($jsonIpData['isp'])) ||
                        (isset($jsonIpData['org']) && self::isBadProvider($jsonIpData['org'])) ||
                        (isset($jsonIpData['as']) && self::isBadProvider($jsonIpData['as']))
                    ) {
                        $blocked_ip[] = $ip;
                        self::saveArray($blockedIpFile, $blocked_ip);
                        self::log($logFile, "Бот {$jsonIpData['isp']} {$ip} заблокирован!");
                        self::denyAccess();
                    } else {
                        $good_ip[] = $ip;
                        self::saveArray($goodIpFile, $good_ip);
                    }
                }
            }
        }


        // === Часть 2: учёт и блокировка известных ботов ===
        $botName = self::detectBot($ua, $searchBots);

        if ($botName) {
            $botData = self::loadArray($botStatsFile);
            $now = time();

            if (isset($botData[$botName])) {
                $realtime = $now - $botData[$botName]['start_time'];

                if (empty($botData[$botName]['blocked_time'])) {
                    // if ($realtime < $directValue) {
                    if ($realtime < 60) { // directValue (пока фиксировано)
                        $botData[$botName]['count']++;
                    } else {
                        $botData[$botName]['start_time'] = $now;
                        $botData[$botName]['count'] = 1;
                    }
                // } elseif (($now - $botData[$botName]['blocked_time']) > $timeValue) {
                } elseif (($now - $botData[$botName]['blocked_time']) > 3600) { // timeValue (пока фиксировано)
                    $botData[$botName]['start_time'] = $now;
                    $botData[$botName]['count'] = 1;
                    $botData[$botName]['blocked_time'] = '';
                }
            } else {
                $botData[$botName] = [
                    'start_time' => $now,
                    'count' => 1,
                    'blocked_time' => '',
                ];
            }

            if (
                // $botData[$botName]['count'] > $limitValue ||
                $botData[$botName]['count'] > 10 || // limitValue (пока фиксировано)
                // (!empty($botData[$botName]['blocked_time']) && ($now - $botData[$botName]['blocked_time']) < $timeValue)
                (!empty($botData[$botName]['blocked_time']) && ($now - $botData[$botName]['blocked_time']) < 3600)
            ) {
                if (empty($botData[$botName]['blocked_time'])) {
                    $botData[$botName]['blocked_time'] = $now;
                    self::saveArray($botStatsFile, $botData);
                    self::log($logFile, "Бот {$botName} был заблокирован на {$timeValue} секунд.");
                }
                self::denyAccess();
            }

            self::saveArray($botStatsFile, $botData);
        }
    }

    public static function getIp()
    {
      return $_SERVER['HTTP_CLIENT_IP'] ??
            $_SERVER['HTTP_X_FORWARDED_FOR'] ??
            $_SERVER['REMOTE_ADDR'] ??
            '0.0.0.0';  
    }

    protected static function isBadProvider($str)
    {
        return stripos($str, 'Biterika') !== false ||
               stripos($str, 'DigitalOcean') !== false;
    }

    protected static function detectBot($ua, $searchBots)
    {
        foreach ($searchBots as $bot) {
            if (preg_match("~({$bot})~i", $ua)) {
                return $bot;
            }
        }
        return '';
    }

    protected static function log($file, $message)
    {
        file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }

    protected static function denyAccess()
    {
        header('HTTP/1.1 403 Forbidden');
        exit();
    }

    protected static function loadArray($file)
    {
        if (file_exists($file)) {
            return include $file;
        }
        return [];
    }

    protected static function saveArray($file, $array)
    {
        file_put_contents($file, "<?php\nreturn " . var_export($array, true) . ";\n");
    }
}
