<?php
add_action( 'admin_menu', 'bbconnect_register_workqueues_menu_page' );
function bbconnect_register_workqueues_menu_page() {
    add_submenu_page('users.php', 'Work Queues', 'Work Queues', 'list_users', 'work_queues_submenu', 'bbconnect_workqueues_page');
}

function bbconnect_workqueues_page() {
    bbconnect_admin_scripts(); // Enqueue CRM styles
    echo '<div class="wrap">'."\n";
    $selected_queue = isset($_GET['queue']) ? urldecode($_GET['queue']) : null;
    $selected_form = isset($_GET['form_id']) ? $_GET['form_id'] : 0;
    $selected_user = isset($_GET['user']) ? $_GET['user'] : null;

    echo '<h2>Work Queues</h2>'."\n";
    bbconnect_workqueues_output_action_items(bbconnect_workqueues_get_todos($selected_user, $selected_queue, $selected_form));
    echo '</div>'."\n";
}
