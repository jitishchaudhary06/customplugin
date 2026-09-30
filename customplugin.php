<?php
/**
 * Plugin Name: Custom Post Type
 * Description: WordPress ki normal post jaisa custom post type. Editor, categories, tags aur frontend permalink WordPress ke apne tables mein save hote hain.
 * Version: 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CPT_REWRITE_VERSION', '1.1.0');

register_activation_hook(__FILE__, 'cpt_activate');
register_deactivation_hook(__FILE__, 'cpt_deactivate');

function cpt_post_type() {
    return 'custom_post';
}

function cpt_taxonomies() {
    return array('cpt_category', 'cpt_tag');
}

function cpt_register_post_type() {
    if (post_type_exists(cpt_post_type())) {
        return;
    }

    register_post_type(cpt_post_type(), array(
        'labels' => array(
            'name' => 'Custom Posts',
            'singular_name' => 'Custom Post',
            'menu_name' => 'Custom Post Type',
            'name_admin_bar' => 'Custom Post',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New Post',
            'new_item' => 'New Post',
            'edit_item' => 'Edit Post',
            'view_item' => 'View Post',
            'view_items' => 'View Posts',
            'all_items' => 'All Posts',
            'search_items' => 'Search Posts',
            'not_found' => 'No posts found.',
            'not_found_in_trash' => 'No posts found in Trash.',
            'featured_image' => 'Featured image',
            'set_featured_image' => 'Set featured image',
            'remove_featured_image' => 'Remove featured image',
            'use_featured_image' => 'Use as featured image',
            'archives' => 'Custom Post Archives',
            'insert_into_item' => 'Insert into post',
            'uploaded_to_this_item' => 'Uploaded to this post',
            'filter_items_list' => 'Filter posts list',
            'items_list_navigation' => 'Posts list navigation',
            'items_list' => 'Posts list',
            'item_published' => 'Post published.',
            'item_updated' => 'Post updated.',
        ),
        'description' => 'Custom posts stored like normal WordPress posts.',
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_admin_bar' => true,
        'show_in_rest' => true,
        'menu_position' => 6,
        'menu_icon' => 'dashicons-admin-post',
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'hierarchical' => false,
        'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions'),
        'taxonomies' => cpt_taxonomies(),
        'has_archive' => true,
        'rewrite' => array('slug' => 'custom-post', 'with_front' => true),
        'query_var' => true,
        'can_export' => true,
        'delete_with_user' => false,
    ));
}

function cpt_register_taxonomies() {
    if (!taxonomy_exists('cpt_category')) {
        register_taxonomy('cpt_category', cpt_post_type(), array(
            'labels' => array(
                'name' => 'Categories',
                'singular_name' => 'Category',
                'search_items' => 'Search Categories',
                'all_items' => 'All Categories',
                'parent_item' => 'Parent Category',
                'parent_item_colon' => 'Parent Category:',
                'edit_item' => 'Edit Category',
                'update_item' => 'Update Category',
                'add_new_item' => 'Add New Category',
                'new_item_name' => 'New Category Name',
                'menu_name' => 'Categories',
            ),
            'public' => true,
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'show_in_nav_menus' => true,
            'rewrite' => array('slug' => 'custom-post-category', 'with_front' => true),
            'query_var' => true,
        ));
    }

    if (!taxonomy_exists('cpt_tag')) {
        register_taxonomy('cpt_tag', cpt_post_type(), array(
            'labels' => array(
                'name' => 'Tags',
                'singular_name' => 'Tag',
                'search_items' => 'Search Tags',
                'popular_items' => 'Popular Tags',
                'all_items' => 'All Tags',
                'edit_item' => 'Edit Tag',
                'update_item' => 'Update Tag',
                'add_new_item' => 'Add New Tag',
                'new_item_name' => 'New Tag Name',
                'separate_items_with_commas' => 'Separate tags with commas',
                'add_or_remove_items' => 'Add or remove tags',
                'choose_from_most_used' => 'Choose from the most used tags',
                'menu_name' => 'Tags',
            ),
            'public' => true,
            'hierarchical' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'show_in_nav_menus' => true,
            'rewrite' => array('slug' => 'custom-post-tag', 'with_front' => true),
            'query_var' => true,
        ));
    }
}

function cpt_register_types() {
    cpt_register_taxonomies();
    cpt_register_post_type();
}

add_action('init', 'cpt_register_types');

function cpt_activate() {
    cpt_register_types();

    if (!post_type_exists(cpt_post_type()) || !taxonomy_exists('cpt_category') || !taxonomy_exists('cpt_tag')) {
        wp_die(esc_html('Activation failed: post type ya taxonomies register nahi ho payi.'));
    }

    flush_rewrite_rules();
    update_option('cpt_rewrite_version', CPT_REWRITE_VERSION);
}

function cpt_deactivate() {
    try {
        cpt_drop_legacy_tables();
    } catch (Exception $e) {
        error_log('Custom Post Type deactivate: ' . $e->getMessage());
    }

    unregister_post_type(cpt_post_type());
    unregister_taxonomy('cpt_category');
    unregister_taxonomy('cpt_tag');
    flush_rewrite_rules();
}

add_action('admin_init', 'cpt_flush_rewrites_once');

function cpt_flush_rewrites_once() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (get_option('cpt_rewrite_version') === CPT_REWRITE_VERSION) {
        return;
    }

    flush_rewrite_rules(false);
    update_option('cpt_rewrite_version', CPT_REWRITE_VERSION);
}

add_action('after_setup_theme', 'cpt_enable_thumbnails', 11);

function cpt_enable_thumbnails() {
    if (!current_theme_supports('post-thumbnails')) {
        add_theme_support('post-thumbnails', array(cpt_post_type()));
    }
}

function cpt_valid_table($table) {
    return is_string($table) && preg_match('/^[A-Za-z0-9_]+$/', $table) === 1;
}

function cpt_drop_legacy_tables() {
    global $wpdb;

    $tables = array($wpdb->prefix . 'cpt_posts', $wpdb->prefix . 'cp_entries');

    foreach ($tables as $table) {
        if (!cpt_valid_table($table)) {
            throw new Exception('Invalid table name.');
        }

        $wpdb->last_error = '';
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }
    }
}

function cpt_delete_all_content() {
    cpt_register_types();

    $ids = get_posts(array(
        'post_type' => cpt_post_type(),
        'post_status' => get_post_stati(),
        'posts_per_page' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
    ));

    if (!is_array($ids)) {
        throw new Exception('Posts load nahi ho paye.');
    }

    foreach ($ids as $id) {
        $deleted = wp_delete_post((int) $id, true);
        if (!$deleted) {
            throw new Exception('Post delete nahi ho paya.');
        }
    }

    foreach (cpt_taxonomies() as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            throw new Exception('Taxonomy nahi mili.');
        }

        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ));

        if (is_wp_error($terms)) {
            throw new Exception($terms->get_error_message());
        }

        foreach ($terms as $term) {
            $removed = wp_delete_term((int) $term->term_id, $taxonomy);
            if (is_wp_error($removed)) {
                throw new Exception($removed->get_error_message());
            }
        }
    }

    delete_option('cpt_rewrite_version');
}

add_shortcode('custom_posts', 'cpt_posts_shortcode');

function cpt_posts_shortcode() {
    if (!post_type_exists(cpt_post_type())) {
        return '';
    }

    $query = new WP_Query(array(
        'post_type' => cpt_post_type(),
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    ));

    if (!$query->have_posts()) {
        return '<p>Koi post nahi hai.</p>';
    }

    $html = '<div class="cpt-posts">';

    while ($query->have_posts()) {
        $query->the_post();
        $permalink = get_permalink();
        $html .= '<article class="cpt-post">';

        if (has_post_thumbnail()) {
            $html .= '<a href="' . esc_url($permalink) . '">' . get_the_post_thumbnail(get_the_ID(), 'medium') . '</a>';
        }

        $html .= '<h3><a href="' . esc_url($permalink) . '">' . esc_html(get_the_title()) . '</a></h3>';
        $html .= '<div>' . wp_kses_post(get_the_excerpt()) . '</div>';
        $html .= '</article>';
    }

    wp_reset_postdata();
    $html .= '</div>';

    return $html;
}
