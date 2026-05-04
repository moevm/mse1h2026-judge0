<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/questionlib.php');

class qtype_judge0 extends question_type {


    public function menu_name() {
        return get_string('pluginname', 'qtype_judge0');
    }
     
    public function can_be_created_by_user() {
        return true;
    }

    
    public function extra_question_fields() {
        return array('qtype_judge0_options', 'language_id', 'checker_code', 'expected_output', 'reference_solution', 'input_generator_code', 'input_generator_language_id');
    }

    public function save_question_options($formdata) {
        global $DB;
        
        parent::save_question_options($formdata);

        $DB->delete_records('qtype_judge0_testcases', ['questionid' => $formdata->id]);

        $inputs = $formdata->test_input ?? [];
        $outputs = $formdata->test_expected_output ?? [];
        $hiddens = $formdata->is_hidden ?? [];
        $weights = $formdata->weight ?? [];

        if (!empty($inputs)) {
            foreach ($inputs as $key => $input) {
                
                $in_text = is_array($input) ? $input['text'] : $input;
                $out_raw = $outputs[$key] ?? '';
                $out_text = is_array($out_raw) ? $out_raw['text'] : $out_raw;

                if (trim($in_text) === '' && trim($out_text) === '') {
                    continue;
                }

                $tc = new stdClass();
                $tc->questionid = $formdata->id;
                $tc->test_input = $in_text;
                $tc->expected_output = $out_text;
                $tc->is_hidden = !empty($hiddens[$key]) ? 1 : 0;
                $tc->weight = isset($weights[$key]) ? (float)$weights[$key] : 1.0;
                
                $DB->insert_record('qtype_judge0_testcases', $tc);
            }
        }
        
        return true;
    }

    public function get_question_options($question) {
        global $DB;
        parent::get_question_options($question);
        
        if (!isset($question->options)) {
            $question->options = new stdClass();
        }
        
        $testcases = $DB->get_records('qtype_judge0_testcases', ['questionid' => $question->id], 'id ASC');
        $question->options->testcases = $testcases;
        
       
        if (!empty($testcases)) {
            $i = 0;
            foreach ($testcases as $tc) {
                $question->test_input[$i] = $tc->test_input;
                $question->test_expected_output[$i] = $tc->expected_output;
                $question->is_hidden[$i] = $tc->is_hidden;
                $question->weight[$i] = $tc->weight;
                $i++;
            }
        }
        
        return true;
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