<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once dirname(__FILE__) . '/customplugin.php';

try {
    cpt_delete_all_content();
    cpt_drop_legacy_tables();
} catch (Exception $e) {
    error_log('Custom Post Type uninstall: ' . $e->getMessage());
}
