<?php
function bbconnect_workqueues_insert_action_item( $author_id, $title, $description, $note_type = array( 'type' => 'system', 'subtype' => 'miscellaneous' ), $receipt_number = '', $action_required = false) {
    $terms = bbconnect_workqueues_get_note_types($note_type);

    $post = array(
            'post_title'    => $title,
            'post_status'   => 'publish',
            'post_type'     => 'bb_note',
            'post_content'  => $description,
            'post_author'   => $author_id,
    );

    $post_id = wp_insert_post($post);
    wp_set_object_terms($post_id, array($terms['type']->term_id, $terms['subtype']->term_id), 'bb_note_type');

    if ($post_id) {
        if ($receipt_number) {
            add_post_meta($post_id, 'note_receipt_number', $receipt_number);
        }
        if ($action_required) {
            add_post_meta($post_id, '_bbc_action_required', 'true');
        }
    }

    return $post_id;
}

function bbconnect_workqueues_get_note_types($note_type) {
    if (!is_array($note_type)) {
        $note_type = array('subtype' => $note_type);
    }
    if (empty($note_type['subtype'])) {
        return false;
    }

    $terms = array();

    if (is_numeric($note_type['subtype'])) {
        $terms['subtype'] = get_term_by('id', $note_type['subtype'], 'bb_note_type');
    } else {
        $terms['subtype'] = get_term_by('slug', $note_type['subtype'], 'bb_note_type');
    }

    if (empty($note_type['type'])) {
        $terms['type'] = get_term_by('id', $terms['subtype']->parent, 'bb_note_type');
    } elseif (is_numeric($note_type['type'])) {
        $terms['type'] = get_term_by('id', $note_type['type'], 'bb_note_type');
    } else {
        $terms['type'] = get_term_by('slug', $note_type['type'], 'bb_note_type');
    }

    return $terms;
}

function bbconnect_workqueues_get_action_items(array $args = array()) {
	$defaults = array(
            'numberposts' => -1,
            'post_type' => 'bb_note',
	        'meta_query' => array(
	                array(
	                        'key' => '_bbc_action_required',
	                        'value' => 'true',
	                ),
	        ),
    );

	$args = wp_parse_args($args, $defaults);
    return get_posts($args);
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
    foreach ($groups as $heading => $items) {
        $note_ids = array();
        $modal_id = 'a'.preg_replace('/[^a-z0-9]/', '', strtolower($heading));
        $url = ( empty( $_GET['user_id'] ) ) ? '/wp-admin/users.php?page=bbconnect_edit_user&user_id='.$items[0]->post_author.'&tab=work_queues' : '/wp-admin/users.php?page=work_queues_submenu&queue_id='.$items['id'];
        echo '<h1><a href="'.$url.'" target="_newtab">'.$heading.'</a> <a href="#TB_inline?width=600&height=550&inlineId='.$modal_id.'" class="thickbox button action" style="float: right;">Act</a></h1>'."\n";

        echo '<table class="wp-list-table widefat action_items">'."\n";
        echo '     <thead>'."\n";
        echo '        <tr>'."\n";
        echo '            <th style="" class="manage-column column-title" id="title" scope="col">Title</th>'."\n";
        echo '            <th style="" class="manage-column column-comments" id="comments" scope="col">Comments</th>'."\n";
        echo '        </tr>'."\n";
        echo '    </thead>'."\n";
        echo '    <tbody id="the-list">'."\n";
        $zebra = 0;
        foreach ($items as $item) {
            $note_ids[] = $item->ID;
            $alternate = ( is_int( $zebra/2) ) ? 'alternate' : '';
            if( !empty( $item->post_title ) ) {
                echo '<tr class="type-page status-publish hentry iedit author-other level-0 '.$alternate.'" id="note-'.$item->ID.'">'."\n";
                echo '  <td class="post-title page-title column-title">'.$item->post_title.'</td>'."\n";
                echo '  <td class="post-title page-title column-title">'.strip_tags($item->post_content).'</td>'."\n";
                echo '</tr>'."\n";
                $zebra++;
            }
        }
        echo ' </tbody>'."\n";
        echo '</table>'."\n";

        // Now the modal
        $function_name = $modal_id.'_action_submit';
?>
        <div id="<?php echo $modal_id; ?>" style="display: none;">
            <div>
                <h2><?php echo $heading; ?>: Actioning <?php echo count($items); ?> item(s)</h2>
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
                        'notes': '<?php echo implode(',', $note_ids); ?>'
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
    $note_ids = explode(',', $_POST['notes']);

    if (empty($comments) || empty($note_ids)) {
        echo 'Invalid data. Please try again.';
        return;
    }

    // Get required terms
    $system_term = get_term_by('slug', 'system', 'bb_note_type');
    $closed_action_term = get_term_by('slug', 'closed-action', 'bb_note_type');

    // Loop through notes
    foreach ($note_ids as $note_id) {
        $note = get_post($note_id);

        // Add new note
        $post_content = $comments."\n\n".'Closed action "'.$note->post_title.'" from '.$note->post_date;
        $data = array(
            'post_type' => 'bb_note',
        	'post_title' => 'Closed Action',
            'post_content' => $post_content,
            'post_status' => 'publish',
            'post_author' => $note->post_author,
            'tax_input' => array(
                    'bb_note_type' => array(
                            $system_term->term_id,
                            $closed_action_term->term_id,
                    )
            )
        );

        $new_post = wp_insert_post($data);

        // Set parent ID on original action
        if ($new_post) {
            $note->post_parent = $new_post;
            $note->post_content .= "\n\n".'Actioned on '.date('Y-m-d').' with the following comment:'."\n\n".$comments;
            wp_update_post($note);
            delete_post_meta($note_id, '_bbc_action_required');
        } else {
            echo 'Failed to add note. Please try again.';
            return;
        }
    }
    return true;
}

function bbconnect_helper_work_queue() {
    $work_queues = array();
    $all_notes = bbconnect_workqueues_get_action_items();
    foreach ($all_notes as $note) {
        $note_types = wp_get_post_terms($note->ID, 'bb_note_type');
        foreach ($note_types as $note_type) {
            if ($note_type->parent > 0) {
                $work_queues[$note_type->term_id] = $note_type->name;
                break;
            }
        }
    }
    return $work_queues;
}