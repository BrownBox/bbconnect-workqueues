<?php
add_action( 'admin_menu', 'bbconnect_register_workqueues_menu_page' );
function bbconnect_register_workqueues_menu_page() {
    add_submenu_page('users.php', 'Work Queues', 'Work Queues', 'list_users', 'work_queues_submenu', 'bbconnect_workqueues_page');
}

function bbconnect_workqueues_page() {
    bbconnect_admin_scripts(); // Enqueue CRM styles
    echo '<div class="wrap">'."\n";
    $selected_queue = isset($_GET['queue']) ? urldecode($_GET['queue']) : null;
    $selected_form = isset($_GET['form_id']) ? $_GET['form_id'] : null;

    $queues = bbconnect_workqueues_get_queues();

    echo '<h2>Work Queues</h2>'."\n";
    if (count($queues) > 0) {
        echo '<div class="options-row">'."\n";
        echo '<select name="queue" id="queue">'."\n";
        echo '<option value="">Please Select</option>'."\n";
        foreach ($queues as $queue_name => $forms) {
            foreach ($forms as $form_id => $tasks) {
                $form = GFAPI::get_form($form_id);
                echo '<option value="'.urlencode($queue_name).'" data-form-id="'.$form_id.'" '.selected($selected_queue.'#'.$selected_form, $queue_name.'#'.$form_id).'>'.$queue_name.' ('.$form['title'].')</option>'."\n";
                if ($selected_queue == $queue_name && $selected_form == $form_id) {
                    $selected_tasks = $tasks;
                }
            }
        }
        echo '</select>'."\n";
        echo '</div>'."\n";

        if (!empty($selected_tasks)) {
            foreach ($selected_tasks as $task) {
                $task_user = get_userdata($task['created_by']);
                $groups[$task_user->display_name.' ('.$task_user->user_email.')'][] = $task;
            }
            bbconnect_workqueues_output_action_items($groups);
        }
    } else {
        echo '<p>No outstanding action items found!</p>';
    }
    echo '</div>'."\n";
?>
<script type="text/javascript">
jQuery('#queue').on('change', function() {
    window.location.href = '/wp-admin/users.php?page=work_queues_submenu&queue='+jQuery(this).val()+'&form_id='+jQuery(this).find(":selected").data('form-id');
});
</script>
<?php
}
