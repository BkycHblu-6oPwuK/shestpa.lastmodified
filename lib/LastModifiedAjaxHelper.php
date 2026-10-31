<?php

namespace Shestpa\Lastmodified;

/**
 * Класс для обработки AJAX-запросов с отключением проверок директорий и типа контента.
 * Класс не предполагает использоваться в событии onEndBufferContent, только ручной вызов
 */
class LastModifiedAjaxHelper extends LastModifiedHelper
{
    /**
     * Отключаем проверку директорий для AJAX-запросов всегда.
     */
    protected static function dirsCheck()
    {
        return true;
    }

    /**
     * Отключаем проверку типа контента для AJAX-запросов всегда.
     */
    protected static function typeContentCheck($content)
    {
        return true;
    }

    /**
     * @param \Bitrix\Main\HttpRequest $request
     */
    protected static function ajaxCheck($request)
    {
        return false; // Для AJAX-запросов всегда возвращаем false, чтобы отключить стандартную проверку AJAX.
    }
}
