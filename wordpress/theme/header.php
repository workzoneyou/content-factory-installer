<?php
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
  <header class="site-header">
    <div class="site-inner header-main-wrapper">
      
      <?php get_template_part('template-parts/header/branding'); ?>
      
      <?php get_template_part('template-parts/header/navigation'); ?>
      
      <?php get_template_part('template-parts/header/search'); ?>

    </div>
  </header>

  <main class="site-main">
    <div class="site-inner">
