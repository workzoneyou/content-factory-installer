<?php
/**
 * Template part for the header navigation
 */
?>
<nav class="header-navigation" role="navigation">
    <?php
    wp_nav_menu([
        'theme_location' => 'primary',
        'menu_class'     => 'header-menu',
        'container'      => false,
        'fallback_cb'    => '__return_false',
    ]);
    ?>
</nav>
