<?php
/**
 * Assets Enqueue and Helper Functions
 *
 * @package Firsanov
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue non-critical styles.
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('firsanov-blog-style', get_stylesheet_uri(), [], '103.0');
    wp_enqueue_style('firsanov-custom-styles', get_template_directory_uri() . '/firsanov-wp.css', [], '141.0');
});

/**
 * Async load CSS handles (v100.0 - Consolidated)
 */
add_filter('style_loader_tag', function($html, $handle, $href) {
    if (is_admin()) {
        return $html;
    }
    
    // List of handles to SKIP async loading (avoid FOUC for critical bits)
    $exclude_handles = ['firsanov-ultimate'];

    if (in_array($handle, $exclude_handles)) {
        return $html;
    }

    // Consolidated async loading pattern
    return "<link rel='stylesheet' id='{$handle}-css' href='{$href}' media='print' onload=\"this.media='all'; this.onload=null;\">";
}, 10, 3);

/**
 * Defer all JS (v105.0)
 */
add_filter('script_loader_tag', function ($tag, $handle) {
    if (is_admin()) {
        return $tag;
    }
    // Defer everything
    return str_replace(' src', ' defer src', $tag);
}, 10, 2);

/**
 * Get related posts by categories.
 *
 * @param int $post_id Current post ID.
 * @param int $count Number of posts to retrieve.
 * @return WP_Post[] Array of post objects.
 */
/**
 * Get related posts by categories with caching.
 *
 * @param int $post_id Current post ID.
 * @param int $count Number of posts to retrieve.
 * @return WP_Post[] Array of post objects.
 */
function firsanov_get_related_posts(int $post_id, int $count = 3): array {
    $transient_key = 'firsanov_related_' . $post_id . '_' . $count;
    $related_ids = get_transient($transient_key);

    if (false === $related_ids) {
        $categories = wp_get_post_categories($post_id);
        
        $args = [
            'post__not_in'   => [$post_id],
            'posts_per_page' => $count,
            'fields'         => 'ids', // Only get IDs for the transient
            'no_found_rows'  => true,   // Optimization: don't count total rows
        ];

        if (!empty($categories)) {
            $args['category__in'] = $categories;
        }

        $related_ids = get_posts($args);
        
        // Cache for 12 hours
        set_transient($transient_key, $related_ids, 12 * HOUR_IN_SECONDS);
    }

    if (empty($related_ids)) {
        return [];
    }

    // Convert IDs back to post objects (this is fast if they are in the object cache)
    return get_posts([
        'post__in'            => $related_ids,
        'posts_per_page'      => $count,
        'post_type'           => 'post',
        'ignore_sticky_posts' => true,
    ]);
}
