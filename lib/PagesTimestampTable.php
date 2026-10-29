<?php

namespace Shestpa\Lastmodified;

use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

Loc::loadMessages(__FILE__);

class PagesTimestampTable extends DataManager
{
    public static function getTableName()
    {
        return 'shestpa_pages_timestamp';
    }

    public static function getMap()
    {
        return array(
            new IntegerField('ID', array(
                'autocomplete' => true,
                'primary' => true,
                'title' => Loc::getMessage('SHESTPA_TABLE_ID'),
            )),
            new StringField('URL', array(
                'required' => true,
                'size' => 32,
                'title' => Loc::getMessage('SHESTPA_TABLE_URL'),
                'validation' => function () {
                    return array(new LengthValidator(null, 32));
                },
            )),
            new StringField('HASH', array(
                'required' => true,
                'size' => 32,
                'title' => Loc::getMessage('SHESTPA_TABLE_HASH'),
                'validation' => function () {
                    return array(new LengthValidator(null, 32));
                },
            )),
            new StringField('LAST_MODIFIED', array(
                'required' => true,
                'size' => 50,
                'title' => Loc::getMessage('SHESTPA_TABLE_LAST_MODIFIED'),
                'validation' => function () {
                    return array(new LengthValidator(null, 50));
                },
            )),
            new DatetimeField('DATE_CHECK', array(
                'required' => true,
                'default_value' => function () {
                    return new DateTime();
                },
                'title' => Loc::getMessage('SHESTPA_TABLE_DATE_CHECK'),
            )),
        );
    }
}
