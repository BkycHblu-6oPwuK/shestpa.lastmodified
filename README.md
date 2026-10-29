# shestpa.lastmodified

Модуль для установки заголовка Last-Modified и http-статуса 304 на страницах сайта.

Проверить корректную работу модуля на сайте можно с помощью [одного из специальных сервисов](http://last-modified.com/)

При установке создается агент очистки таблицы (`\Shestpa\Lastmodified\CleanupAgent::exec();`), удаляются записи страниц к которым не обращались 30 дней

## Использование
После установки модуля необходимо его подключить в файле init.php с помощью методов API 1С-Битрикс и вызвать метод регистрации обработчика:
```php
if (\Bitrix\Main\Loader::includeModule('shestpa.lastmodified')) {
    \Shestpa\LastModified\LastModifiedHelper::registerEvent();
}
```
или вручную:
```php
if(\Bitrix\Main\Loader::includeModule('shestpa.lastmodified')) {
    \Bitrix\Main\EventManager::getInstance()->addEventHandler(
        'main',
        'OnEndBufferContent',
        ['\Shestpa\LastModified\LastModifiedHelper', 'onEndBufferContent'],
        false,
        1
    );
}
```

остановить применение заголовка Last-Modified можно с помощью метода:
```php
\Shestpa\LastModified\LastModifiedHelper::enableStopSetLastModified();
```

проверить что заголовок Last-Modified был установлен можно с помощью метода:
```php
\Shestpa\LastModified\LastModifiedHelper::isLastModifiedSet();
```

вручную вызвать обработчик можно с помощью метода:
```php
\Shestpa\LastModified\LastModifiedHelper::onEndBufferContent($content);
```

## Исключения
Модуль не устанавливает заголовки для:
- страниц административного раздела
- страниц со статусом 404
- если скрипт запущен из командной строки (CLI)
- для AJAX-запросов и POST-запросов
- если заголовок Last-Modified уже установлен
- если на странице был вызван метод `\Shestpa\LastModified\LastModifiedHelper::enableStopSetLastModified()`
- если код ответа уже не 200
- если авторизован пользователь админ
- для директории /personal
- если ответ не html

## логи

Ошибки пишутся с помощью дефолтной функции `AddMessage2Log`