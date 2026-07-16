<?php
/**
 * Theme Setup and Basic Configuration
 *
 * @package Firsanov
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configure theme supports, menus, and image sizes.
 */
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    
    // КАСТОМНЫЕ РАЗМЕРЫ (Без жесткой обрезки для сохранения пропорций)
    add_image_size('firsanov-thumb', 500, 281, false); 
    add_image_size('firsanov-grid', 650, 450, false); 
    
    add_theme_support(
        'html5',
        [
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'style',
            'script',
        ]
    );

    // Регистрация областей меню
    register_nav_menus([
        'primary' => 'Основное меню (Primary Menu)',
    ]);
});

/**
 * AUTO-FIX: Создаем и назначаем меню, если оно отсутствует.
 */
add_action('init', function() {
    if (is_admin()) {
        return;
    }
    
    if (!has_nav_menu('primary')) {
        $menu_name = 'Main Menu';
        $menu_exists = wp_get_nav_menu_object($menu_name);
        
        if (!$menu_exists) {
            $menu_id = wp_create_nav_menu($menu_name);
        } else {
            $menu_id = $menu_exists->term_id;
        }
        
        $locations = get_theme_mod('nav_menu_locations');
        if (!is_array($locations)) $locations = [];
        $locations['primary'] = $menu_id;
        set_theme_mod('nav_menu_locations', $locations);
    }
});

/**
 * ГАРМОНИЯ СЕТКИ: Делаем количество постов кратным 3 (12 постов на странице).
 */
add_action('pre_get_posts', function ($query) {
    if (!is_admin() && $query->is_main_query()) {
        $query->set('posts_per_page', 12);
    }
});
