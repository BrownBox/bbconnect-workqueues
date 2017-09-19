<?php
function bbconnect_workqueues_get_queues() {
    $todos = bbconnect_workqueues_get_todos();
    $queues = array();
    foreach ($todos as $todo) {
        $queues[$todo['work_queue']][$todo['form_id']][] = $todo;
    }
    ksort($queues);
    return $queues;
}

function bbconnect_workqueues_get_queue($queue_name, $form_id) {
    $queues = bbconnect_workqueues_get_queues();
    return $queues[$queue_name][$form_id];
}

function bbconnect_workqueues_get_queue_list() {
    $queues = bbconnect_workqueues_get_queues();
    return array_keys($queues);
}

function bbconnect_workqueues_get_todos($user_id = null, $work_queue = null, $form_id = 0) {
    $search_criteria = array(
            'field_filters' => array(
                    array(
                            'key' => 'action_status',
                            'value' => 'todo',
                    ),
            ),
    );
    if (!empty($user_id)) {
        $search_criteria['field_filters'][] = array(
                'key' => 'created_by',
                'value' => $user_id,
        );
    }
    if (!empty($work_queue)) {
        $search_criteria['field_filters'][] = array(
                'key' => 'work_queue',
                'value' => $work_queue,
        );
    }
    $offset = 0;
    $page_size = 100;
    $entries = array();
    do {
        $paging = array('offset' => $offset, 'page_size' => $page_size);
        $entries += GFAPI::get_entries($form_id, $search_criteria, null, $paging, $total_count);
        $offset += $page_size;
    } while ($offset < $total_count);
    return $entries;
}

function bbconnect_workqueues_insert_action_note($user_id, $title, $description, $work_queue = '', $action_required = false) {
    $action_form_id = bbconnect_get_action_form();
    $user = new WP_User($user_id);
    $_POST = array(); // Hack to allow multiple form submissions via API in single process
    $entry = array(
            'input_18' => $user->user_email,
            'input_1' => 'note',
            'input_5' => $work_queue,
            'input_7' => $title,
            'input_8' => $description,
            'action_status' => $action_required ? 'todo' : 'todone',
    );
    GFAPI::submit_form($action_form_id, $entry);
}

function bbconnect_workqueues_output_action_items($groups) {
    echo '<div class="column_holder actions-history-holder">'."\n";
    // First some custom styles
?>
<style type="text/css">
.column_holder {clear: both;}
h1 {clear: both; font-size: 1.1rem; line-height: 2rem; margin-bottom: 0; padding-bottom: 0.2rem; vertical-align: top;}
h1 a.button.action {float: right;}
table.action_items {width: 100%;}
table.action_items th {text-align: left; width: 30%;}
#comments {min-width: 65%; width: 65%;}
</style>
<?php
    add_thickbox(); // Make sure modal library is loaded
    foreach ($groups as $heading => $tasks) {
        $task_ids = array();
        $modal_id = 'a'.preg_replace('/[^a-z0-9]/', '', strtolower($heading));
        $url = ( empty( $_GET['user_id'] ) ) ? '/wp-admin/users.php?page=bbconnect_edit_user&user_id='.$tasks[0]['created_by'].'&tab=work_queues' : '/wp-admin/users.php?page=work_queues_submenu&queue='.urlencode($tasks[0]['work_queue']).'&form_id='.$tasks[0]['form_id'];
        echo '<h1><a href="'.$url.'">'.$heading.'</a> <a href="#TB_inline?width=600&height=550&inlineId='.$modal_id.'" class="thickbox button action" style="float: right;">Act</a></h1>'."\n";
        echo '<table class="wp-list-table widefat striped action_items">'."\n";
        echo '     <thead>'."\n";
        echo '        <tr>'."\n";
        echo '            <th style="" class="manage-column column-title" id="title" scope="col">Created</th>'."\n";
        echo '            <th style="" class="manage-column column-comments" id="comments" scope="col">Details</th>'."\n";
        echo '        </tr>'."\n";
        echo '    </thead>'."\n";
        echo '    <tbody id="the-list">'."\n";
        foreach ($tasks as $task) {
            if (is_array($task)) {
                $task_ids[] = $task['id'];
                echo '<tr class="type-page status-publish hentry iedit author-other level-0" id="note-'.$task['id'].'">'."\n";
                echo '  <td class="post-title page-title column-title">'.$task['date_created'].'</td>'."\n";
                echo '  <td class="">'.$task['action_description'].'</td>'."\n";
                echo '</tr>'."\n";
            }
        }
        echo '    </tbody>'."\n";
        echo '</table>'."\n";

        // Now the modal
        $function_name = $modal_id.'_action_submit';
?>
        <div id="<?php echo $modal_id; ?>" style="display: none;">
            <div>
                <h2><?php echo $heading; ?>: Actioning <?php echo count($tasks); ?> item(s)</h2>
                <form action="" method="post">
                    <div class="modal-row"><label for="note_content">Comments:</label><textarea id="<?php echo $modal_id ?>_comments" name="<?php echo $modal_id ?>_comments" rows="10"></textarea></div>
                    <input type="submit" class="button action" onclick="return <?php echo $function_name; ?>();">
                </form>
            </div>
        </div>
        <script type="text/javascript">
            function <?php echo $function_name; ?>() {
                var data = {
                        'action': 'close_action_notes',
                        'comments': jQuery('textarea[name=<?php echo $modal_id; ?>_comments]').val(),
                        'tasks': '<?php echo implode(',', $task_ids); ?>'
                };
            	jQuery.post(ajaxurl, data, function(response) {
        			if (response == 0) {
                        tb_remove();
                        window.location.reload();
        			} else {
            			alert(response);
        			}
        		});
                return false;
            }
        </script>
<?php
    }
    echo '</div>'."\n";
}

