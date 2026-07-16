<?php
get_header();
?>
<?php if (have_posts()) : ?>
  <?php while (have_posts()) : the_post(); ?>
    <article <?php post_class('single-post'); ?>>
      <div class="post-meta">
        <time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>">
          <?php echo esc_html(get_the_date()); ?>
        </time>
      </div>
      <h1 class="post-title"><?php the_title(); ?></h1>
      
      <?php if (has_post_thumbnail()) : ?>
        <div class="post-hero">
          <?php the_post_thumbnail('full', [
              'class' => 'single-thumb',
              'loading' => 'eager', 
              'fetchpriority' => 'high', 
              'decoding' => 'async',
              'sizes' => '(max-width: 480px) 100vw, (max-width: 767px) 100vw, 800px'
          ]); ?>
        </div>
      <?php endif; ?>

      <div class="post-content">
        <?php the_content(); ?>
      </div>

      <!-- Блок Читайте также -->
      <?php
      $related_posts = firsanov_get_related_posts(get_the_ID());
      if ($related_posts) : ?>
        <section class="related-posts">
          <h3 class="related-title">Читайте также</h3>
          <div class="related-grid">
            <?php foreach ($related_posts as $post) : setup_postdata($post); ?>
              <a href="<?php the_permalink(); ?>" class="related-item">
                <div class="related-thumb-wrapper">
                  <?php if (has_post_thumbnail()) : ?>
                    <?php the_post_thumbnail('firsanov-thumb', [
                      'class' => 'related-thumb', 
                      'style' => 'width: 100%; height: 100%; object-fit: cover;',
                      'width' => '500',
                      'height' => '281'
                    ]); ?>
                  <?php else : ?>
                    <div class="related-thumb-placeholder" style="width: 100%; height: 100%; background: #e2e8f0;"></div>
                  <?php endif; ?>
                </div>
                <h4 class="related-item-title" style="font-size: 1rem; font-weight: 700; line-height: 1.4; color: #1e293b; margin: 0;"><?php the_title(); ?></h4>
              </a>
            <?php endforeach; wp_reset_postdata(); ?>
          </div>
        </section>
      <?php endif; ?>

      <a class="back-link" href="<?php echo esc_url(home_url('/')); ?>">← Назад к списку</a>
    </article>

    <?php
    $video_id = get_post_meta(get_the_ID(), 'youtube_video_id', true);
    if ($video_id) : ?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "VideoObject",
      "name": "<?php echo esc_attr(get_the_title()); ?>",
      "thumbnailUrl": "https://img.youtube.com/vi/<?php echo esc_attr($video_id); ?>/maxresdefault.jpg",
      "uploadDate": "<?php echo get_the_date('Y-m-d'); ?>",
      "contentUrl": "https://www.youtube.com/watch?v=<?php echo esc_attr($video_id); ?>",
      "embedUrl": "https://www.youtube.com/embed/<?php echo esc_attr($video_id); ?>"
    }
    </script>
    <?php endif; ?>
  <?php endwhile; ?>
<?php else : ?>
  <div class="empty-state">
    Запись не найдена.
  </div>
<?php endif; ?>
<?php
get_footer();
