<?php

namespace Shestpa\Lastmodified;

class PagesBufferPurifier
{
    /**
     * @param string $content
     * @return bool false, если очистка не удалась (контент не изменён)
     */
    public static function deleteKernelJs(&$content)
    {
        if (static::isSkipped()) {
            return true;
        }

        $patterns = array(
            '/<script.+?src=".+?kernel_main\/kernel_main\.js\?\d+"><\/script\>/',
            '/<script.+?src=".+?bitrix\/js\/main\/core\/core[^"]+"><\/script\>/',
            '/<script.+?>BX\.(setCSSList|setJSList)\(\[.+?\]\).*?<\/script>/',
            '/<script.+?>if\(\!window\.BX\)window\.BX.+?<\/script>/',
            '/<script[^>]+?>\(window\.BX\|\|top\.BX\)\.message[^<]+<\/script>/',
        );

        return static::removePatterns($content, $patterns);
    }

    /**
     * @param string $content
     * @return bool
     */
    public static function deleteKernelCss(&$content)
    {
        if (static::isSkipped()) {
            return true;
        }

        $patterns = array(
            '/<link.+?href=".+?kernel_main\/kernel_main\.css\?\d+"[^>]+>/',
            '/<link.+?href=".+?bitrix\/js\/main\/core\/css\/core[^"]+"[^>]+>/',
            '/<link.+?href=".+?bitrix\/templates\/[\w\d_-]+\/styles.css[^"]+"[^>]+>/',
            '/<link.+?href=".+?bitrix\/templates\/[\w\d_-]+\/template_styles.css[^"]+"[^>]+>/',
        );

        return static::removePatterns($content, $patterns);
    }

    /**
     * Приводит контент к виду, пригодному для сравнения хешей.
     * Работает по ссылке, как и deleteKernel*.
     *
     * @param string $content
     * @return bool false, если какая-то регулярка упала (результату верить нельзя)
     */
    public static function clean(&$content)
    {
        $ok = static::deleteKernelJs($content);
        $ok = static::deleteKernelCss($content) && $ok;

        // Динамика, которая меняется от хита к хиту и контентом не является
        $result = str_replace(bitrix_sessid(), '', $content);
        $result = preg_replace(
            array(
                '/"SERVER_TIME":\d+/',
                '/bxSession\.Expand\([^)]*\)/',
            ),
            array(
                '"SERVER_TIME":0',
                'bxSession.Expand()',
            ),
            $result
        );

        if ($result === null) {
            return false;
        }

        $content = $result;

        return $ok;
    }

    /**
     * Авторизованным, админке и страницам с save_kernel=Y ядро не вырезаем.
     * @return bool
     */
    protected static function isSkipped()
    {
        global $USER, $APPLICATION;

        if (is_object($USER) && $USER->IsAuthorized()) {
            return true;
        }
        if (strpos($APPLICATION->GetCurDir(), '/bitrix/') !== false) {
            return true;
        }
        if ($APPLICATION->GetProperty('save_kernel') == 'Y') {
            return true;
        }

        return false;
    }

    /**
     * preg_replace возвращает null при ошибке (например, backtrack_limit).
     * В этом случае $content не трогаем.
     *
     * @param string $content
     * @param array  $patterns
     * @return bool
     */
    protected static function removePatterns(&$content, array $patterns)
    {
        $result = preg_replace($patterns, '', $content);
        if ($result === null) {
            return false;
        }

        $result = preg_replace("/\n{2,}/", "\n\n", $result);
        if ($result === null) {
            return false;
        }

        $content = $result;

        return true;
    }
}
