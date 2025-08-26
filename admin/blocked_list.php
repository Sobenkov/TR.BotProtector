<?php
use Bitrix\Main\Loader;
use TR\BotProtector\BlockedTable;

require_once $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php";

Loader::includeModule('tr.botprotector');

$sTableID = "tbl_tr_botprotector_blocked";
$oSort = new CAdminSorting($sTableID, "ID", "desc");
$lAdmin = new CAdminList($sTableID, $oSort);

$rsData = BlockedTable::getList([
    'order' => ['ID' => 'DESC'],
]);
$rsData = new CAdminResult($rsData, $sTableID);
$lAdmin->NavText($rsData->GetNavPrint("Записи"));

$lAdmin->AddHeaders([
    ["id"=>"ID", "content"=>"ID", "sort"=>"ID", "default"=>true],
    ["id"=>"IP", "content"=>"IP", "default"=>true],
    ["id"=>"USER_AGENT", "content"=>"User-Agent", "default"=>true],
    ["id"=>"DATE_BLOCKED", "content"=>"Дата блокировки", "default"=>true],
]);

while ($arRes = $rsData->NavNext(true, "f_")) {
    $row =& $lAdmin->AddRow($f_ID, $arRes);
    $row->AddViewField("ID", $f_ID);
    $row->AddViewField("IP", htmlspecialcharsbx($f_IP));
    $row->AddViewField("USER_AGENT", htmlspecialcharsbx($f_USER_AGENT));
    $row->AddViewField("DATE_BLOCKED", $f_DATE_BLOCKED);

    $arActions = [];
    $arActions[] = [
        "ICON"=>"delete",
        "TEXT"=>"Удалить",
        "ACTION"=>"if(confirm('Удалить запись?')) ".$lAdmin->ActionDoGroup($f_ID, "delete"),
    ];

    $row->AddActions($arActions);
}

// Групповые действия
$lAdmin->AddGroupActionTable([
    "delete"=>"Удалить",
]);

$lAdmin->CheckListMode();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$lAdmin->DisplayList();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
