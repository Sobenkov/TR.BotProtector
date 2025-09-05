<?php
namespace TR\BotProtector\Services;

class BotDetector {
    public function detectBot(string $ua, array $searchBots): string {
        foreach ($searchBots as $bot) {
            if (preg_match("~({$bot})~i", $ua)) {
                return $bot;
            }
        }
        return '';
    }

    public function isTechBot(string $ua): bool {
        return preg_match("~(Google|Lighthouse|Yahoo|Rambler|Bot|Yandex|Spider|Crawler|Mail|curl)~i", $ua);
    }
}
