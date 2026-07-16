<?php


declare(strict_types=1);

/**
 * 📂 THEME DECOMPOSITION (v11.0 Gold Standard)
 * Logic is separated into specific files in the inc/ directory for better maintainability.
 */

// 1. Theme Setup (Supports, Menus, Image Sizes)
require get_template_directory() . '/inc/setup.php';

// 2. Optimization (WebP, Emoji removal, LCP fixes)
require get_template_directory() . '/inc/optimization.php';

// 3. Performance (Preloads, LCP, Analytics)
require get_template_directory() . '/inc/performance.php';

// 4. Infrastructure (Scripts, Styles, Helpers)
require get_template_directory() . '/inc/enqueue.php';

// 5. Critical CSS (Unified Critical & Dynamic Variables)
require get_template_directory() . '/inc/critical-css.php';

// 6. Customizer (Settings and Dynamic Style Injection)
require get_template_directory() . '/inc/customizer.php';

/**
 * REST API: SEO Metadata support
 */
add_action('init', function() {
    $meta_fields = [
        '_yoast_wpseo_title',
        '_yoast_wpseo_metadesc',
        '_yoast_wpseo_focuskw'
    ];

    foreach ($meta_fields as $field) {
        register_meta('post', $field, [
            'show_in_rest' => true,
            'single'       => true,
            'type'         => 'string',
        ]);
    }
});

/**
 * Archive Title Cleaner
 */
add_filter('get_the_archive_title', function ($title) {
    if (is_category()) {
        $title = single_cat_title('', false);
    } elseif (is_tag()) {
        $title = single_tag_title('', false);
    } elseif (is_author()) {
        $title = get_the_author();
    } elseif (is_post_type_archive()) {
        $title = post_type_archive_title('', false);
    } elseif (is_tax()) {
        $title = single_term_title('', false);
    }
    return wp_strip_all_tags($title);
});

// Центрируем заголовок архива категории
add_action("wp_head", function() {
    echo "<style>.archive .entry-title, .archive .page-title, .category .entry-title, .category .page-title { text-align: center; } .table-container { border: none !important; } .related-item { border: 1px solid rgba(0,0,0,0.08); border-radius: 16px; padding: 16px; transition: box-shadow 0.2s, transform 0.2s; } .related-item:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.1); transform: translateY(-2px); } .related-item img { border-radius: 12px; }</style>";
});

// YouTube видео после оглавления (универсальный — работает по произвольному полю youtube_video_id)
add_filter('the_content', function($content) {
    if (is_single()) {
        $video_id = get_post_meta(get_the_ID(), 'youtube_video_id', true);
        if ($video_id) {
            $marker = 'article-content-flow';
            $pos = strpos($content, $marker);
            if ($pos !== false) {
                $tag_start = strrpos(substr($content, 0, $pos), '<div');
                $embed = '<lite-youtube videoid="' . esc_attr($video_id) . '" style="background-image:url(https://i.ytimg.com/vi/' . esc_attr($video_id) . '/hqdefault.jpg);max-width:800px;margin:30px auto;border-radius:20px"><button class="lty-playbtn" title="Play"></button></lite-youtube>';
                $content = substr($content, 0, $tag_start) . $embed . substr($content, $tag_start);
            }
        }
    }
    return $content;
});

// CSS и JS для lite-youtube-embed (фасад YouTube — не грузит iframe до клика)
add_action('wp_footer', function() {
    if (is_single() && get_post_meta(get_the_ID(), 'youtube_video_id', true)) {
        echo '<style>lite-youtube{background-color:#000;position:relative;display:block;contain:content;background-position:center center;background-size:cover;cursor:pointer;max-width:800px;margin:30px auto;border-radius:20px;aspect-ratio:16/9;overflow:hidden}lite-youtube::after{content:"";display:block;padding-bottom:56.25%}lite-youtube>iframe{width:100%;height:100%;position:absolute;top:0;left:0;border:0;border-radius:20px}lite-youtube>.lty-playbtn{display:block;width:68px;height:48px;position:absolute;cursor:pointer;transform:translate3d(-50%,-50%,0);top:50%;left:50%;z-index:1;background:none;border:0;background-image:url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 68 48%27%3E%3Cpath d=%27M66.5 7.7c-.8-2.9-2.5-5.4-5.4-6.2C55.8.1 34 0 34 0S12.2.1 6.9 1.6c-3 .7-4.6 3.2-5.4 6.1a45 45 0 00-1.5 16c0 5.5.6 11 1.6 16.3.7 2.9 2.5 5.4 5.3 6.2C12.2 47.9 34 48 34 48s21.8-.1 27.1-1.6c2.8-.7 4.6-3.3 5.4-6.1C68 35 68 24 68 24s0-11.4-1.5-16.3z%27 fill=%27red%27/%3E%3Cpath d=%27M45 24L27 14v20%27 fill=%27white%27/%3E%3C/svg%3E");filter:grayscale(100%);transition:filter .1s}lite-youtube:hover>.lty-playbtn{filter:none}lite-youtube.lyt-activated>.lty-playbtn{display:none}</style>';
        echo '<script>class LiteYTEmbed extends HTMLElement{connectedCallback(){var s=this;s.addEventListener("pointerover",LiteYTEmbed.warmConnections,{once:true});s.addEventListener("click",function(){s.addIframe();},{once:true});if("ontouchstart"in window){s.addEventListener("touchend",function(){s.addIframe();},{once:true});}}static warmConnections(){if(LiteYTEmbed.preconnected)return;var o=document.createElement("link");o.rel="preconnect";o.href="https://www.youtube-nocookie.com";document.head.append(o);LiteYTEmbed.preconnected=true}addIframe(){if(this.classList.contains("lyt-activated"))return;this.classList.add("lyt-activated");var btn=this.querySelector(".lty-playbtn");if(btn)btn.remove();var vid=this.getAttribute("videoid");var touch="ontouchstart"in window;var isSafari=/^((?!chrome|android).)*safari/i.test(navigator.userAgent);var i=document.createElement("iframe");i.width="560";i.height="315";i.allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture";i.allowFullscreen=true;if(touch){i.src="https://www.youtube.com/embed/"+vid+"?rel=0&playsinline=1";}else if(isSafari){i.src="https://www.youtube.com/embed/"+vid+"?autoplay=1&rel=0";}else{i.src="https://www.youtube-nocookie.com/embed/"+vid+"?autoplay=1&rel=0";}this.append(i);}}customElements.define("lite-youtube",LiteYTEmbed);</script>';
    }
});
// SEO v142: noindex на страницах пагинации (кроме 1-й) — чтобы /page/2/+ не дублировали главную
add_filter('wpseo_robots', function($robots) {
    if (is_paged()) {
        return 'noindex, follow';
    }
    return $robots;
});
