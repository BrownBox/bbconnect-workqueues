<?php
/*
Plugin Name: BBConnect Work Queues
Description: Adds Work Queues functionality to BB Connect
Version: 0.0.1
Author: Brown Box
Author URI: http://brownbox.net.au
License: GPLv2
Copyright 2015 Brown Box
*/

define('BBCONNECT_WORKQUEUES_VERSION', '0.0.1');

function bbconnect_workqueues_init() {
    if (!defined('BBCONNECT_VER')) {
        add_action('admin_init', 'bbconnect_workqueues_deactivate');
        add_action('admin_notices', 'bbconnect_workqueues_deactivate_notice');
    }
}
add_action('plugins_loaded', 'bbconnect_workqueues_init');

function bbconnect_workqueues_deactivate() {
    deactivate_plugins(plugin_basename( __FILE__ ));
}

function bbconnect_workqueues_deactivate_notice() {
    echo '<div class="updated"><p><strong>BBConnect Work Queues</strong> has been <strong>deactivated</strong> as it requires BB Connect.</p></div>';
    if (isset( $_GET['activate']))
        unset( $_GET['activate']);
}

require_once('core.php');
require_once('user.php');
require_once('queues.php');
require_once('forms.php');
require_once('rules.php');
require_once('dashboard.php');

register_activation_hook( __FILE__, 'bbconnect_workqueues_activate' );
function bbconnect_workqueues_activate() {
    $db_version = get_option('bbconnect_workqueues_db_version');
    if (empty($db_version) || version_compare($db_version, BBCONNECT_WORKQUEUES_VERSION) < 0) {
        // Special Work Queue search field
    	$field = array();
    	$field_keys = array();

        $field[] = array('source' => 'bbconnect', 'meta_key' => 'bb_work_queue', 'tag' => '', 'name' => __( 'Work Queue', 'bbconnect' ), 'options' => array( 'admin' => false, 'user' => false, 'signup' => false, 'reports' => true, 'public' => false, 'req' => false, 'field_type' => 'select', 'choices' => 'bbconnect_helper_work_queue' ), 'help' => false, 'column' => 'section_account_information', 'section' => 'account_information');

    	foreach ( $field as $key => $value ) {
    		if ( false != get_option( 'bbconnect_'.$value['meta_key'] ) )
    			continue;

    		// SET A NAMED VALUE FOR THE BBCONNECT_USER_META ARRAY AND
    		$field_keys[] = $value['meta_key'];
    		// ADD THE OPTION
    		add_option( 'bbconnect_'.$value['meta_key'], $value );
    	}

    	if ( !empty( $field_keys ) ) {
    		$umo = get_option( '_bbconnect_user_meta' );
    		foreach ( $umo as $uk => $uv ) {
    			// COLUMNS
    			foreach ( $uv as $suk => $suv ) {
    				if ( 'bbconnect_account_information' == $suv ) {
    					$acct = get_option( $suv );
    					foreach ( $field_keys as $fk => $fv )
    						$acct['options']['choices'][] = $fv;
    					update_option( $suv, $acct );
    					$aok = true;
    				}
    			}
    		}
    		// IF NO JOY, PUT IT IN COLUMN 3
    		if ( !isset( $aok ) ) {
    			foreach ( $field_keys as $fk => $fv )
    				$umo['column_3'][] = 'bbconnect_'.$fv;

    			update_option( '_bbconnect_user_meta', $umo );
    		}
    	}
        update_option('bbconnect_workqueues_db_version', BBCONNECT_WORKQUEUES_VERSION);
    }
}