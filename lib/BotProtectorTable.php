<?php
namespace TR\BotProtector;

use Bitrix\Main\Entity;
use Bitrix\Main\Type;

class BotProtectorTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'b_botprotector_log';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new Entity\DatetimeField('DATE_INSERT', [
                'default_value' => function () {
                    return new Type\DateTime();
                }
            ]),
            new Entity\StringField('IP', [
                'required' => true,
                'validation' => function () {
                    return [
                        function ($value) {
                            return (strlen($value) <= 45)
                                ? true
                                : 'IP должен быть не длиннее 45 символов';
                        }
                    ];
                }
            ]),
            new Entity\TextField('USER_AGENT'),
            new Entity\StringField('REASON', [
                'required' => false,
            ]),
        ];
    }
}
