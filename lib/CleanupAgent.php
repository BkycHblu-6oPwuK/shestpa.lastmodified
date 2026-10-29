<?php

namespace Shestpa\Lastmodified;

class CleanupAgent
{
    const CLEANUP_DAYS  = 30;
    const CLEANUP_LIMIT = 5000;

    /**
     * Агент: удаляет записи, к которым не обращались CLEANUP_DAYS дней.
     * Агент обязан вернуть строку собственного вызова.
     *
     * @return string
     */
    public static function exec()
    {
        try {
            $connection = \Bitrix\Main\Application::getConnection();
            $sqlHelper  = $connection->getSqlHelper();
            $table      = PagesTimestampTable::getTableName();

            $threshold = \Bitrix\Main\Type\DateTime::createFromTimestamp(
                time() - static::CLEANUP_DAYS * 86400
            );

            $connection->queryExecute(
                'DELETE FROM ' . $table .
                    ' WHERE DATE_CHECK < ' . $sqlHelper->convertToDbDateTime($threshold) .
                    ' LIMIT ' . (int)static::CLEANUP_LIMIT
            );
        } catch (\Exception $e) {
            AddMessage2Log($e->getMessage(), LastModifiedHelper::MODULE_ID);
        }

        return '\\Shestpa\\Lastmodified\\CleanupAgent::exec();';
    }
}
