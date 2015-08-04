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
    $args = array(
            'author' => $_GET['user_id'], // @todo does BBConnect have a nicer way of getting the ID of the user we're looking at?
    );
    $notes = bbconnect_workqueues_get_action_items($args);
    $groups = array();
    foreach ($notes as $note) {
        $note_types = wp_get_post_terms($note->ID, 'bb_note_type');
        foreach ($note_types as $note_type) {
            if ($note_type->parent > 0) {
                $groups[$note_type->name][] = $note;
                $groups[$note_type->name]['id'] = $note_type->term_id;
                break;
            }
        }
    }
    bbconnect_workqueues_output_action_items($groups);
}
