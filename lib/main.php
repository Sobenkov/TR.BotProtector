<?php
namespace TR\BotProtector;

use Bitrix\Main\Config\Option;
use TR\BotProtector\Contracts\LoggerInterface;
use TR\BotProtector\Contracts\StorageInterface;
use TR\BotProtector\Services\IpResolver;
use TR\BotProtector\Services\ProviderChecker;
use TR\BotProtector\Services\BotDetector;

class Main {
    protected static $moduleId = 'tr.botprotector';

    protected StorageInterface $storage;
    protected LoggerInterface $logger;
    protected IpResolver $ipResolver;
    protected ProviderChecker $providerChecker;
    protected BotDetector $botDetector;

    protected string $dataDir;

    public function __construct(
        StorageInterface $storage,
        LoggerInterface $logger,
        IpResolver $ipResolver,
        ProviderChecker $providerChecker,
        BotDetector $botDetector
    ) {
        $this->storage = $storage;
        $this->logger = $logger;
        $this->ipResolver = $ipResolver;
        $this->providerChecker = $providerChecker;
        $this->botDetector = $botDetector;

        $this->dataDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/botprotector/logs/';
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0775, true);
        }
    }

    public function checkVisitor(): void {
        $enabled = Option::get(self::$moduleId, 'enabled', 'Y');
        if ($enabled !== 'Y') {
            return;
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $this->ipResolver->getIp();

        $blockedIpFile = $this->dataDir . 'blocked_ip.php';
        $goodIpFile    = $this->dataDir . 'good_ip.php';
        $botStatsFile  = $this->dataDir . 'bots.php';

        $blockedIp = $this->storage->load($blockedIpFile);
        $goodIp    = $this->storage->load($goodIpFile);

        $searchBots = array_map('trim', explode(',', Option::get(
            self::$moduleId,
            'search_bots',
            "YandexBot, GoogleBot, BingBot, Yandex, Google"
        )));

        $limitValue  = (int) Option::get(self::$moduleId, 'limit_value', 10);
        $timeValue   = (int) Option::get(self::$moduleId, 'time_value', 3600);
        $directValue = (int) Option::get(self::$moduleId, 'direct_value', 60);

        // === Часть 1: проверка по IP ===
        if (!$this->botDetector->isTechBot($ua)) {
            $state = $this->checkIp($ip, $ua, $blockedIp, $goodIp, $blockedIpFile, $goodIpFile);
            if ($state === 'deny') {
                $this->denyAccess();
            }
        }

        // === Часть 2: проверка ботов ===
        $botName = $this->botDetector->detectBot($ua, $searchBots);
        if ($botName) {
            $this->checkBot($botName, $ip, $ua, $botStatsFile, $limitValue, $timeValue, $directValue);
        }
    }

    protected function checkIp(string $ip, string $ua, array &$blockedIp, array &$goodIp, string $blockedIpFile, string $goodIpFile): string {
        if (in_array($ip, $blockedIp)) {
            $this->logger->log("Blacklist", "IP {$ip} в blacklist, доступ запрещен");
            $this->denyAccess();
            return 'deny';
        }

        if (in_array($ip, $goodIp)) {
            return 'allow';
        }

        $jsonIpData = @file_get_contents("http://ip-api.com/json/{$ip}?fields=isp,org,as");
        $jsonIpData = @json_decode($jsonIpData, true);

        if (is_array($jsonIpData)) {
            if (
                ($jsonIpData['isp'] && $this->providerChecker->isBad($jsonIpData['isp'])) ||
                ($jsonIpData['org'] && $this->providerChecker->isBad($jsonIpData['org'])) ||
                ($jsonIpData['as']  && $this->providerChecker->isBad($jsonIpData['as']))
            ) {
                $blockedIp[] = $ip;
                $this->storage->save($blockedIpFile, $blockedIp);
                $this->logger->log("Bad_provider", "Бот {$ip} занесён в blacklist по данным API");
                $this->denyAccess();
                return 'deny';
            } else {
                $goodIp[] = $ip;
                $this->storage->save($goodIpFile, $goodIp);
                return 'allow';
            }
        }

        $this->logger->log("Unknown", "IP {$ip} не определён через API, пропускаем");
        return 'unknown';
    }

    protected function checkBot(string $botName, string $ip, string $ua, string $botStatsFile, int $limitValue, int $timeValue, int $directValue): void {
        $botData = $this->storage->load($botStatsFile);
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
                'start_time'   => $now,
                'count'        => 1,
                'blocked_time' => '',
            ];
        }

        if (
            $botData[$botName]['count'] > $limitValue ||
            (!empty($botData[$botName]['blocked_time']) && ($now - $botData[$botName]['blocked_time']) < $timeValue)
        ) {
            if (empty($botData[$botName]['blocked_time'])) {
                $botData[$botName]['blocked_time'] = $now;
                $this->storage->save($botStatsFile, $botData);
                $this->logger->log("Blocked", "Бот {$botName} был заблокирован на {$timeValue} секунд.");
            }
            $this->denyAccess();
        }

        $this->storage->save($botStatsFile, $botData);
    }

    protected function denyAccess(): void {
        header('HTTP/1.1 403 Forbidden');
        exit();
    }
}
