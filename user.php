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
    $tasks = bbconnect_workqueues_get_todos($user_id);
    $groups = array();
    foreach ($tasks as $task) {
        $form = GFAPI::get_form($task['form_id']);
        $groups[$task['work_queue'].' ('.$form['title'].')'][] = $task;
    }
    bbconnect_workqueues_output_action_items($groups);
}
