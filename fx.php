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

function bbconnect_workqueues_get_todos($user_id = null, $work_queue = null, $form_id = 0, $sorting = null) {
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

    if (is_null($sorting)) {
        if (!empty($_GET['orderby'])) {
            $orderby = $_GET['orderby'];
            $order = 'desc';
        } else {
            $orderby = 'date_created';
            $order = 'asc';
        }
        if (!empty($_GET['order']) && in_array($_GET['order'], array('asc', 'desc'))) {
            $order = $_GET['order'];
        }

        $sorting = array(
                'key' => $orderby,
                'direction' => $order,
        );
    }

    $offset = 0;
    $page_size = 100;
    $entries = array();
    do {
        $paging = array('offset' => $offset, 'page_size' => $page_size);
        $entries += GFAPI::get_entries($form_id, $search_criteria, $sorting, $paging, $total_count);
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

function bbconnect_workqueues_get_sorting_args($field, $order = 'asc', &$current_sort) {
    $sorting = array(
            'orderby' => $field,
    );
    if ($order != 'desc') { // Sanity check
        $order = 'asc';
    }
    $current_sort = $order == 'asc' ? 'desc' : 'asc';
    if (!empty($_GET['order'])) {
        if ($_GET['orderby'] == $field && $_GET['order'] == $order) {
            $current_sort = $order;
            $order = $order == 'asc' ? 'desc' : 'asc';
        }
    }
    $sorting['order'] = $order;
    return $sorting;
}

function bbconnect_workqueues_output_action_items($tasks) {
    // First some custom styles
?>
<style type="text/css">
.tablenav a.button.dobulkaction {display: inline-block;}
table.action_items {width: 100%;}
table.action_items th {text-align: left; width: 30%;}
#comments {min-width: 65%; width: 65%;}
</style>
<?php
    add_thickbox(); // Make sure modal library is loaded
    $queues = bbconnect_workqueues_get_queues();
    $datetime_format = get_option('date_format').' '.get_option('time_format');
    $selected_queue = urldecode($_GET['queue']);
    $selected_form = $_GET['form_id'];
    $selected_user = $_GET['user']; // User filter, NOT user profile
    $orderby = 'date_created';
    $order = 'asc';
    $page_size = 50;
    $paged = max(1, (int)$_GET['paged']);
    $total_pages = ceil(count($tasks)/$page_size);
    if (!empty($_GET['orderby'])) {
        $orderby = $_GET['orderby'];
    }
    if (!empty($_GET['order']) && in_array($_GET['order'], array('asc', 'desc'))) {
        $order = $_GET['order'];
    }
    echo '<form id="posts-filter" method="get">'."\n";
    echo '    <input type="hidden" name="orderby" value="'.$orderby.'">'."\n";
    echo '    <input type="hidden" name="order" value="'.$order.'">'."\n";
    echo '    <input type="hidden" name="page" value="'.$_GET['page'].'">'."\n";
    if (!empty($_GET['user_id'])) {
        echo '    <input type="hidden" name="user_id" value="'.$_GET['user_id'].'">'."\n";
        echo '    <input type="hidden" name="tab" value="'.$_GET['tab'].'">'."\n";
    }
    echo '    <div class="tablenav top">'."\n";
    echo '        <div class="alignleft actions bulkactions">'."\n";
    echo '            <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>'."\n";
    echo '            <select name="action" id="bulk-action-selector-top">'."\n";
    echo '                <option value="-1">Bulk Actions</option>'."\n";
    echo '                <option value="action" class="hide-if-no-js">Action</option>'."\n";
    echo '            </select>'."\n";
    echo '            <input type="hidden" name="selected_tasks" id="selected_tasks" value="">'."\n";
    echo '            <a href="#TB_inline?width=600&height=550&inlineId=work_queue_action_modal" class="thickbox button action dobulkaction">Apply</a>'."\n";
    echo '        </div>'."\n";
    echo '        <div class="alignleft actions">'."\n";
    echo '            <label for="filter-by-work-queue" class="screen-reader-text">Filter by work queue</label>'."\n";
    echo '            <input type="hidden" id="form_id" name="form_id" value="'.$selected_form.'">'."\n";
    echo '            <select name="queue" id="queue">'."\n";
    echo '                <option value="" data-form-id="">All Work Queues</option>';
    $users = array();
    foreach ($queues as $queue_name => $forms) {
        foreach ($forms as $form_id => $queue_tasks) {
            $form = GFAPI::get_form($form_id);
            echo '                <option value="'.urlencode($queue_name).'" data-form-id="'.$form_id.'" '.selected($selected_queue.'#'.$selected_form, $queue_name.'#'.$form_id).'>'.$queue_name.' ('.$form['title'].')</option>'."\n";
            foreach ($queue_tasks as $queue_task) {
                if (!isset($users[$queue_task['created_by']])) {
                    $user = get_userdata($queue_task['created_by']);
                    $users[$queue_task['created_by']] = $user->display_name.' ('.$user->user_email.')';
                }
            }
        }
    }
    echo '            </select>';
    if (empty($_GET['user_id'])) {
        asort($users, SORT_FLAG_CASE | SORT_STRING);
        echo '            <select name="user" id="user">'."\n";
        echo '                <option value="">All Users</option>';
        foreach ($users as $user_id => $user_label) {
            echo '            <option value="'.$user_id.'" '.selected($selected_user, $user_id).'>'.$user_label.'</option>'."\n";
        }
        echo '            </select>'."\n";
    }
    echo '            <input name="filter_action" id="post-query-submit" class="button" value="Filter" type="submit">'."\n";
    echo '        </div>'."\n";
    echo '        <h2 class="screen-reader-text">Pages list navigation</h2>'."\n";
    echo '        <div class="tablenav-pages">'."\n";
    echo '            <span class="displaying-num">'.count($tasks).' items</span>'."\n";
    $big = 99999999;
    echo paginate_links(array(
            'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format' => '?paged=%#%',
            'current' => $paged,
            'total' => $total_pages,
            'before_page_number' => '<span class="screen-reader-text">Page </span>'
    ));
    echo '        </div>'."\n";
    echo '    </div>'."\n";

    $col_count = 0;
    ob_start();
    echo '            <tr>'."\n";
    $col_count++;
    echo '                <td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-1" type="checkbox"></td>'."\n";
    $url = add_query_arg(bbconnect_workqueues_get_sorting_args('work_queue', 'asc', $sort_class));
    if ($orderby == 'work_queue') {
        $sort_class .= ' sorted';
    }
    $col_count++;
    echo '                <th style="" class="manage-column column-title column-primary sortable '.$sort_class.' ui-sortable" id="title" scope="col">'."\n";
    echo '                    <a href="'.$url.'"><span>Work Queue</span><span class="sorting-indicator"></span></a>'."\n";
    echo '                </th>'."\n";
    if (empty($_GET['user_id'])) {
        $url = add_query_arg(bbconnect_workqueues_get_sorting_args('created_by', 'asc', $sort_class));
        if (!empty($_GET['orderby']) && $_GET['orderby'] == 'created_by') {
            $sort_class .= ' sorted';
        }
        $col_count++;
        echo '                <th style="" class="manage-column column-title sortable '.$sort_class.' ui-sortable" id="title" scope="col">'."\n";
        echo '                    <a href="'.$url.'"><span>User</span><span class="sorting-indicator"></span></a>'."\n";
        echo '                </th>'."\n";
    }
    $col_count++;
    echo '                <th style="" class="manage-column" id="comments" scope="col">Details</th>'."\n";
    $url = add_query_arg(bbconnect_workqueues_get_sorting_args('date_created', 'asc', $sort_class));
    if ($orderby == 'date_created') {
        $sort_class .= ' sorted';
    }
    $col_count++;
    echo '                <th style="" class="manage-column column-date sortable '.$sort_class.' ui-sortable" id="date" scope="col"><a href="'.$url.'"><span>Date</span><span class="sorting-indicator"></span></a></th>'."\n";
    echo '            </tr>'."\n";
    $table_headers = ob_get_clean();

    echo '    <table class="wp-list-table widefat fixed striped action_items">'."\n";
    echo '        <thead>'."\n";
    echo $table_headers;
    echo '        </thead>'."\n";
    echo '        <tbody id="the-list">'."\n";
    if (count($tasks) == 0) {
        echo '            <tr class="no-items">'."\n";
        echo '                <td class="colspanchange" colspan="'.$col_count.'">No items found</td>'."\n";
        echo '            </tr>'."\n";
    } else {
        $pages = array_chunk($tasks, $page_size);
        $users = $forms = array();
        foreach ($pages[$paged-1] as $task) {
            if (is_array($task)) {
                if (empty($_GET['user_id'])) {
                    $crossover_action_url = '/wp-admin/users.php?page=bbconnect_edit_user&user_id='.$task['created_by'].'&tab=work_queues';
                    $crossover_action_label = 'View User';
                } else {
                    $crossover_action_url = '/wp-admin/users.php?page=work_queues_submenu&queue='.urlencode($task['work_queue']).'&form_id='.$task['form_id'];
                    $crossover_action_label = 'View Work Queue';
                }
                $task_date = bbconnect_get_datetime($task['date_created'], bbconnect_get_timezone('UTC')); // We're assuming DB is configured to use UTC...
                $task_date->setTimezone(bbconnect_get_timezone()); // Convert to local timezone
                $task_ids[] = $task['id'];
                if (!isset($users[$task['created_by']])) {
                    $users[$task['created_by']] = get_userdata($task['created_by']);
                }
                if (!isset($forms[$task['form_id']])) {
                    $forms[$task['form_id']] = GFAPI::get_form($task['form_id']);
                }
                echo '            <tr class="type-page status-publish hentry iedit author-other level-0" id="note-'.$task['id'].'">'."\n";
                echo '                <th scope="row" class="check-column">';
                echo '                    <input id="cb-select-'.$task['id'].'" name="task[]" value="'.$task['id'].'" type="checkbox">';
                echo '                </th>'."\n";
                $queue_filter_url = add_query_arg(array('queue' => urlencode($task['work_queue']), 'form_id' => $task['form_id']));
                echo '                <td class="post-title has-row-actions page-title column-title"><strong><a href="'.$queue_filter_url.'">'.$task['work_queue'].' ('.$forms[$task['form_id']]['title'].')</a></strong>'."\n";
                echo '                    <div class="row-actions">'."\n";
                echo '                        <span class="view"><a href="'.$crossover_action_url.'" target="_blank">'.$crossover_action_label.'</a> | </span><span class="view"><a href="/wp-admin/admin.php?page=gf_entries&view=entry&id='.$task['form_id'].'&lid='.$task['id'].'" target="_blank">View Entry</a> | </span><span class="edit"><a href="#TB_inline?width=600&height=550&inlineId=work_queue_action_modal" class="workqueue_action_action thickbox action" data-task-id="'.$task['id'].'">Action</a></span>'."\n";
                echo '                    </div>'."\n";
                echo '                </td>'."\n";
                if (empty($_GET['user_id'])) {
                    echo '                <td class="post-title page-title column-title"><strong>'.$users[$task['created_by']]->display_name.'<br>'.$users[$task['created_by']]->user_email.'</strong></td>'."\n";
                }
                echo '                <td class="">'.$task['action_description'].'</td>'."\n";
                echo '                <td class="post-date page-date column-date">'.$task_date->format($datetime_format).'</td>'."\n";
                echo '            </tr>'."\n";
            }
        }
    }
    echo '        </tbody>'."\n";
    echo '        <tfoot>'."\n";
    echo $table_headers;
    echo '        </tfoot>'."\n";
    echo '    </table>'."\n";
    echo '</form>'."\n";

    // Now the modal
?>
    <div id="work_queue_action_modal" style="display: none;">
        <div>
            <h2>Actioning <span id="task_count"></span> item(s)</h2>
            <form action="" method="post">
                <div class="modal-row"><label for="note_content">Comments:</label><textarea id="<?php echo $modal_id ?>_comments" name="<?php echo $modal_id ?>_comments" rows="10"></textarea></div>
                <input type="submit" class="button action" onclick="return bbconnect_workqueues_action_submit();">
            </form>
        </div>
    </div>
    <script type="text/javascript">
        jQuery('form#posts-filter').on('submit', function() {
            jQuery('input#form_id').val(jQuery('select#queue').find(":selected").data('form-id'));
            return true;
        });
        jQuery('a.dobulkaction').on('click', function(e) {
            switch (jQuery('select#bulk-action-selector-top').find(":selected").val()) {
                case '-1':
                    e.preventDefault();
                    return false;
                    break;
                case 'action':
                    var tasks = '';
                    var task_count = 0;
                    jQuery('input[name="task[]"]').each(function() {
                        if (jQuery(this).prop('checked')) {
                            console.log(this);
                            if (tasks != '') {
                                tasks += ',';
                            }
                            tasks += jQuery(this).val();
                            task_count++;
                        }
                    });
                    if (task_count == 0) {
                        e.preventDefault();
                        return false;
                    }
                    jQuery('input#selected_tasks').val(tasks);
                    jQuery('span#task_count').html(task_count);
                    break;
            }
        });
        jQuery('a.workqueue_action_action').click(function() {
            jQuery('input#selected_tasks').val(jQuery(this).data('task-id'));
            jQuery('span#task_count').html('1');
        });
        function bbconnect_workqueues_action_submit() {
            var data = {
                    'action': 'close_action_notes',
                    'comments': jQuery('textarea[name=<?php echo $modal_id; ?>_comments]').val(),
                    'tasks': jQuery('input#selected_tasks').val()
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
