<?php

function bbconnect_work_queue_rule($rule, array $args = array()) {
    if (file_exists(dirname(__FILE__).'/rules/'.$rule.'.php')) {
        require_once(dirname(__FILE__).'/rules/'.$rule.'.php');
    }
}