<?php
add_action( 'admin_menu', 'bbconnect_register_work_queues_menu_page' );
function bbconnect_register_work_queues_menu_page() {
    add_submenu_page( 'users.php', 'Work Queues', 'Work Queues', 'list_users', 'work_queues_submenu', 'bbconnect_work_queues_page');
}

function bbconnect_work_queues_page() {
    bbconnect_admin_scripts(); // Enqueue CRM styles
    echo '<div class="wrap">'."\n";
    $selected_queue = isset($_GET['queue_id']) ? $_GET['queue_id'] : null;
    $queue_name = '';

    $available_types = array();
    $all_notes = bbconnect_workqueues_get_action_items();
    foreach ($all_notes as $note) {
        $note_types = wp_get_post_terms($note->ID, 'bb_note_type');
        foreach ($note_types as $note_type) {
            if ($note_type->parent > 0) {
                $available_types[$note_type->term_id]['name'] = $note_type->name;
                $available_types[$note_type->term_id]['notes'][] = $note;
                break;
            }
        }
    }
    echo '<div class="options-row">'."\n";
    echo '<h2>Work Queues <a class="add-new-h2" href="/edit-tags.php?taxonomy=bb_note_type&post_type=bb_note">Add New</a></h2>'."\n";
    echo '<select name="queue_id" id="queue_id">'."\n";
    echo '<option value="">Please Select</option>'."\n";
    foreach ($available_types as $term_id => $details) {
        if ($selected_queue == $term_id) {
            $queue_name = $details['name'];
        }
        echo '<option value="'.$term_id.'" '.selected($selected_queue, $term_id).'>'.$details['name'].'</option>'."\n";
    }
    echo '</select>'."\n";

    if (!empty($selected_queue)) {
        $contacts = array();

        $note_ids = array();
        foreach ($available_types[$selected_queue]['notes'] as $note) {
            $note_user = get_userdata($note->post_author);
            $contacts[$note->post_author] = $note_user;
            $groups[$note_user->display_name.' ('.$note_user->user_email.')'][] = $note;
            $note_ids[] = $note->ID;
        }
        echo '</div>'."\n";
        bbconnect_workqueues_output_action_items($groups);
    }
    echo '</div>'."\n";

?>
<script type="text/javascript">
jQuery('#queue_id').on('change', function() {
    window.location.href = '/wp-admin/users.php?page=work_queues_submenu&queue_id='+jQuery(this).val();
});
</script>
<?php
}