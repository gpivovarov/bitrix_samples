<?php

namespace BSamples;

/**
 * Class CDN
 *
 * Сервер автоматически создает у себя файл при первичном запросе.
 * Класс позволяет сбросить кеш только адресно. Для полной очистки используется purge из консоли целевого сервера.
 *
 **/
class CDN
{
    /**
     * Расширения, которые поддерживает cdn сервер
     */
    const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
    ];
    /**
     * IP адреса серверов с CDN
     */
    const SERVER_ADDRESSES = [
        '0.0.0.0'
    ];

    /**
     * Хост CDN
     */
    const CDN_HOST = 'http://localhost';

    /**
     * Адресный сброс CDN кеша
     * @param $url - абсолютный путь к файлу вместе с хостом
     * @return void
     */
    public static function clearCache($path)
    {
        $relPath = substr($path, strlen(self::CDN_HOST));
        if (empty($relPath)) {
            return;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PURGE');

        foreach (self::SERVER_ADDRESSES as $addr) {
            curl_setopt($ch, CURLOPT_URL, 'http://'.$addr.$relPath);
            curl_exec($ch);
        }

        curl_close($ch);
    }

    /**
     * Заменяет ссылки на поддерживаемые типы файлов с относительного адреса на абсолютные
     * @param string $content
     * @return void
     */
    public static function replaceSrc(&$content)
    {
        if (\Bitrix\Main\Context::getCurrent()->getRequest()->isAdminSection() || (defined(
                    'DISABLE_CDN_REPLACE'
                ) && \DISABLE_CDN_REPLACE === true)) {
            return;
        }

        $extension_regex = "(?i:".implode("|", self::ALLOWED_EXTENSIONS).")";
        $regex = "/
            ((?i:
                (?<!;)href=
                |(?<!;)src=
                |(?<!;)data-src=
                |(?<!;)data-lazy=
                |(?<!;)data-lazy_fix=
                |background\\s*:\\s*url\\(
                |image\\s*:\\s*url\\(
                |'SRC':
            ))
            (\"|')
            ((?:))
            ([^?'\"]+\\.)
            (".$extension_regex.")
            (|\\?\\d+|\\?v=\\d+)
            (\\2)
        /x";

        $content = preg_replace_callback($regex, [self::class, 'ReplaceFilter'], $content);
    }

    public static function ReplaceFilter($match)
    {
        return $match[1].$match[2].$match[3].self::CDN_HOST.$match[4].$match[5].$match[6].$match[7];
    }

    public static function getHost()
    {
        return self::CDN_HOST;
    }
}