<?php
defined('B_PROLOG_INCLUDED') and (B_PROLOG_INCLUDED === true) or die();

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Shestpa\Lastmodified\PagesTimestampTable;

Loc::loadMessages(__FILE__);

if (class_exists('shestpa_lastmodified')) {
    return;
}

class shestpa_lastmodified extends CModule
{
    /** @var string */
    public $MODULE_ID;

    /** @var string */
    public $MODULE_VERSION;

    /** @var string */
    public $MODULE_VERSION_DATE;

    /** @var string */
    public $MODULE_NAME;

    /** @var string */
    public $MODULE_DESCRIPTION;

    /** @var string */
    public $MODULE_GROUP_RIGHTS;

    /** @var string */
    public $PARTNER_NAME;

    public function __construct()
    {
        $arModuleVersion = array();
        require __DIR__ . '/version.php';

        $this->MODULE_ID = 'shestpa.lastmodified';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('SHESTPA_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('SHESTPA_MODULE_DESCRIPTION');
        $this->MODULE_GROUP_RIGHTS = 'N';
        $this->PARTNER_NAME = 'Shestpa';
    }

    public function doInstall()
    {
        global $APPLICATION;

        if ($this->checkRequirements()) {
            ModuleManager::registerModule($this->MODULE_ID);
            $this->installDB();
            $this->installAgents();
        } else {
            $APPLICATION->ThrowException(Loc::getMessage('SHESTPA_MODULE_INSTALL_ERROR'));
        }
    }

    public function doUninstall()
    {
        $this->uninstallAgents();
        $this->uninstallEvents();
        $this->uninstallDB();
        ModuleManager::unregisterModule($this->MODULE_ID);
    }

    public function installDB()
    {
        if (!Loader::includeModule($this->MODULE_ID)) {
            return;
        }

        $connection = Application::getConnection();
        $table = PagesTimestampTable::getTableName();

        if (!$connection->isTableExists($table)) {
            PagesTimestampTable::getEntity()->createDbTable();

            $connection->queryExecute(
                'ALTER TABLE ' . $table . ' ADD UNIQUE INDEX ux_pages_timestamp_url (URL)'
            );
            $connection->queryExecute(
                'ALTER TABLE ' . $table . ' ADD INDEX ix_pages_timestamp_date_check (DATE_CHECK)'
            );
        }
    }

    public function uninstallDB()
    {
        if (Loader::includeModule($this->MODULE_ID)) {
            $connection = Application::getConnection();
            $table = PagesTimestampTable::getTableName();

            if ($connection->isTableExists($table)) {
                $connection->dropTable($table);
            }
        }
    }

    public function installAgents()
    {
        CAgent::AddAgent(
            '\\Shestpa\\Lastmodified\\CleanupAgent::exec();',
            $this->MODULE_ID,
            'N',
            86400
        );
    }

    public function uninstallAgents()
    {
        CAgent::RemoveModuleAgents($this->MODULE_ID);
    }

    /**
     * @return bool
     */
    public function checkRequirements()
    {
        return version_compare(ModuleManager::getVersion('main'), '18.0.2') >= 0;
    }
}
