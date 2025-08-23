<?php
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$module_id = 'tr.botprotector';

if ($APPLICATION->GetGroupRight($module_id) < "S") {
    $APPLICATION->AuthForm(Loc::getMessage("ACCESS_DENIED"));
}

// === Сохранение настроек ===
if ($_SERVER['REQUEST_METHOD'] == 'POST' && check_bitrix_sessid()) {
    Option::set($module_id, 'enabled', $_POST['enabled'] === 'Y' ? 'Y' : 'N');
    Option::set($module_id, 'good_ip', trim($_POST['good_ip']));
    Option::set($module_id, 'blocked_ip', trim($_POST['blocked_ip']));
    Option::set($module_id, 'search_bots', trim($_POST['search_bots']));
    Option::set($module_id, 'limit_value', intval($_POST['limit_value']));
    Option::set($module_id, 'time_value', intval($_POST['time_value']));
    Option::set($module_id, 'direct_value', intval($_POST['direct_value']));
}

// === Чтение настроек ===
$enabled      = Option::get($module_id, 'enabled', 'N');
$goodIp       = Option::get($module_id, 'good_ip', '');
$blockedIp    = Option::get($module_id, 'blocked_ip', '');
$searchBots   = Option::get($module_id, 'search_bots', "YandexBot, GoogleBot, BingBot");
$limitValue   = Option::get($module_id, 'limit_value', 10);
$timeValue    = Option::get($module_id, 'time_value', 3600);
$directValue  = Option::get($module_id, 'direct_value', 60);

$aTabs = [
    [
        "DIV" => "edit1",
        "TAB" => "Настройки",
        "ICON" => "",
        "TITLE" => "Основные настройки модуля"
    ],
];

$tabControl = new CAdminTabControl("tabControl", $aTabs);
?>

<form method="post" action="<? echo $APPLICATION->GetCurPage() ?>?mid=<?=urlencode($module_id)?>&lang=<?=LANG?>">
<?php
$tabControl->Begin();
$tabControl->BeginNextTab();
?>
<tr>
    <td width="40%"><label for="enabled">Включить модуль:</label></td>
    <td width="60%">
        <input type="checkbox" name="enabled" value="Y" <?php if ($enabled == 'Y') echo 'checked'; ?>>
    </td>
</tr>
<tr class="heading"><td colspan="2">IP настройки</td></tr>
<tr>
    <td>Белый список IP:</td>
    <td><textarea name="good_ip" rows="4" cols="50"><?=htmlspecialcharsbx($goodIp)?></textarea></td>
</tr>
<tr>
    <td>Черный список IP:</td>
    <td><textarea name="blocked_ip" rows="4" cols="50"><?=htmlspecialcharsbx($blockedIp)?></textarea></td>
</tr>

<tr class="heading"><td colspan="2">Настройки ботов</td></tr>
<tr>
    <td>Список ботов (через запятую):</td>
    <td><textarea name="search_bots" rows="4" cols="50"><?=htmlspecialcharsbx($searchBots)?></textarea></td>
</tr>
<tr>
    <td>Максимум запросов:</td>
    <td><input type="text" name="limit_value" size="5" value="<?=$limitValue?>"></td>
</tr>
<tr>
    <td>Блокировка (секунд):</td>
    <td><input type="text" name="time_value" size="5" value="<?=$timeValue?>"></td>
</tr>
<tr>
    <td>Окно анализа активности (секунд):</td>
    <td><input type="text" name="direct_value" size="5" value="<?=$directValue?>"></td>
</tr>

<?php
$tabControl->Buttons();
?>
    <input type="submit" name="save" value="<?=GetMessage("MAIN_SAVE")?>" class="adm-btn-save">
    <?=bitrix_sessid_post();?>
<?php
$tabControl->End();
?>
</form>