add_action('wp_ajax_close_action_notes', 'bbconnect_workqueues_close_action_notes');
function bbconnect_workqueues_close_action_notes() {
    // Get post data
    $comments = $_POST['comments'];
    $task_ids = explode(',', $_POST['tasks']);

    if (empty($comments) || empty($task_ids)) {
        echo 'Invalid data. Please try again.';
        return;
    }

    $current_user = wp_get_current_user();

    // Loop through notes
    foreach ($task_ids as $task_id) {
        $entry = GFAPI::get_entry($task_id);
        $entry['action_status'] = 'todone';
        GFAPI::update_entry($entry);

        GFFormsModel::add_note($task_id, $current_user->ID, $current_user->display_name, 'Actioned on '.date('Y-m-d').' with the following comment:'."\n\n".$comments);

        // @todo Track activity
//         $post_content = $comments."\n\n".'Closed action "'.$note->post_title.'" from '.$note->post_date;
    }
    return true;
}

function bbconnect_helper_work_queue() {
    $work_queues = array();
    $queues = bbconnect_workqueues_get_queues();
    foreach ($queues as $queue_name => $forms) {
        foreach ($forms as $form_id => $tasks) {
            $form = GFAPI::get_form($form_id);
            $work_queues[$form_id.':'.$queue_name] = $queue_name.' ('.$form['title'].')';
        }
    }
    return $work_queues;
}

add_action('bbconnect_update_user', 'bbconnect_workqueues_check_address', 10, 3);
function bbconnect_workqueues_check_address($user, $args, $other) {
    extract($args);

    if (empty($country) && !empty($other['country'])) {
        $country = $other['country'];
    }

    // Compare address details to those on record
    $dirty = false;
    $note_content = 'Submitted address details were different to those on file - please review'."\n\n";
    $fields = array(
            'title' => 'title',
            'address1' => 'bbconnect_address_one_1',
            'address2' => 'bbconnect_address_two_1',
            'suburb' => 'bbconnect_address_city_1',
            'state' => 'bbconnect_address_state_1',
            'postcode' => 'bbconnect_address_postal_code_1',
    );

    foreach ($fields as $varname => $metaname) {
        if (isset($$varname)) {
            $val = get_user_meta($user->ID, $metaname, true);
            if (!bbconnect_address_compare($$varname, $val)) {
                $note_content .= ucfirst($varname).':'."\n";
                $note_content .= 'Old Value: '.$val."\n";
                $note_content .= 'New Value: '.$$varname."\n\n";
                $dirty = true;
            }
        }
    }

    // Phone and country are a bit special
    if (!empty($phone)) {
        $phone_data = get_user_meta($user->ID, 'telephone');
        foreach ($phone_data as $phone_number) {
            if ($phone_number['type'] == 'home') {
                if (!bbconnect_address_compare($phone, $phone_number['value'])) {
                    $note_content .= 'Phone:'."\n";
                    $note_content .= 'Old Value: '.$phone_number['value']."\n";
                    $note_content .= 'New Value: '.$phone."\n\n";
                    $dirty = true;
                }
            }
        }
    }

    if (!empty($country)) {
        $country = bbconnect_address_compare($country);
        $val = get_user_meta($user->ID, 'bbconnect_address_country_1', true);
        if (!bbconnect_address_compare($country, $val)) {
            $note_content .= 'Country:'."\n";
            $note_content .= 'Old Value: '.$val."\n";
            $note_content .= 'New Value: '.$country."\n\n";
            $dirty = true;
        }
    }

    if ($dirty) {
        bbconnect_workqueues_insert_action_note($user->ID, 'Address Review', $note_content, 'Address Review', true);
    }
}

