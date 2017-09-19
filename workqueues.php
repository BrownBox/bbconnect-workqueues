<?php
/**
 * Plugin Name: Connexions Action Notes & Work Queues
 * Plugin URI: http://connexionscrm.com/
 * Description: Keep your CRM informed and up to date with online and offline interactions with every contact.
 * Version: 0.1
 * Author: Brown Box
 * Author URI: http://brownbox.net.au
 * License: Proprietary Brown Box
 */
define('BBCONNECT_WORKQUEUES_VERSION', '0.1');
define('BBCONNECT_WORKQUEUES_DIR', plugin_dir_path(__FILE__));
define('BBCONNECT_WORKQUEUES_URL', plugin_dir_url(__FILE__));

require_once(BBCONNECT_WORKQUEUES_DIR.'db.php');
require_once(BBCONNECT_WORKQUEUES_DIR.'fx.php');
require_once(BBCONNECT_WORKQUEUES_DIR.'user.php');
require_once(BBCONNECT_WORKQUEUES_DIR.'queues.php');
require_once(BBCONNECT_WORKQUEUES_DIR.'rules.php');
require_once(BBCONNECT_WORKQUEUES_DIR.'dashboard.php');
require_once(BBCONNECT_WORKQUEUES_DIR.'forms.php');

function bbconnect_workqueues_init() {
    if (!defined('BBCONNECT_VER')) {
        add_action('admin_init', 'bbconnect_workqueues_deactivate');
        add_action('admin_notices', 'bbconnect_workqueues_deactivate_notice');
    }
    if (is_admin()) {
        // DB updates
        bbconnect_workqueues_updates();
        // Plugin updates
        new BbConnectUpdates(__FILE__, 'BrownBox', 'bbconnect-workqueues');
    }
}
add_action('plugins_loaded', 'bbconnect_workqueues_init');

function bbconnect_workqueues_deactivate() {
    deactivate_plugins(plugin_basename(__FILE__));
}

function bbconnect_workqueues_deactivate_notice() {
    echo '<div class="updated"><p><strong>Connexions Action Notes & Work Queues</strong> has been <strong>deactivated</strong> as it requires Connexions.</p></div>';
    if (isset($_GET['activate'])) {
        unset($_GET['activate']);
    }
}
