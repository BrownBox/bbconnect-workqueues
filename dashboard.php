<?php

/**
 * Add a widget to the dashboard.
 *
 * This function is hooked into the 'wp_dashboard_setup' action below.
 */
function bbconnect_workqueues_actions_add_dashboard_widgets() {
    wp_add_dashboard_widget('bbconnect_workqueues_actions_dashboard_widget', // Widget slug.
                            'Work Queues', // Title.
                            'bbconnect_workqueues_actions_dashboard_widget_function'); // Display function.
}
add_action('wp_dashboard_setup', 'bbconnect_workqueues_actions_add_dashboard_widgets');

/**
 * Create the function to output the contents of our Dashboard Widget.
 */
function bbconnect_workqueues_actions_dashboard_widget_function() {
    echo ' <table cellspacing="0" class="widefat gf_dashboard_view" style="border:0px;">' . "\n";
    echo '        <thead>' . "\n";
    echo '            <tr>' . "\n";
    echo '                <td class="gf_dashboard_form_title_header" style="font-style: italic; font-weight: bold; padding: 8px 18px!important; text-align: left">Work Queue</td>' . "\n";
    echo '                <td class="gf_dashboard_form_title_header" style="font-style: italic; font-weight: bold; padding: 8px 18px!important; text-align: left">Form</td>' . "\n";
    echo '                <td class="gf_dashboard_entries_unread_header" style="font-style: italic; font-weight: bold; padding: 8px 18px!important; text-align: center">Tasks</td>' . "\n";
    echo '            </tr>' . "\n";
    echo '        </thead>' . "\n";
    echo '        <tbody class="list:user user-list">' . "\n";

    $queues = bbconnect_workqueues_get_queues();
    foreach ($queues as $queue_name => $forms) {
        foreach ($forms as $form_id => $tasks) {
            echo bbconnect_workqueues_actions_dashboard_widget_row($queue_name, $form_id, count($tasks));
        }
    }

    echo '         </tbody>' . "\n";
    echo '     </table>' . "\n";
}

function bbconnect_workqueues_actions_dashboard_widget_row($name, $form_id, $count) {
    $form = GFAPI::get_form($form_id);
    $row = '';
    $row .= '  <tr class="author-self status-inherit" valign="top">'."\n";
    $row .= '      <td class="gf_dashboard_form_title column-title" style="padding:8px 18px;">'."\n";
    $row .= '          <a class="form_title_unread" href="users.php?page=work_queues_submenu&queue='.urlencode($name).'&form_id='.$form_id.'" style="font-weight:bold;" title="View All Tasks">'.$name.'</a>'."\n";
    $row .= '      </td>'."\n";
    $row .= '      <td class="gf_dashboard_form_title column-title" style="padding:8px 18px;">'."\n";
    $row .= '          <a class="form_title_unread" href="users.php?page=work_queues_submenu&queue='.urlencode($name).'&form_id='.$form_id.'" style="font-weight:bold;" title="View All Tasks">'.$form['title'].'</a>'."\n";
    $row .= '      </td>'."\n";
    $row .= '      <td class="gf_dashboard_entries_unread column-date" style="padding:8px 18px; text-align:center;">'."\n";
    $row .= '          <a class="form_entries_unread" href="users.php?page=work_queues_submenu&queue='.urlencode($name).'&form_id='.$form_id.'" style="font-weight:bold;" title="View All Tasks">'.$count.'</a>'."\n";
    $row .= '      </td>'."\n";
    $row .= '  </tr>'."\n";

    return $row;
}
