<?php
/**
 * Theme Customizer Settings
 *
 * @package Firsanov
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Customizer Sections, Settings, and Controls.
 */
add_action('customize_register', function ($wp_customize) {
    $wp_customize->add_section('firsanov_design', [
        'title'    => 'Настройки дизайна темы',
        'priority' => 30,
    ]);

    // Brand Color
    $wp_customize->add_setting('brand_color', ['default' => '#c7f560', 'transport' => 'postMessage']);
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'brand_color', [
        'label' => 'Цвет бренда', 'section' => 'firsanov_design',
    ]));

    // Corridor/Center Color
    $wp_customize->add_setting('bg_color', ['default' => '#ffffff', 'transport' => 'postMessage']);
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'bg_color', [
        'label' => 'Цвет фона (Коридор)', 'section' => 'firsanov_design',
    ]));

    // SEO Titles and Descriptions
    $wp_customize->add_setting('blog_title', ['default' => 'Как платить за бугром', 'transport' => 'refresh']);
    $wp_customize->add_control('blog_title', [
        'label' => 'Заголовок блога', 'section' => 'firsanov_design', 'type' => 'text',
    ]);

    $wp_customize->add_setting('blog_description', ['default' => 'Вопрос, который все гуглят', 'transport' => 'refresh']);
    $wp_customize->add_control('blog_description', [
        'label' => 'Описание блога', 'section' => 'firsanov_design', 'type' => 'textarea',
    ]);

    // Aspect Ratio Control
    $wp_customize->add_setting('global_img_aspect_ratio', ['default' => '3 / 2', 'transport' => 'refresh']);
    $wp_customize->add_control('global_img_aspect_ratio', [
        'label'   => 'Пропорции изображений',
        'section' => 'firsanov_design',
        'type'    => 'select',
        'choices' => [
            'none'   => 'Оригинал (без обрезки)',
            '3 / 2'  => '3:2 (Горизонтальный)',
            '4 / 3'  => '4:3 (Классика)',
            '1 / 1'  => '1:1 (Квадрат)',
            '16 / 9' => '16:9 (Кино)',
        ],
    ]);
});

/**
 * Inject singular flag for JS preview.
 */
add_action('wp_head', function() {
    if (is_customize_preview()) {
        $is_listing = is_home() || is_front_page() || is_archive() || is_search();
        $is_singular = (is_singular() || is_single() || is_page()) && !$is_listing;
        echo '<script>window.firsanov_is_singular = ' . ($is_singular ? 'true' : 'false') . ';</script>';
    }
});

/**
 * Live Preview Script.
 */
add_action("customize_preview_init", function() { 
    wp_enqueue_script( 
        "firsanov-customize-preview", 
        get_template_directory_uri() . "/js/customize-preview.js", 
        ["customize-preview", "jquery"], 
        time(), 
        true 
    ); 
});

/**
 * Aspect-Ratio Injection and CLS Fix using Customizer setting.
 */
add_filter('wp_get_attachment_image_attributes', function($attr, $attachment, $size) {
    $ratio_setting = get_theme_mod('global_img_aspect_ratio', 'none');
    $style_ratio = '';
    
    if ($ratio_setting === 'none' || $ratio_setting === '') {
        $meta = wp_get_attachment_metadata($attachment->ID);
        if (!empty($meta['width']) && !empty($meta['height'])) {
            $w = $meta['width']; $h = $meta['height'];
            $style_ratio = "$w / $h";
        }
    } else {
        $style_ratio = $ratio_setting;
    }

    if (!empty($style_ratio)) {
        $style = "aspect-ratio: $style_ratio;";
        $attr['style'] = isset($attr['style']) ? $attr['style'] . ' ' . $style : $style;

        if ($ratio_setting !== 'none' && $ratio_setting !== '') {
            $parts = explode('/', $style_ratio);
            if (count($parts) === 2) {
                $w_ratio = (float)trim($parts[0]);
                $h_ratio = (float)trim($parts[1]);
                $attr['width'] = 800;
                $attr['height'] = round(800 * ($h_ratio / $w_ratio));
            }
        }
    }
    return $attr;
}, 20, 3);
