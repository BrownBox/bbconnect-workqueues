<?php
add_filter('bbconnect_user_tabs', 'bbconnect_work_queues_tab', 10, 1);
function bbconnect_work_queues_tab(array $tabs) {
    $tabs['work_queues'] = array(
		'title' => 'Work Queues',
		'subs' => false,
    );
    return $tabs;
}

add_action('bbconnect_admin_profile_work_queues', 'bbconnect_user_work_queues');
function bbconnect_user_work_queues() {
    global $user_id;
    $selected_queue = isset($_GET['queue']) ? urldecode($_GET['queue']) : null;
    $selected_form = isset($_GET['form_id']) ? $_GET['form_id'] : 0;
    bbconnect_workqueues_output_action_items(bbconnect_workqueues_get_todos($user_id, $selected_queue, $selected_form));
}
