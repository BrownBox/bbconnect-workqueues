<?php
function bbconnect_workqueues_updates() {
    // Get current version
    $dbv = get_option('bbconnect_workqueues_db_version', 0);

    // If it's not the latest, run our updates
    if (version_compare($dbv, BBCONNECT_WORKQUEUES_VERSION, '<')) {
        // List of versions that involved a DB update - each one must have a corresponding function below
        $db_versions = array(
                '0.1',
        );

        foreach ($db_versions as $version) {
            if (version_compare($version, $dbv, '>')) {
                call_user_func('bbconnect_workqueues_db_update_'.str_replace('.', '_', $version));
                update_option('bbconnect_workqueues_db_version', $version);
            }
        }
        update_option('bbconnect_workqueues_db_version', BBCONNECT_WORKQUEUES_VERSION);
    }
}

function bbconnect_workqueues_db_update_0_1() {
    // Special Work Queue search field
    $field = array(
            array('source' => 'bbconnect', 'meta_key' => 'bb_work_queue', 'tag' => '', 'name' => __( 'Work Queue', 'bbconnect' ), 'options' => array( 'admin' => false, 'user' => false, 'signup' => false, 'reports' => true, 'public' => false, 'req' => false, 'field_type' => 'select', 'choices' => 'bbconnect_helper_work_queue' ), 'help' => false, 'column' => 'section_account_information', 'section' => 'account_information'),
    );
    $field_keys = array();

    foreach ($field as $key => $value) {
        if (false != get_option('bbconnect_'.$value['meta_key'])) {
            continue;
        }

        $field_keys[] = $value['meta_key'];
        add_option('bbconnect_'.$value['meta_key'], $value);
    }

    $umo = get_option('_bbconnect_user_meta');
    if (!empty($field_keys)) {
        foreach ($umo as $uk => $uv) {
            // Add to the account info section
            foreach ($uv as $suk => $suv) {
                if ('bbconnect_account_information' == $suv) {
                    $acct = get_option($suv);
                    foreach ($field_keys as $fk => $fv) {
                        $acct['options']['choices'][] = $fv;
                    }
                    update_option($suv, $acct);
                    $aok = true;
                }
            }
        }
        // If we couldn't find the account info section just add to column 3
        if (!isset($aok)) {
            foreach ($field_keys as $fk => $fv) {
                $umo['column_3'][] = 'bbconnect_' . $fv;
            }

            update_option('_bbconnect_user_meta', $umo);
        }
    }
}
