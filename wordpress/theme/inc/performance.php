<?php
/**
 * Performance and Preload Logic
 *
 * @package Firsanov
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper to get modern format URL (AVIF/WebP) from any image URL.
 */
function firsanov_get_modern_url(string $url, string $format = 'avif'): string {
    return str_ireplace(['.jpg', '.jpeg', '.png'], '.' . $format, $url);
}

/**
 * Main performance injection to wp_head.
 */
add_action('wp_head', function() {
    // 1. PRECONNECT & DNS-PREFETCH
    echo '<link rel="preconnect" href="' . esc_url(home_url()) . '" crossorigin>' . "\n";
    echo '<link rel="dns-prefetch" href="' . esc_url(home_url()) . '">' . "\n";

    // 2. LCP PRELOAD
    $lcp_img_id = null;
    $is_singular_lcp = false;

    if (is_singular() && has_post_thumbnail()) {
        $lcp_img_id = get_post_thumbnail_id();
        $is_singular_lcp = true;
    } elseif ((is_home() || is_archive()) && have_posts()) {
        $recent_posts = get_posts(['numberposts' => 1, 'fields' => 'ids']);
        if (!empty($recent_posts)) {
            $lcp_img_id = get_post_thumbnail_id($recent_posts[0]);
        }
    }

    if ($lcp_img_id) {
        $img_src = wp_get_attachment_image_url($lcp_img_id, 'full');
        $img_srcset = wp_get_attachment_image_srcset($lcp_img_id, 'full');

        if ($img_src) {
            $uploads_dir = wp_get_upload_dir();
            $base_url = $uploads_dir['baseurl'];
            $base_path = $uploads_dir['basedir'];

            $final_src = $img_src;
            $final_srcset = $img_srcset;

            $formats = ['avif', 'webp'];
            foreach ($formats as $fmt) {
                $cand_src = firsanov_get_modern_url($img_src, $fmt);
                $path_check = str_replace($base_url, $base_path, strtok($cand_src, '?'));
                if (file_exists($path_check)) {
                    $final_src = $cand_src;
                    if ($img_srcset) {
                        $final_srcset = firsanov_get_modern_url($img_srcset, $fmt);
                    }
                    break;
                }
            }

            echo '<link rel="preload" as="image" href="' . esc_url($final_src) . '" fetchpriority="high"';
            if ($final_srcset) {
                echo ' imagesrcset="' . esc_attr($final_srcset) . '"';
            }
            // Smart sizes for LCP
            if ($is_singular_lcp) {
                $manual_sizes = '(max-width: 480px) 100vw, (max-width: 1024px) 100vw, 1024px';
            } else {
                // Grid item sizes
                $manual_sizes = '(max-width: 767px) 100vw, (max-width: 1024px) 50vw, 400px';
            }
            echo ' imagesizes="' . esc_attr($manual_sizes) . '"';
            echo '>' . "\n";
        }
    }

    // 3. LOGO & FONT PRELOAD
    $logo_id = get_theme_mod('custom_logo') ?: get_option('site_logo');
    if ($logo_id) {
        $logo_src = wp_get_attachment_image_url($logo_id, [128, 128]);
        if ($logo_src) {
            $final_logo = $logo_src;
            $uploads_dir = wp_get_upload_dir();
            foreach (['avif', 'webp'] as $fmt) {
                $cand_logo = firsanov_get_modern_url($logo_src, $fmt);
                $path_check = str_replace($uploads_dir['baseurl'], $uploads_dir['basedir'], strtok($cand_logo, '?'));
                if (file_exists($path_check)) {
                    $final_logo = $cand_logo;
                    break;
                }
            }
            echo '<link rel="preload" as="image" href="' . esc_url($final_logo) . '" fetchpriority="high">' . "\n";
        }
    }

    // Preload Inter fonts (Standard for the theme)
    $fonts = [
        'inter-400-subset.woff2',
        'inter-700-subset.woff2',
        'inter-900-subset.woff2',
        'unbounded-900.woff2'
    ];
    foreach ($fonts as $font) {
        echo '<link rel="preload" href="' . esc_url(get_theme_file_uri('assets/fonts/' . $font)) . '" as="font" type="font/woff2" crossorigin>' . "\n";
    }
}, 1);
