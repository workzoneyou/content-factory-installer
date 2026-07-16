<?php
/**
 * Critical CSS and Dynamic Theme Variables
 *
 * @package Comandos_Blog
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unified Critical & Dynamic Variables Injection
 */
add_action('wp_head', function() {
    // 1. Data from Customizer
    $brand_color = get_theme_mod('brand_color', '#c7f560');
    $bg_color    = get_theme_mod('bg_color', '#ffffff');
    $aspect_ratio = get_theme_mod('global_img_aspect_ratio', 'none');
    $css_aspect_ratio = ($aspect_ratio === 'none') ? 'auto' : $aspect_ratio;

    // 2. Read Critical CSS File (v131.0 - Unified Cache)
    // We use critical-desktop.css for EVERYONE to ensure Nginx cache hits (TTFB optimization).
    // The file contains responsive @media queries.
    $critical_file = 'critical-desktop.css';
    $critical_css = '';
    $critical_css_file = get_template_directory() . '/' . $critical_file;
    if (file_exists($critical_css_file)) {
        $critical_css = file_get_contents($critical_css_file);
    }
    // Debug
    $critical_css .= '/* Loaded: ' . $critical_file . ' */';

    // 3. Smart Contrast Logic
    $hex = str_replace('#', '', $bg_color);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    $text_color = ($brightness > 128) ? '#000000' : '#ffffff';

    // Compute text color for use ON TOP of the primary/brand color
    $brand_hex = str_replace('#', '', $brand_color);
    $br = hexdec(substr($brand_hex, 0, 2));
    $bg_r = hexdec(substr($brand_hex, 2, 2));
    $bb = hexdec(substr($brand_hex, 4, 2));
    $brand_brightness = (($br * 299) + ($bg_r * 587) + ($bb * 114)) / 1000;
    $primary_text = ($brand_brightness > 128) ? '#000000' : '#ffffff';

    // 3.5. Layering Logic (v132.0 - Forced Mode Fix)
    // Listing mode must be active on home, front page, and archives
    $is_listing = is_home() || is_front_page() || is_archive() || is_search();
    
    if ($is_listing) {
        $body_bg_val = $bg_color; 
        $post_bg_val = '#ffffff'; 
        $view_mode = 'listing';
    } else {
        // Only then we consider singular views
        $body_bg_val = '#ffffff'; 
        $post_bg_val = $bg_color; 
        $view_mode = 'singular';
    }

    // 4. Output Unified block
    ?>
    <style id="comandos-v22-final">
        /* v22.0 View Mode: <?php echo $view_mode; ?> */
        :root {
            --primary: <?php echo esc_attr($brand_color); ?>;
            --white: #ffffff;
            --body-bg: <?php echo esc_attr($body_bg_val); ?>;
            --post-bg: <?php echo esc_attr($post_bg_val); ?>;
            --bg-color: <?php echo esc_attr($bg_color); ?>;
            --text-color: <?php echo esc_attr($text_color); ?>;
            --primary-text: <?php echo esc_attr($primary_text); ?>;
            --img-aspect-ratio: <?php echo esc_attr($css_aspect_ratio); ?>;
            --slate-100: #f1f5f9;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --slate-900: #0f172a;
            --slate-950: #020617;
            --primary-glow-light: color-mix(in srgb, var(--primary) 20%, transparent);
        }
        body { background: var(--body-bg) !important; }
        * { hyphens: none !important; -webkit-hyphens: none !important; word-break: normal !important; overflow-wrap: normal !important; }
        <?php echo $critical_css; ?>
        
        /* FORCED LCP OPTIMIZATION (v131.0) - Cache Safe */
        .single-thumb, .single-thumb img {
            width: 100% !important;
            height: auto !important;
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
            transition: none !important;
            will-change: transform;
        }
        
    </style>
<?php
}, -100);
