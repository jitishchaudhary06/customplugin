<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

try {
    global $wpdb;

    $tables = array($wpdb->prefix . 'cpt_posts', $wpdb->prefix . 'cp_entries');

    foreach ($tables as $table) {
        if (!is_string($table) || preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            throw new Exception('Invalid table name.');
        }

        $wpdb->last_error = '';
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }
    }
} catch (Exception $e) {
    error_log('Custom Post Type uninstall: ' . $e->getMessage());
}