add_action('bbconnect_create_user', 'bbconnect_workqueues_new_user');
function bbconnect_workqueues_new_user($user_id) {
    bbconnect_workqueues_insert_action_note($user_id, 'New Contact', 'New contact - please check and clean up data as needed', 'New Contact', true);
}

add_filter('bbconnect_meta_options_exclude', 'bbconnect_workqueues_meta_options_exclude', 10, 2);
function bbconnect_workqueues_meta_options_exclude($exclude, $value) {
    if ('bb_work_queue' == $value['meta_key']) {
        $exclude = true;
    }
    return $exclude;
}

add_filter('bbconnect_restricted_field', 'bbconnect_workqueues_restricted_field', 10, 3);
function bbconnect_workqueues_restricted_field($restricted_field, $meta_key, $field_type) {
    if ('bb_work_queue' == $meta_key) {
        $restricted_field = true;
    }
    return $restricted_field;
}

add_filter('bbconnect_restricted_choices', 'bbconnect_workqueues_restricted_choices', 10, 3);
function bbconnect_workqueues_restricted_choices($restricted_choices, $meta_key, $field_type) {
    if ('bb_work_queue' == $meta_key) {
        $restricted_choices = true;
    }
    return $restricted_choices;
}

add_filter('bbconnect_meta_multi_op', 'bbconnect_workqueues_meta_multi_op', 10, 2);
function bbconnect_workqueues_meta_multi_op($multi_op, $user_meta) {
    if ('bb_work_queue' == $user_meta['meta_key']) {
        $multi_op = true;
    }
    return $multi_op;
}

add_filter('bbconnect_field_value', 'bbconnect_workqueues_field_value', 10, 3);
function bbconnect_workqueues_field_value($value, $key, $current_member) {
    if ('bbconnect_bb_work_queue' == $key) {
        $todos = bbconnect_workqueues_get_todos($current_member->ID);
        foreach ($todos as $todo) {
            $queues[$todo['work_queue']] = $todo['work_queue'];
        }
        ksort($queues);
        $value = implode('; ', array_keys($queues));
    }
    return $value;
}

add_filter('bbconnect_report_quicklink_args', 'bbconnect_workqueues_report_quicklink_args', 10, 2);
function bbconnect_workqueues_report_quicklink_args($args, $member_search) {
    // @todo
    return $args;
}


add_filter('bbconnect_filter_process_wp_col', 'bbconnect_workqueues_filter_process_wp_col', 10, 3);
function bbconnect_workqueues_filter_process_wp_col($wp_col, $user_meta, $value) {
    if ('bb_work_queue' == $user_meta['meta_key']) {
        $wp_col = 'ID';
    }
    return $wp_col;
}


add_filter('bbconnect_filter_process_op', 'bbconnect_workqueues_filter_process_op', 10, 3);
function bbconnect_workqueues_filter_process_op($op, $user_meta, $value) {
    if ('bb_work_queue' == $user_meta['meta_key']) {
        if ('=' == $op && isset($value['query'])) {
            $op = 'IN';
        } else if ('!=' == $op && isset($value['query'])) {
            $op = 'NOT IN';
        }
    }
    return $op;
}

add_filter('bbconnect_filter_process_q_val', 'bbconnect_workqueues_filter_process_q_val', 10, 3);
function bbconnect_workqueues_filter_process_q_val($q_val, $user_meta, $subvalue) {
    if ('bb_work_queue' == $user_meta['meta_key']) {
        list($form_id, $work_queue) = explode(':', $subvalue);
        $todos = bbconnect_workqueues_get_todos(null, $work_queue, $form_id);
        $work_queue_users = array();
        foreach ($todos as $todo) {
            $work_queue_users[$todo['created_by']] = $todo['created_by'];
        }
        $q_val = '('.implode(',', $work_queue_users).')';
    }
    return $q_val;
}
