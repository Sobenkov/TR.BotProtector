<?php
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$aMenu = [
    "parent_menu" => "global_menu_services", // куда встраиваем: настройки, сервисы и т.д.
    "section" => "tr_botprotector",
    "sort" => 100,
    "text" => "BotProtector",
    "title" => "Блокировка ботов",
    "icon" => "default_menu_icon",
    "items_id" => "menu_tr_botprotector",
    "items" => [
        [
            "text" => "Настройки",
            "url" => "settings.php?mid=tr.botprotector&lang=".LANG,
            "title" => "Настройки модуля",
        ],
        [
            "text" => "Заблокированные боты",
            "url" => "tr_botprotector_blocked_list.php?lang=".LANG,
            "title" => "Список заблокированных",
        ],
    ],
];

return $aMenu;
