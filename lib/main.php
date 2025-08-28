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
        $enabled = Option::get(self::$moduleId, 'enabled', 'Y');
        if ($enabled !== 'Y') {
            return;
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = self::getIp();

        // Путь к данным
        $dataDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/botprotector/logs/';
        Directory::createDirectory($dataDir);

        $blockedIpFile = $dataDir . 'blocked_ip.php';
        $goodIpFile = $dataDir . 'good_ip.php';
        $botStatsFile = $dataDir . 'bots.php';
        $logFile = $dataDir . 'botprotector.log';

        $blockedIp = self::loadArray($blockedIpFile);
        $goodIp = self::loadArray($goodIpFile);

        // Читаем настройки
        $searchBots   = Option::get(self::$moduleId, 'search_bots', "YandexBot, GoogleBot, BingBot, YandexAdNet, YandexDirect, YaDirectFetcher, YandexMarket, YandexMetrika, YandexRCA, YandexRenderResourcesBot, YandexSearchShop, YandexWebmaster, Lighthouse, Yandex, Google");
        $searchBots   = array_map('trim', explode(',', $searchBots));

        $limitValue   = (int) Option::get(self::$moduleId, 'limit_value', 10);
        $timeValue    = (int) Option::get(self::$moduleId, 'time_value', 3600);
        $directValue  = (int) Option::get(self::$moduleId, 'direct_value', 60);

        $customGoodIp    = preg_split('/\r\n|\r|\n/', Option::get(self::$moduleId, 'goodIp', ''));
        $customBlockedIp = preg_split('/\r\n|\r|\n/', Option::get(self::$moduleId, 'blockedIp', ''));

        $customGoodIp    = array_filter(array_map('trim', $customGoodIp));
        $customBlockedIp = array_filter(array_map('trim', $customBlockedIp));

        // Регулярка для технических ботов
        $isTechBot = preg_match("~(Google|Lighthouse|Yahoo|Rambler|Bot|Yandex|Spider|Snoopy|Crawler|Finder|Mail|curl)~i", $ua);
            
        // === Часть 1: проверка по IP (не бот по UA) ===
        if (!$isTechBot) {
            $state = 'unknown';

            if (in_array($ip, $customBlockedIp) || in_array($ip, $blockedIp)) {
                $state = 'blacklist';
            } elseif (in_array($ip, $customGoodIp) || in_array($ip, $goodIp)) {
                $state = 'whitelist';
            } else {
                $jsonIpData = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,isp,org,as,country,query");
                $jsonIpData = @json_decode($jsonIpData, true);

                if (is_array($jsonIpData)) {
                    if (
                        (isset($jsonIpData['isp']) && self::isBadProvider($jsonIpData['isp'])) ||
                        (isset($jsonIpData['org']) && self::isBadProvider($jsonIpData['org'])) ||
                        (isset($jsonIpData['as']) && self::isBadProvider($jsonIpData['as']))
                    ) {
                        $state = 'bad_provider';
                    } else {
                        $state = 'good_provider';
                    }
                }
            }
            
            switch ($state) {
                case 'blacklist':
                    self::log($logFile, "IP {$ip} в blacklist, доступ запрещен");
                    BotBlockTable::add([
                        'IP' => $ip,
                        'USER_AGENT' => $ua,
                        'REASON' => 'Blacklist',
                    ]);
                    self::denyAccess();
                    break;

                case 'whitelist':
                    break;

                case 'bad_provider':
                    $blockedIp[] = $ip;
                    self::saveArray($blockedIpFile, $blockedIp);
                    self::log($logFile, "Бот {$ip} занесён в blacklist по данным API");
                    BotBlockTable::add([
                        'IP' => $ip,
                        'USER_AGENT' => $ua,
                        'REASON' => 'Bad provider',
                    ]);
                    self::denyAccess();
                    break;

                case 'good_provider':
                    $goodIp[] = $ip;
                    self::saveArray($goodIpFile, $goodIp);
                    break;

                case 'unknown':
                default:
                    self::log($logFile, "IP {$ip} не определён через API, пропускаем");
                    break;
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
                    if ($realtime < $directValue) {
                        $botData[$botName]['count']++;
                    } else {
                        $botData[$botName]['start_time'] = $now;
                        $botData[$botName]['count'] = 1;
                    }
                } elseif (($now - $botData[$botName]['blocked_time']) > $timeValue) {
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
                $botData[$botName]['count'] > $limitValue ||
                (!empty($botData[$botName]['blocked_time']) && ($now - $botData[$botName]['blocked_time']) < $timeValue)
            ) {
                if (empty($botData[$botName]['blocked_time'])) {
                    $botData[$botName]['blocked_time'] = $now;
                    self::saveArray($botStatsFile, $botData);
                    self::log($logFile, "Бот {$botName} был заблокирован на {$timeValue} секунд.");
                }
                BotBlockTable::add([
                    'IP' => $ip,
                    'USER_AGENT' => $ua,
                    'REASON' => 'Blocked settings',
                ]);
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