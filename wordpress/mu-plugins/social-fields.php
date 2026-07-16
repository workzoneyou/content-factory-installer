<?php
/**
 * Plugin Name: Content Factory — доп. соцполя профиля
 * Description: Добавляет ВК, Telegram, RuTube, TenChat в профиль пользователя
 *              (Yoast даёт только FB/IG/YouTube/X — под русские сети полей нет).
 */
add_filter('user_contactmethods', function ($m) {
    $m['vk']            = 'ВКонтакте (URL)';
    $m['telegram']      = 'Telegram (URL)';
    $m['telegram_blog'] = 'Telegram-блог (URL)';
    $m['rutube']        = 'RuTube (URL)';
    $m['tenchat']       = 'TenChat (URL)';
    return $m;
});
