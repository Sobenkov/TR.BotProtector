<?php
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$module_id = 'tr.botprotector';

if ($APPLICATION->GetGroupRight($module_id) < "S") {
    $APPLICATION->AuthForm(Loc::getMessage("ACCESS_DENIED"));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && check_bitrix_sessid()) {
    Option::set($module_id, 'enabled', $_POST['enabled'] === 'Y' ? 'Y' : 'N');
}

$enabled = Option::get($module_id, 'enabled', 'N');

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
<?php
$tabControl->Buttons();
?>
    <input type="submit" name="save" value="<?=GetMessage("MAIN_SAVE")?>" class="adm-btn-save">
    <?=bitrix_sessid_post();?>
<?php
$tabControl->End();
?>
</form>
