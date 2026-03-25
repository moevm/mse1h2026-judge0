<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/questionlib.php');

class qtype_judge0 extends question_type {
    
    public function extra_question_fields() {
        return array('qtype_judge0_options', 'language_id', 'checker_code', 'expected_output', 'reference_solution', 'input_generator_code', 'input_generator_language_id');
    }

    public function save_question_options($formdata) {
        global $DB;
        $result = parent::save_question_options($formdata);
        if ($result !== true) {
            return $result;
        }

        $DB->delete_records('qtype_judge0_testcases', ['questionid' => $formdata->id]);

        if (!empty($formdata->test_input)) {
            foreach ($formdata->test_input as $key => $input) {
                if (trim($input) === '' && trim($formdata->test_expected_output[$key]) === '') {
                    continue;
                }
                $tc = new stdClass();
                $tc->questionid = $formdata->id;
                $tc->test_input = $input;
                $tc->expected_output = $formdata->test_expected_output[$key];
                $tc->is_hidden = !empty($formdata->is_hidden[$key]) ? 1 : 0;
                $tc->weight = (float)$formdata->weight[$key];
                $DB->insert_record('qtype_judge0_testcases', $tc);
            }
        }
        return true;
    }

    public function get_question_options($question) {
        global $DB;
        $result = parent::get_question_options($question);
        if ($result && isset($question->options)) {
            $question->options->testcases = $DB->get_records('qtype_judge0_testcases', ['questionid' => $question->id], 'id ASC');
        }
        return $result;
    }

    public function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $question->language_id = $questiondata->options->language_id ?? 71;
        $question->checker_code = $questiondata->options->checker_code ?? '';
        $question->expected_output = $questiondata->options->expected_output ?? '';
        $question->reference_solution = $questiondata->options->reference_solution ?? '';
        $question->input_generator_code = $questiondata->options->input_generator_code ?? '';
        $question->input_generator_language_id = $questiondata->options->input_generator_language_id ?? 71;
        $question->testcases = $questiondata->options->testcases ?? [];
    }
}