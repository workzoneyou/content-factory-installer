<?php
/**
 * Template part for the header search form
 */
?>
<div class="header-search-wrapper">
    <form role="search" method="get" class="header-search-form" action="<?php echo esc_url(home_url('/')); ?>">
        <div class="search-input-group">
            <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="search" class="search-field" placeholder="Поиск по статьям..." value="<?php echo get_search_query(); ?>" name="s" />
        </div>
    </form>
</div>
