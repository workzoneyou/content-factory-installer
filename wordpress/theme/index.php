<?php
get_header();
?>

<div class="blog-header">
  <h1 class="page-title"><?php echo esc_html(get_theme_mod('blog_title', get_bloginfo('name'))); ?></h1>
  <p class="page-description"><?php echo esc_html(get_theme_mod('blog_description', get_bloginfo('description'))); ?></p>
</div>

<div class="category-filters-container">
  <div class="category-filters">
    <a href="<?php echo esc_url(home_url('/')); ?>" class="category-pill <?php echo (is_home() || is_front_page()) ? 'active' : ''; ?>">Все статьи</a>
    <?php
    $categories = get_categories([
        'orderby' => 'name',
        'parent'  => 0,
        'hide_empty' => 1
    ]);
    foreach ($categories as $cat) :
        $active_class = (is_category($cat->term_id)) ? 'active' : '';
        echo '<a href="' . esc_url(get_category_link($cat->term_id)) . '" class="category-pill ' . $active_class . '">' . esc_html($cat->name) . '</a>';
    endforeach;
    ?>
  </div>
</div>

<div class="posts-grid-wrapper">
  <?php if (have_posts()) : ?>
    <section class="posts" itemscope itemtype="https://schema.org/Blog">
      <?php while (have_posts()) : the_post(); ?>
        <article <?php post_class('post-card'); ?> itemscope itemtype="https://schema.org/BlogPosting">
          <?php if (has_post_thumbnail()) : ?>
            <div class="post-thumb" itemprop="image" itemscope itemtype="https://schema.org/ImageObject">
              <a href="<?php the_permalink(); ?>">
                <?php 
                global $wp_query;
                $attr = [];
                if ($wp_query->current_post === 0) {
                    $attr['loading'] = 'eager';
                    $attr['fetchpriority'] = 'high';
                    $attr['decoding'] = 'async';
                    $attr['class'] = 'lcp-image';
                }
                the_post_thumbnail('firsanov-grid', $attr); 
                ?>
              </a>
            </div>
          <?php endif; ?>

          <div class="post-body">
            <?php 
            $categories = get_the_category();
            if (!empty($categories)) : ?>
              <span class="post-category"><?php echo esc_html($categories[0]->name); ?></span>
            <?php endif; ?>

            <h2 class="post-title" itemprop="headline">
              <a href="<?php the_permalink(); ?>">
                <?php the_title(); ?>
              </a>
            </h2>

            <?php if (has_excerpt()) : ?>
              <div class="post-excerpt" itemprop="description">
                <?php echo wp_trim_words(get_the_excerpt(), 25); ?>
              </div>
            <?php endif; ?>

            <a href="<?php the_permalink(); ?>" class="btn-card">Читать полностью</a>
          </div>
        </article>
      <?php endwhile; ?>
    </section>

    <div class="pagination">
      <?php
      the_posts_pagination([
          'mid_size' => 1,
          'prev_text' => '← Назад',
          'next_text' => 'Вперёд →',
      ]);
      ?>
    </div>
  <?php else : ?>
    <div class="empty-state">
      <p>Записей пока нет. Скоро здесь появятся интересные материалы!</p>
    </div>
  <?php endif; ?>
</div>

<?php
get_footer();
