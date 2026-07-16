<?php
/**
 * Template part for the header branding (Logo & Title)
 */
?>
<div class="header-branding">
    <a class="header-logo-link" href="<?php echo esc_url(home_url('/')); ?>">
        <?php 
        $logo_id = get_theme_mod('custom_logo') ?: get_option('site_logo');
        if ($logo_id) {
            $logo_attr = [
                'class' => 'header-custom-logo',
                'loading' => 'eager',
                'fetchpriority' => 'high',
                'width' => '64',
                'height' => '64',
                'decoding' => 'async'
            ];
            echo wp_get_attachment_image($logo_id, [128, 128], false, $logo_attr);
        } else {
            $site_icon = get_site_icon_url(128);
            if ($site_icon) {
                // Ensure WebP fallback logic
                $site_icon_webp = str_ireplace('.png', '.webp', $site_icon);
                echo '<img src="' . esc_url($site_icon_webp) . '" class="header-custom-logo" alt="logo" width="64" height="64" fetchpriority="high" loading="eager" decoding="async">';
            }
        }
        ?>
        <span class="header-site-title"><?php echo esc_html(get_bloginfo('name')); ?></span>
    </a>
</div>
