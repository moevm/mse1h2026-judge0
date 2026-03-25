<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/questionlib.php');

class qtype_judge0 extends question_type {
    
    public function extra_question_fields() {
        return array('qtype_judge0_options', 'checker_code', 'expected_output');
    }

    public function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $question->checker_code = $questiondata->options->checker_code ?? '';
        $question->expected_output = $questiondata->options->expected_output ?? '';
    }
}