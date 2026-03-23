<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/edit_question_form.php');

class qtype_judge0_edit_form extends question_edit_form {
    protected function definition_inner($mform) {
        
        $mform->addElement('textarea', 'checker_code', get_string('checker_code', 'qtype_judge0'), ['rows' => 5, 'style' => 'font-family: monospace;']);
        $mform->setType('checker_code', PARAM_RAW);
        $mform->addHelpButton('checker_code', 'checker_code', 'qtype_judge0');

        $mform->addElement('textarea', 'expected_output', 'Expected Output', ['rows' => 3, 'style' => 'font-family: monospace;']);
        $mform->setType('expected_output', PARAM_RAW);
    }
    
    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        if (!empty($question->options)) {
            $question->checker_code = $question->options->checker_code;
            $question->expected_output = $question->options->expected_output;
        }
        return $question;
    }

    public function qtype() {
        return 'judge0';
    }
}