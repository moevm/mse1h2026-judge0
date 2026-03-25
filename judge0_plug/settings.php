<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'qtype_judge0/server_url',
        get_string('server_url', 'qtype_judge0'),
        get_string('server_url_desc', 'qtype_judge0'),
        'http://server:2358',
        PARAM_URL
    ));
}
