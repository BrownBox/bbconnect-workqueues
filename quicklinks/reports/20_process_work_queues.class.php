<?php
/**
 * Process Work Queues quicklink
 * @author markparnell
 */
class reports_20_process_work_queues_quicklink extends bb_form_quicklink {
    public function __construct() {
        parent::__construct();
        $this->title = 'Process Work Queue(s)';
    }

    protected function form_contents(array $user_ids = array(), array $args = array()) {
        echo '<div class="modal-row"><label for="work_queues" class="full-width">Which Work Queue(s) do you want to process?</label><br>';
        foreach ($args['work_queues'] as $queue_id => $queue_name) {
            echo '<input type="checkbox" name="work_queues['.$queue_id.']" value="'.$queue_id.'" checked> '.$queue_name.'<br>';
        }
        echo '<input type="hidden" name="task_ids" value="'.$args['task_ids'].'">'."\n";
        echo '</div>';
        echo '<div class="modal-row"><label for="comments">Comments:</label><textarea id="comments" name="comments" rows="10"></textarea></div>';
    }

    public static function post_submission() {
        extract($_POST);
        if (empty($comments) || empty($work_queues)) {
            echo 'All fields are required.';
            return;
        }

        $task_ids = explode(',', $task_ids);
        foreach ($task_ids as $idx => $task_id) {
            $entry = GFAPI::get_entry($task_id);
            if (!in_array($entry['form_id'].':'.$entry['work_queue'], $work_queues)) {
                unset($task_ids[$idx]);
            }
        }

        if (empty($task_ids)) {
            echo 'No matching work queue items found.';
            return;
        }

        return bbconnect_workqueues_process_close_action_notes($task_ids, $comments);
    }
}
