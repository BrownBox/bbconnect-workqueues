<?php
add_filter('gform_form_settings', 'bbconnect_workqueues_form_setting', 10, 2);
function bbconnect_workqueues_form_setting($settings, $form) {
    $work_queue_select = '<select name="work_queue">'."\n";
    $work_queue_select .= '<option value="" '.selected('', rgar($form, 'work_queue'), false).'>None</option>'."\n";
    $work_queues = get_terms('bb_note_type', array('hide_empty' => false));
    foreach ($work_queues as $work_queue) {
        if ($work_queue->parent > 0) {
            $work_queue_select .= '<option value="'.$work_queue->term_id.'" '.selected($work_queue->term_id, rgar($form, 'work_queue'), false).'>'.$work_queue->name.'</option>'."\n";
        }
    }
    $work_queue_select .= '</select>';

    $settings['Form Options']['work_queue'] = '
        <tr>
            <th><label for="work_queue">Work Queue</label></th>
            <td>'.$work_queue_select.'<br>Select which work queue (if any) contacts should be added to after completing this form. Note that the form must contain an email address in order to locate the contact record.</td>
        </tr>';

    return $settings;
}

// save your custom form setting
add_filter('gform_pre_form_settings_save', 'bbconnect_workqueues_save_form_setting');
function bbconnect_workqueues_save_form_setting($form) {
    $form['work_queue'] = rgpost('work_queue');
    return $form;
}

add_action('gform_after_submission', 'bbconnect_workqueues_add_to_work_queue', 10, 2);
function bbconnect_workqueues_add_to_work_queue($entry, $form) {
    if (!empty($form['work_queue'])) {
        $args = array();
        foreach ($form['fields'] as $field) {
            $inputs = $field['inputs'];
            switch ($field['type']) {
                case 'email':
                    if (!empty($entry[$field['id']])) {
                        $args['email'] = $entry[$field['id']];
                    }
                    break;
                case 'name':
                    if (!empty($entry[(string)$inputs[0]['id']])) {
                        $args['firstname'] = $entry[(string)$inputs[0]['id']];
                    }
                    if (!empty($entry[(string)$inputs[1]['id']])) {
                        $args['lastname'] = $entry[(string)$inputs[1]['id']];
                    }
                    break;
                case 'address':
                    if (!empty($entry[(string)$inputs[0]['id']])) {
                        $args['address1'] = $entry[(string)$inputs[0]['id']];
                    }
                    if (!empty($entry[(string)$inputs[1]['id']])) {
                        $args['address2'] = $entry[(string)$inputs[1]['id']];
                    }
                    if (!empty($entry[(string)$inputs[2]['id']])) {
                        $args['suburb'] = $entry[(string)$inputs[2]['id']];
                    }
                    if (!empty($entry[(string)$inputs[3]['id']])) {
                        $args['state'] = $entry[(string)$inputs[3]['id']];
                    }
                    if (!empty($entry[(string)$inputs[4]['id']])) {
                        $args['postcode'] = $entry[(string)$inputs[4]['id']];
                    }
                    if (!empty($entry[(string)$inputs[5]['id']])) {
                        $args['country'] = $entry[(string)$inputs[5]['id']];
                    }
                    break;
            }
            if (!empty($entry[$field['id']])) {
                switch ($field['uniquenameField']) {
                    case 'title':
                        $args['title'] = $entry[$field['id']];
                        break;
                    case 'firstname':
                        $args['firstname'] = $entry[$field['id']];
                        break;
                    case 'lastname':
                        $args['lastname'] = $entry[$field['id']];
                        break;
                    case 'address':
                        $args['address1'] = $entry[$field['id']];
                        break;
                    case 'address2':
                        $args['address2'] = $entry[$field['id']];
                        break;
                    case 'suburb':
                        $args['suburb'] = $entry[$field['id']];
                        break;
                    case 'state':
                        $args['state'] = $entry[$field['id']];
                        break;
                    case 'countries':
                        $args['country'] = $entry[$field['id']];
                        break;
                    case 'postcode':
                        $args['postcode'] = $entry[$field['id']];
                        break;
                }
                if($field['inputName'] == 'phone'){
                    $args['phone'] = $entry[$field['id']];
                }
            }
        }

        $user_id = bbconnect_get_user($args, null, $is_new_contact);
        if ($user_id) {
            $work_queue = get_term($form['work_queue'], 'bb_note_type');
            $title = 'E-Booklet requested';
            $action_required = false;
            if (!empty($args['country']) && ($args['country'] == 'AU' || $args['country'] == 'Australia')) {
                $title = 'Action Required';
                $action_required = true;
            }
            bbconnect_workqueues_insert_action_item($user_id, $title.' - '.$work_queue->name, 'Automatically added from form submission.', $form['work_queue'], '', $action_required);
        }
    }
}