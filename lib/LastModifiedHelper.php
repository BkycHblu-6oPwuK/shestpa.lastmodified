<?php

namespace Shestpa\Lastmodified;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\HttpResponse;

class LastModifiedHelper
{
    const MODULE_ID = 'shestpa.lastmodified';

    /** GET-параметры, которые не влияют на контент (иначе в таблице тысячи дублей) */
    protected static $ignoredParams = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'yclid',
        'gclid',
        'fbclid',
        'clear_cache',
        'sessid',
        '_openstat',
    ];

    protected static $forceStopSetLastModified = false;
    protected static $registered = false;

    /**
     * Включает флаг, запрещающий установку Last-Modified для текущего запроса
     * @return void
     */
    public static function enableStopSetLastModified()
    {
        static::$forceStopSetLastModified = true;
    }

    /**
     * Обработчик main:OnEndBufferContent
     * @param string $content
     * @return void
     */
    public static function onEndBufferContent(&$content)
    {
        if (!static::isApplicable($content)) {
            return;
        }

        $context  = Context::getCurrent();
        $response = $context->getResponse();

        if (static::isLastModifiedSet($response)) {
            return;
        }

        try {
            $normalized = static::normalizeContent($content);
            if ($normalized === null) {
                return;
            }
            $hash = md5($normalized);
            $key  = static::getPageKey();
            $now  = time();

            $row = PagesTimestampTable::getList(array(
                'select' => array('ID', 'LAST_MODIFIED', 'HASH'),
                'filter' => array('=URL' => $key),
                'limit'  => 1,
            ))->fetch();

            $lastModified = $now;
            $status       = 'added';

            if (!$row) {
                PagesTimestampTable::add(array(
                    'URL'           => $key,
                    'LAST_MODIFIED' => static::formatHttpDate($now),
                    'HASH'          => $hash,
                ));
            } elseif ($row['HASH'] === $hash) {
                $status = 'notmod';
                $ts = strtotime($row['LAST_MODIFIED']);
                if ($ts) {
                    $lastModified = $ts;
                }
            } else {
                $status = 'mod';
                PagesTimestampTable::update($row['ID'], array(
                    'LAST_MODIFIED' => static::formatHttpDate($now),
                    'HASH'          => $hash,
                ));
            }

            $etag = '"' . $hash . '"';

            $response->setLastModified(DateTime::createFromTimestamp($lastModified));
            $response->addHeader('ETag', $etag);

            // 304 только если страница реально не менялась
            if ($status === 'notmod' && static::isNotModified($lastModified, $etag)) {
                $response->setStatus('304 Not Modified');
                $content = '';
            }

            if (Option::get(static::MODULE_ID, 'debug', 'N') === 'Y') {
                $response->addHeader('Hash-Modified', $status . $hash);
            }
        } catch (\Exception $e) {
            AddMessage2Log($e->getMessage(), static::MODULE_ID);
        } catch (\Throwable $e) {
            AddMessage2Log($e->getMessage(), static::MODULE_ID);
        }
    }

    /**
     * Проверка protected-свойства HttpResponse::$lastModified
     * (+ заголовок, добавленный через addHeader() или header()).
     * @param HttpResponse $response
     * @return bool
     */
    public static function isLastModifiedSet($response)
    {
        if ($response->getHeaders()->get('Last-Modified')) {
            return true;
        }

        foreach (headers_list() as $header) {
            if (stripos($header, 'Last-Modified:') === 0) {
                return true;
            }
        }

        try {
            $prop = new \ReflectionProperty($response, 'lastModified');
            if (version_compare(phpversion(), '8.1', '<')) {
                $prop->setAccessible(true);
            }
            return $prop->getValue($response) !== null;
        } catch (\ReflectionException $e) {
            return false; // в этой версии ядра свойства нет
        }
    }

    public static function registerEvent()
    {
        if (self::$registered || self::isCli()) {
            return false;
        }

        if (defined('ADMIN_SECTION') || defined('BX_CRONTAB') || defined('NO_AGENT_CHECK')) {
            return false;
        }

        \Bitrix\Main\EventManager::getInstance()->addEventHandler(
            'main',
            'OnEndBufferContent',
            [__CLASS__, 'onEndBufferContent'],
            false,
            1
        );

        self::$registered = true;

        return true;
    }

    /**
     * @return bool
     */
    protected static function isCli()
    {
        return php_sapi_name() === 'cli';
    }

    /**
     * @param string $content
     * @return bool
     */
    protected static function isApplicable($content)
    {
        global $USER, $APPLICATION;

        if (static::$forceStopSetLastModified) {
            return false;
        }
        if (static::isCli()) {
            return false;
        }
        if (defined('ADMIN_SECTION') || defined('ERROR_404') || defined('PUBLIC_AJAX_MODE')) {
            return false;
        }
        if (!is_object($USER) || !is_object($APPLICATION)) {
            return false;
        }
        if ($USER->IsAdmin()) {
            return false;
        }

        $request = Context::getCurrent()->getRequest();

        if (!in_array($request->getRequestMethod(), array('GET', 'HEAD'), true)) {
            return false;
        }
        if ($request->isAjaxRequest()) {
            return false;
        }

        $code = http_response_code();
        if ($code && $code != 200) {
            return false;
        }

        if ($APPLICATION->GetShowIncludeAreas()) {
            return false;
        }
        if (\CSite::InDir('/personal/') || \CSite::InDir('/bitrix/')) {
            return false;
        }

        return stripos($content, '</html>') !== false;
    }

    /**
     * Уникальный ключ страницы: сайт + хост + URI (без мусорных параметров) + группы.
     * @return string
     */
    protected static function getPageKey()
    {
        global $USER, $APPLICATION;

        $request = Context::getCurrent()->getRequest();
        $groups  = $USER->GetUserGroupArray();
        sort($groups);

        $uri = $APPLICATION->GetCurPageParam('', static::$ignoredParams, false);

        return md5(SITE_ID . '|' . $request->getHttpHost() . '|' . $uri . '|' . implode(',', $groups));
    }

    /**
     * Убираем всё, что меняется от хита к хиту и не является контентом.
     * @param string $content
     * @return string|null
     */
    protected static function normalizeContent($content)
    {
        if (!PagesBufferPurifier::clean($content)) {
            return null;
        }

        return $content;
    }

    /**
     * ETag имеет приоритет над If-Modified-Since (RFC 7232).
     * @param int $lastModified
     * @param string $etag
     * @return bool
     */
    protected static function isNotModified($lastModified, $etag)
    {
        if (!empty($_SERVER['HTTP_IF_NONE_MATCH'])) {
            foreach (explode(',', $_SERVER['HTTP_IF_NONE_MATCH']) as $candidate) {
                $candidate = trim($candidate);
                if (strpos($candidate, 'W/') === 0) {
                    $candidate = substr($candidate, 2);
                }
                if ($candidate === $etag || $candidate === '*') {
                    return true;
                }
            }
            return false;
        }

        if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $parts = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
            $since = strtotime(trim($parts[0]));
            return $since !== false && $since >= $lastModified;
        }

        return false;
    }

    /**
     * @param int $timestamp
     * @return string
     */
    protected static function formatHttpDate($timestamp)
    {
        return gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';
    }
}
