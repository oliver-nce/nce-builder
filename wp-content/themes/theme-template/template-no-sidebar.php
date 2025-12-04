<?php
/**
 * Template Name: Full Width No Sidebar
 * Description: A clean template with no sidebars, menus, or widgets - just the content.
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); ?><?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #fff;
        }
        .fullwidth-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 30px;
        }
        /* Hide any theme elements that might leak through */
        #secondary, .sidebar, .widget-area, aside.widget-area {
            display: none !important;
        }
    </style>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="fullwidth-content">
    <?php
    while (have_posts()) :
        the_post();
        the_content();
    endwhile;
    ?>
</div>

<?php wp_footer(); ?>
</body>
</html>

