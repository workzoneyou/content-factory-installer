<?php
/**
 * Optimization and Performance Fixes
 *
 * @package Firsanov
 */

declare(strict_types=1);

// FORCE IMAGE QUALITY (v68.0 PageSpeed optimization)
add_filter('wp_editor_set_quality', function($quality) { return 65; });
add_filter('webp_quality', function($quality) { return 65; });
add_filter('avif_quality', function($quality) { return 50; });

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Remove Emojis and WordPress bloat from wp_head.
 */
add_action('init', function() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    
    // Disable Global Styles and SVG Filters
    remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
    remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
    
    add_filter('tiny_mce_plugins', function($plugins) {
        if (is_array($plugins)) { return array_diff($plugins, ['wpemoji']); }
        return [];
    });
}, 1);

/**
 * Disable Classic Theme Styles, WP Embed and Jquery Migrate.
 */
add_action('wp_enqueue_scripts', function() {
    wp_dequeue_style('classic-theme-styles');
    wp_deregister_script('wp-embed');
    
    // Disable jquery-migrate to reduce JS weight and reflow
    if (!is_admin() && isset($GLOBALS['wp_scripts']->registered['jquery'])) {
        $scripts = $GLOBALS['wp_scripts'];
        $scripts->registered['jquery']->deps = array_diff($scripts->registered['jquery']->deps, ['jquery-migrate']);
    }
}, 20);

/**
 * ASYNC LOADING logic moved to inc/enqueue.php to avoid duplication.
 */

/**
 * LCP FIX: Disable Lazy Load for the first 4 images (Logo, Hero, etc.).
 */
add_filter('wp_get_attachment_image_attributes', function($attr, $attachment, $size) {
    if (is_admin()) return $attr;
    static $counter = 0;
    $counter++;
    if ($counter <= 4 || (is_single() && strpos($attr['class'] ?? '', 'single-thumb') !== false)) {
        $attr['loading'] = 'eager';
        $attr['fetchpriority'] = 'high';
        $attr['decoding'] = 'async';
    }
    return $attr;
}, 10, 3);

/**
 * Disable lazy-loading for the first image in the article content.
 */
add_filter('wp_img_tag_add_loading_attr', function($value, $image, $context) {
    if (is_admin() || !is_single()) return $value;
    static $content_img_counter = 0;
    if ($context === 'the_content') {
        $content_img_counter++;
        if ($content_img_counter <= 1) {
            return false;
        }
    }
    return $value;
}, 10, 3);

/**
 * Force AVIF/WebP for Logo.
 */
add_filter('wp_get_attachment_image_attributes', function($attr, $attachment, $size) {
    if (is_admin()) return $attr;
    if (strpos($attr['class'] ?? '', 'custom-logo') !== false || strpos($attr['class'] ?? '', 'header-custom-logo') !== false) {
        $uploads_dir = wp_get_upload_dir();
        $base_url = $uploads_dir['baseurl'];
        $base_path = $uploads_dir['basedir'];

        $formats = ['avif', 'webp'];
        foreach ($formats as $fmt) {
            $new_url = str_ireplace('.png', '.' . $fmt, $attr['src']);
            $check_path = str_replace($base_url, $base_path, $new_url);
            if (file_exists($check_path)) {
                $attr['src'] = $new_url;
                if (isset($attr['srcset'])) {
                    $attr['srcset'] = str_ireplace('.png', '.' . $fmt, $attr['srcset']);
                }
                break;
            }
        }
    }
    return $attr;
}, 11, 3);

/**
 * CLS FIX: Ensure width/height for all avatars and images.
 */
add_filter('get_avatar', function($avatar, $id_or_email, $size, $default, $alt, $args) {
    if (strpos($avatar, 'width=') === false) {
        $avatar = str_replace('<img ', '<img width="80" height="80" decoding="async" loading="lazy" class="avatar avatar-80" ', $avatar);
    }
    if (strpos($avatar, 'alt=""') !== false || strpos($avatar, 'alt=\'\'') !== false) {
        $avatar = str_replace(['alt=""', "alt=''"], 'alt="' . esc_attr(is_numeric($id_or_email) ? get_the_author_meta('display_name', $id_or_email) : 'Автор') . '"', $avatar);
    }
    return $avatar;
}, 10, 6);

add_filter('wp_get_attachment_image_attributes', function($attr, $attachment, $size) {
    if (!isset($attr['width']) || !isset($attr['height'])) {
        $img_data = wp_get_attachment_image_src($attachment->ID, $size);
        if ($img_data) {
            $attr['width'] = $img_data[1];
            $attr['height'] = $img_data[2];
        }
    }
    return $attr;
}, 20, 3);

/**
 * AVIF & WebP Support and Auto-Generation.
 */
add_filter('upload_mimes', function($mimes) {
    $mimes['webp'] = 'image/webp';
    $mimes['avif'] = 'image/avif';
    return $mimes;
});

add_filter('wp_generate_attachment_metadata', function($metadata, $attachment_id) {
    $file = get_attached_file($attachment_id);
    if (!file_exists($file)) return $metadata;
    $info = pathinfo($file);
    $dirname = $info['dirname'];
    $extensions = ['jpg', 'jpeg', 'png'];
    if (in_array(strtolower($info['extension']), $extensions)) {
        $formats = [
            'avif' => 50,
            'webp' => 65
        ];

        foreach ($formats as $format => $quality) {
            $new_file = $dirname . '/' . $info['filename'] . '.' . $format;
            $editor = wp_get_image_editor($file);
            if (!is_wp_error($editor)) { 
                $editor->set_quality($quality);
                $editor->save($new_file, 'image/' . $format); 
            }

            if (!empty($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size_info) {
                    $size_file = $dirname . '/' . $size_info['file'];
                    if (file_exists($size_file)) {
                        $size_path = pathinfo($size_file);
                        $size_new = $dirname . '/' . $size_path['filename'] . '.' . $format;
                        $size_editor = wp_get_image_editor($size_file);
                        if (!is_wp_error($size_editor)) { 
                            $size_editor->set_quality($quality);
                            $size_editor->save($size_new, 'image/' . $format); 
                        }
                    }
                }
            }
        }
    }
    return $metadata;
}, 10, 2);

