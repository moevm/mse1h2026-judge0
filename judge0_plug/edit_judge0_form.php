<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/edit_question_form.php');

class qtype_judge0_edit_form extends question_edit_form {
    protected function definition_inner($mform) {
        $languages = [
            71 => 'Python 3.8.1',
            54 => 'C++ (GCC 9.2.0)',
            62 => 'Java (OpenJDK 13.0.1)',
            63 => 'JavaScript (Node.js 12.14.0)',
            51 => 'C# (Mono 6.6.0.161)',
            46 => 'Bash (5.0.0)',
            72 => 'Ruby (2.7.0)',
            73 => 'Rust (1.40.0)',
            78 => 'Kotlin (1.3.70)',
            68 => 'PHP (7.4.1)',
        ];

        $mform->addElement('select', 'language_id', get_string('language_id', 'qtype_judge0'), $languages);
        $mform->setDefault('language_id', 71);
        $mform->addHelpButton('language_id', 'language_id', 'qtype_judge0');

        $mform->addElement('textarea', 'checker_code', get_string('checker_code', 'qtype_judge0'), ['rows' => 5, 'style' => 'font-family: monospace;']);
        $mform->setType('checker_code', PARAM_RAW);
        $mform->addHelpButton('checker_code', 'checker_code', 'qtype_judge0');

        $mform->addElement('textarea', 'reference_solution', get_string('reference_solution', 'qtype_judge0'), ['rows' => 8, 'style' => 'font-family: monospace;']);
        $mform->setType('reference_solution', PARAM_RAW);
        $mform->addHelpButton('reference_solution', 'reference_solution', 'qtype_judge0');

        $mform->addElement('header', 'inputgeneratorheader', get_string('input_generator_header', 'qtype_judge0'));
        $mform->addElement('select', 'input_generator_language_id', get_string('input_generator_language_id', 'qtype_judge0'), $languages);
        $mform->setDefault('input_generator_language_id', 71);
        $mform->addElement('textarea', 'input_generator_code', get_string('input_generator_code', 'qtype_judge0'), ['rows' => 5, 'style' => 'font-family: monospace;']);
        $mform->setType('input_generator_code', PARAM_RAW);
        $mform->addHelpButton('input_generator_code', 'input_generator_code', 'qtype_judge0');

        $mform->addElement('textarea', 'expected_output', 'Expected Output', ['rows' => 3, 'style' => 'font-family: monospace;']);
        $mform->setType('expected_output', PARAM_RAW);

        $mform->addElement('header', 'testcasesheader', get_string('testcases', 'qtype_judge0'));

        $testcase_elements = [];
        $testcase_elements[] = $mform->createElement('textarea', 'test_input', get_string('test_input', 'qtype_judge0'), ['rows' => 3, 'style' => 'font-family: monospace;']);
        $testcase_elements[] = $mform->createElement('textarea', 'test_expected_output', get_string('test_expected_output', 'qtype_judge0'), ['rows' => 3, 'style' => 'font-family: monospace;']);
        $testcase_elements[] = $mform->createElement('advcheckbox', 'is_hidden', get_string('is_hidden', 'qtype_judge0'));
        $testcase_elements[] = $mform->createElement('text', 'weight', get_string('testcase_weight', 'qtype_judge0'), ['size' => 5]);

        $mform->setType('test_input', PARAM_RAW);
        $mform->setType('test_expected_output', PARAM_RAW);
        $mform->setType('weight', PARAM_FLOAT);

        $repeatcount = 0;
        if (isset($this->question->options->testcases)) {
            $repeatcount = count($this->question->options->testcases);
        }
        $repeatsatstart = max($repeatcount, 1);

        $repeatoptions = [];
        $repeatoptions['weight']['default'] = 1.0;

        $this->repeat_elements($testcase_elements, $repeatsatstart,
            $repeatoptions,
            'testcases_repeats',
            'testcases_add_fields',
            1,
            get_string('add_testcase', 'qtype_judge0'),
            true
        );
    }
    
    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        $question->language_id = $question->options->language_id ?? 71;
        if (!empty($question->options)) {
            $question->checker_code = $question->options->checker_code ?? '';
            $question->reference_solution = $question->options->reference_solution ?? '';
            $question->input_generator_code = $question->options->input_generator_code ?? '';
            $question->input_generator_language_id = $question->options->input_generator_language_id ?? 71;
            $question->expected_output = $question->options->expected_output ?? '';

            if (!empty($question->options->testcases)) {
                $i = 0;
                foreach ($question->options->testcases as $tc) {
                    $question->test_input[$i] = $tc->test_input;
                    $question->test_expected_output[$i] = $tc->expected_output;
                    $question->is_hidden[$i] = $tc->is_hidden;
                    $question->weight[$i] = $tc->weight;
                    $i++;
                }
            }
        }
        return $question;
    }

    public function qtype() {
        return 'judge0';
    }
}
