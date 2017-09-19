<?php
function bbconnect_work_queue_rule($rule, array $args = array()) {
    if (file_exists(BBCONNECT_WORKQUEUES_DIR.'rules/'.$rule.'.php')) {
        require_once(BBCONNECT_WORKQUEUES_DIR.'rules/'.$rule.'.php');
        return true;
    } else {
        return false;
    }
}
