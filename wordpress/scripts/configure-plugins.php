<?php
/**
 * Настройка плагинов после установки.
 * Запускается ВНУТРИ контейнера WP.
 *
 * ENV:
 *   YANDEX_METRIKA_ID — ID счётчика Метрики (может быть пустым)
 */

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require '/var/www/html/wp-load.php';

$ym_id = getenv('YANDEX_METRIKA_ID') ?: '';

echo "=== plugin configuration ===\n";

// ---------- True Lazy Analytics ----------
update_option('tlap_add_analytics_option_main', [
    'tlap_timer_delay' => '3000',
]);

if ($ym_id !== '') {
    update_option('tlap_add_analytics_option_metrica', [
        'tlap_yametrika_id'        => $ym_id,
        'tlap_yametrika_webvisor'  => 1,
        'tlap_yametrika_cdn'       => 1,
        'tlap_yametrika_ecommerce' => 'dataLayer',
    ]);
    echo "✓ True Lazy Analytics: Метрика ID $ym_id\n";
} else {
    echo "– True Lazy Analytics: Метрика не задана (YANDEX_METRIKA_ID пуст)\n";
}

// ---------- Yoast SEO ----------
$wpseo = get_option('wpseo') ?: [];
$wpseo = array_merge($wpseo, [
    'tracking'                             => 0,
    'toggled_tracking'                     => 1,
    'dismiss_configuration_workout_notice' => 1,
    'should_redirect_after_install_free'   => false,
    'activation_redirect_timestamp_free'   => time(),
]);
update_option('wpseo', $wpseo);
echo "✓ Yoast SEO: tracking off, onboarding dismissed\n";

// ---------- WP Fastest Cache ----------
// Базовая конфигурация кэша — включить HTML cache + gzip
update_option('WpFastestCache', json_encode([
    'wpFastestCacheStatus'       => 'on',
    'wpFastestCacheNewPost'      => 'on',
    'wpFastestCacheUpdatePost'   => 'on',
    'wpFastestCacheGzip'         => 'on',
    'wpFastestCacheBrowserCache' => 'on',
]));
echo "✓ WP Fastest Cache: включён (HTML + gzip + browser cache)\n";

echo "=== done ===\n";
