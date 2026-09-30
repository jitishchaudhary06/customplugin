<?php
/**
 * Plugin Name: Custom Post Type
 * Description: Custom posts ko alag table mein save karta hai. Admin menu se add karo aur shortcode [custom_posts] se frontend par dikhao.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

register_activation_hook(__FILE__, 'cpt_activate');
register_deactivation_hook(__FILE__, 'cpt_deactivate');

function cpt_table() {
    global $wpdb;
    return $wpdb->prefix . 'cpt_posts';
}

function cpt_valid_table($table) {
    return is_string($table) && preg_match('/^[A-Za-z0-9_]+$/', $table) === 1;
}

function cpt_table_exists() {
    global $wpdb;

    $table = cpt_table();
    if (!cpt_valid_table($table)) {
        return false;
    }

    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    return $found === $table;
}

function cpt_activate() {
    global $wpdb;

    try {
        $table = cpt_table();
        if (!cpt_valid_table($table)) {
            throw new Exception('Invalid table name.');
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(200) NOT NULL,
            content longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'publish',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) " . $wpdb->get_charset_collate() . ';';

        $wpdb->last_error = '';
        dbDelta($sql);

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }

        if (!cpt_table_exists()) {
            throw new Exception('Table create nahi ho payi.');
        }
    } catch (Exception $e) {
        wp_die(esc_html('Activation failed: ' . $e->getMessage()));
    }
}

function cpt_drop_table() {
    global $wpdb;

    $tables = array(cpt_table(), $wpdb->prefix . 'cp_entries');

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

function cpt_deactivate() {
    try {
        cpt_drop_table();
    } catch (Exception $e) {
        wp_die(esc_html('Deactivation failed: ' . $e->getMessage()));
    }
}

function cpt_admin_guard() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html('You are not allowed to access this page.'));
    }
}

function cpt_safe_error($e) {
    $known = array(
        'Database table nahi mili. Plugin activate karo.',
        'Database table nahi mili.',
        'Invalid post.',
        'Post nahi mila.',
        'Title aur content dono required hain.',
        'Title 200 characters se lamba nahi ho sakta.',
        'Status invalid hai.',
    );

    $message = $e->getMessage();
    if (in_array($message, $known, true)) {
        return $message;
    }

    error_log('Custom Post Type: ' . $message);
    return 'Kuch galat ho gaya. Dubara try karo.';
}

add_action('admin_menu', 'cpt_admin_menu');

function cpt_admin_menu() {
    add_menu_page('Custom Post Type', 'Custom Post Type', 'manage_options', 'cpt-posts', 'cpt_list_page', 'dashicons-admin-post', 26);
    add_submenu_page('cpt-posts', 'All Posts', 'All Posts', 'manage_options', 'cpt-posts', 'cpt_list_page');
    add_submenu_page('cpt-posts', 'Add New', 'Add New', 'manage_options', 'cpt-post-new', 'cpt_form_page');
}

function cpt_rows($status = '') {
    global $wpdb;

    if (!cpt_table_exists()) {
        throw new Exception('Database table nahi mili.');
    }

    $table = cpt_table();
    $wpdb->last_error = '';

    if ($status === '') {
        $rows = $wpdb->get_results("SELECT id, title, content, status, created_at FROM `{$table}` ORDER BY id DESC");
    } else {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, content, status, created_at FROM `{$table}` WHERE status = %s ORDER BY id DESC",
            $status
        ));
    }

    if ($wpdb->last_error) {
        throw new Exception($wpdb->last_error);
    }

    return is_array($rows) ? $rows : array();
}

function cpt_redirect_list($notice) {
    wp_safe_redirect(add_query_arg(
        array(
            'page' => 'cpt-posts',
            'cpt_notice' => $notice,
        ),
        admin_url('admin.php')
    ));
    exit;
}

function cpt_list_page() {
    cpt_admin_guard();

    $error = '';
    $rows = array();

    try {
        if (!cpt_table_exists()) {
            throw new Exception('Database table nahi mili. Plugin activate karo.');
        }

        if (isset($_POST['cpt_delete'])) {
            $id = absint($_POST['cpt_id'] ?? 0);
            check_admin_referer('cpt_delete_' . $id);

            if (!$id) {
                throw new Exception('Invalid post.');
            }

            global $wpdb;
            $wpdb->last_error = '';
            $deleted = $wpdb->delete(cpt_table(), array('id' => $id), array('%d'));

            if ($deleted === false || $wpdb->last_error) {
                throw new Exception($wpdb->last_error ? $wpdb->last_error : 'Delete nahi ho paya.');
            }

            if ($deleted === 0) {
                throw new Exception('Post nahi mila.');
            }

            cpt_redirect_list('deleted');
        }
    } catch (Exception $e) {
        $error = cpt_safe_error($e);
    }

    if (cpt_table_exists()) {
        try {
            $rows = cpt_rows();
        } catch (Exception $e) {
            if ($error === '') {
                $error = cpt_safe_error($e);
            }
        }
    }

    $notices = array(
        'saved' => 'Post save ho gaya.',
        'updated' => 'Post update ho gaya.',
        'deleted' => 'Post delete ho gaya.',
    );
    $notice_key = isset($_GET['cpt_notice']) ? sanitize_key(wp_unslash($_GET['cpt_notice'])) : '';

    if ($error) {
        echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
    } elseif (isset($notices[$notice_key])) {
        echo '<div class="notice notice-success"><p>' . esc_html($notices[$notice_key]) . '</p></div>';
    }

    if (!cpt_table_exists()) {
        return;
    }
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Custom Post Type</h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=cpt-post-new')); ?>" class="page-title-action">Add New</a>
        <hr class="wp-header-end">
        <p>Frontend par dikhane ke liye page mein ye shortcode lagao: <code>[custom_posts]</code></p>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows) : ?>
                    <tr><td colspan="4">Koi post nahi hai.</td></tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=cpt-post-new&edit=' . absint($row->id))); ?>">
                                    <?php echo esc_html($row->title); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($row->status); ?></td>
                            <td><?php echo esc_html(mysql2date('Y-m-d H:i', $row->created_at)); ?></td>
                            <td>
                                <form method="post" style="display:inline" onsubmit="return confirm('Is post ko delete karna hai?');">
                                    <?php wp_nonce_field('cpt_delete_' . absint($row->id)); ?>
                                    <input type="hidden" name="cpt_id" value="<?php echo absint($row->id); ?>">
                                    <button type="submit" class="button-link-delete" name="cpt_delete" value="1">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function cpt_form_page() {
    cpt_admin_guard();

    $error = '';
    $edit_id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
    $form_id = $edit_id;
    $title = '';
    $content = '';
    $status = 'publish';

    try {
        if (!cpt_table_exists()) {
            throw new Exception('Database table nahi mili. Plugin activate karo.');
        }

        if (isset($_POST['cpt_save'])) {
            check_admin_referer('cpt_save');

            $form_id = absint($_POST['cpt_id'] ?? 0);
            $title = sanitize_text_field(wp_unslash($_POST['cpt_title'] ?? ''));
            $content = sanitize_textarea_field(wp_unslash($_POST['cpt_content'] ?? ''));
            $status = sanitize_key(wp_unslash($_POST['cpt_status'] ?? 'publish'));
            $title_length = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);

            if ($title === '' || $content === '') {
                throw new Exception('Title aur content dono required hain.');
            }

            if ($title_length > 200) {
                throw new Exception('Title 200 characters se lamba nahi ho sakta.');
            }

            if (!in_array($status, array('publish', 'draft'), true)) {
                throw new Exception('Status invalid hai.');
            }

            global $wpdb;
            $table = cpt_table();
            $wpdb->last_error = '';

            if ($form_id) {
                $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$table}` WHERE id = %d", $form_id));
                if (!$exists) {
                    throw new Exception('Post nahi mila.');
                }

                $saved = $wpdb->update(
                    $table,
                    array(
                        'title' => $title,
                        'content' => $content,
                        'status' => $status,
                    ),
                    array('id' => $form_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
                $done = 'updated';
            } else {
                $saved = $wpdb->insert(
                    $table,
                    array(
                        'title' => $title,
                        'content' => $content,
                        'status' => $status,
                        'created_at' => current_time('mysql'),
                    ),
                    array('%s', '%s', '%s', '%s')
                );
                $done = 'saved';
            }

            if ($saved === false || $wpdb->last_error) {
                throw new Exception($wpdb->last_error ? $wpdb->last_error : 'Save nahi ho paya.');
            }

            cpt_redirect_list($done);
        }

        if ($edit_id) {
            global $wpdb;
            $wpdb->last_error = '';
            $row = $wpdb->get_row($wpdb->prepare(
                'SELECT id, title, content, status FROM `' . cpt_table() . '` WHERE id = %d',
                $edit_id
            ));

            if ($wpdb->last_error) {
                throw new Exception($wpdb->last_error);
            }

            if (!$row) {
                throw new Exception('Post nahi mila.');
            }

            $form_id = (int) $row->id;
            $title = $row->title;
            $content = $row->content;
            $status = $row->status;
        }
    } catch (Exception $e) {
        $error = cpt_safe_error($e);
    }

    if ($error) {
        echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
    }

    if (!cpt_table_exists()) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo $form_id ? 'Edit Post' : 'Add New Post'; ?></h1>
        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=cpt-post-new')); ?>">
            <?php wp_nonce_field('cpt_save'); ?>
            <input type="hidden" name="cpt_id" value="<?php echo absint($form_id); ?>">
            <table class="form-table">
                <tr>
                    <th><label for="cpt_title">Title</label></th>
                    <td><input name="cpt_title" id="cpt_title" class="regular-text" value="<?php echo esc_attr($title); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="cpt_content">Content</label></th>
                    <td><textarea name="cpt_content" id="cpt_content" class="large-text" rows="6" required><?php echo esc_textarea($content); ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="cpt_status">Status</label></th>
                    <td>
                        <select name="cpt_status" id="cpt_status">
                            <option value="publish" <?php selected($status, 'publish'); ?>>Publish</option>
                            <option value="draft" <?php selected($status, 'draft'); ?>>Draft</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button($form_id ? 'Update Post' : 'Save Post', 'primary', 'cpt_save'); ?>
        </form>
    </div>
    <?php
}

add_shortcode('custom_posts', 'cpt_posts_shortcode');

function cpt_posts_shortcode() {
    try {
        $rows = cpt_rows('publish');

        if (!$rows) {
            return '<p>Koi post nahi hai.</p>';
        }

        $html = '<div class="cpt-posts">';
        foreach ($rows as $row) {
            $html .= '<article class="cpt-post">';
            $html .= '<h3>' . esc_html($row->title) . '</h3>';
            $html .= '<div>' . wpautop(esc_html($row->content)) . '</div>';
            $html .= '<small>' . esc_html(mysql2date('Y-m-d H:i', $row->created_at)) . '</small>';
            $html .= '</article>';
        }
        $html .= '</div>';

        return $html;
    } catch (Exception $e) {
        error_log('Custom Post Type shortcode: ' . $e->getMessage());
        return '';
    }
}
