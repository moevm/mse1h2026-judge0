<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/edit_question_form.php');

class qtype_judge0_edit_form extends question_edit_form {
    protected function definition_inner($mform) {
        $mform->addElement('textarea', 'expected_output', 'Expected Output', ['rows' => 5]);
        $mform->setType('expected_output', PARAM_RAW);
    }
    
    public function qtype() {
        return 'judge0';
    }
}