/**
 * Replace JPEG/PNG with AVIF/WebP in HTML (AVIF prioritizes).
 */
function firsanov_apply_modern_format_replacement($html) {
    if (is_admin()) return $html;
    return preg_replace_callback('/<img([^>]+)>/i', function($matches) {
        $img = $matches[0];
        $uploads_dir = wp_get_upload_dir();
        $base_url = $uploads_dir['baseurl'];
        $base_path = $uploads_dir['basedir'];
        
        $formats = ['avif', 'webp'];

        if (preg_match('/src="([^"]+)\.(jpg|jpeg|png)(\?.*)?"/i', $img, $src_matches)) {
            $url_without_ext = $src_matches[1];
            $ext = $src_matches[2];
            $query = $src_matches[3] ?? '';
            $url_old = $url_without_ext . '.' . $ext . $query;

            foreach ($formats as $fmt) {
                $url_new = $url_without_ext . '.' . $fmt . $query;
                $path_check = str_replace($base_url, $base_path, $url_without_ext . '.' . $fmt);
                if (file_exists($path_check)) {
                    $img = str_replace($url_old, $url_new, $img);
                    break;
                }
            }
        }

        if (preg_match('/srcset="([^"]+)"/i', $img, $srcset_matches)) {
            $old_srcset = $srcset_matches[1];
            $sources = explode(',', $old_srcset);
            $best_srcset = $old_srcset;
            
            foreach ($formats as $fmt) {
                $new_sources = [];
                $all_found = true;
                foreach ($sources as $source) {
                    $source = trim($source);
                    if (empty($source)) continue;
                    $parts = preg_split('/\s+/', $source);
                    $url = $parts[0];
                    $descriptor = isset($parts[1]) ? ' ' . $parts[1] : '';
                    
                    if (preg_match('/\.(jpg|jpeg|png)(\?.*)?$/i', $url, $url_parts)) {
                        $url_cand = preg_replace('/\.(jpg|jpeg|png)/i', '.' . $fmt, $url);
                        $path_cand = str_replace($base_url, $base_path, strtok($url_cand, '?'));
                        if (file_exists($path_cand)) {
                            $new_sources[] = $url_cand . $descriptor;
                        } else {
                            $all_found = false;
                            break;
                        }
                    } else {
                        $new_sources[] = $source;
                    }
                }
                
                if ($all_found && !empty($new_sources)) {
                    $best_srcset = implode(', ', $new_sources);
                    break; // Found all sources for the best format
                }
            }
            $img = str_replace($old_srcset, $best_srcset, $img);
        }

        // CLS DEEP FIX v108.0: Prevent sizes="auto" and force eager for hero
        $is_hero = strpos($img, 'single-thumb') !== false;
        
        if ($is_hero) {
            $img = preg_replace('/sizes="auto"/i', 'sizes="(max-width: 800px) 100vw, 800px"', $img);
            $img = preg_replace('/loading="lazy"/i', 'loading="eager"', $img);
            
            // Critical: Add fetchpriority if missing for hero
            if (strpos($img, 'fetchpriority=') === false) {
                $img = str_replace('<img ', '<img fetchpriority="high" ', $img);
            }
        }

        if (strpos($img, 'loading=') === false) { 
            $loading_type = $is_hero ? 'eager' : 'lazy';
            $img = str_replace('<img ', '<img loading="' . $loading_type . '" ', $img); 
        }
        if (strpos($img, 'decoding=') === false) { $img = str_replace('<img ', '<img decoding="async" ', $img); }
        
        return $img;
    }, $html);
}
add_filter('the_content', 'firsanov_apply_modern_format_replacement', 999);
add_filter('post_thumbnail_html', 'firsanov_apply_modern_format_replacement', 999);
add_filter('get_header_image_tag', 'firsanov_apply_modern_format_replacement', 999);


/**
 * CONTENT OPTIMIZATION: Fix header hierarchy and author layout.
 */
add_filter('the_content', function ($content) {
    // Author card title fix (H4 -> H3)
    $content = preg_replace('/<h4([^>]*)>Автор:/i', '<h3$1>Автор:', $content);
    $content = str_replace('</h4>', '</h3>', $content);
    
    // Author avatar wrapper
    $content = preg_replace_callback('/(<img[^>]*src="[^"]*gravatar\.com[^>]*>)/i', function($m) {
        $img = $m[1];
        if (strpos($img, 'class=') === false) { $img = str_replace('<img ', '<img class="avatar" ', $img); }
        elseif (strpos($img, 'class="') !== false && strpos($img, 'avatar') === false) { $img = str_replace('class="', 'class="avatar ', $img); }
        if (strpos($img, 'alt=') === false || strpos($img, 'alt=""') !== false) { $img = str_replace('<img ', '<img alt="' . esc_attr(get_the_author()) . ' - автор блога" ', $img); }
        return '<span class="author-avatar-wrapper">' . $img . '</span>';
    }, $content);
    return $content;
}, 998);
