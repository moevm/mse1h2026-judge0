<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/questionbase.php');

class qtype_judge0_question extends question_graded_automatically {
    public $language_id;
    public $checker_code;
    public $expected_output;
    public $last_judge0_response = null;

    public function get_expected_data() { return array('answer' => PARAM_RAW); }

    public function grade_response(array $response) {
        global $USER;
        $student_id = $USER->id ?? rand(1000, 9999);

        $code = $response['answer'] ?? '';
        if (trim($code) === '') {
            return array(0.0, question_state::$gradedwrong);
        }

        $full_code = $code . "\n" . $this->checker_code;
        $base_url = get_config('qtype_judge0', 'server_url') ?: 'http://server:2358';
        $judge0_url = rtrim($base_url, '/') . '/submissions?base64_encoded=false&wait=true';

        $this->last_judge0_response = [];

        $dynamic_input = null;
        if (!empty($this->input_generator_code)) {
            $gen_payload = json_encode([
                'source_code' => $this->input_generator_code,
                'language_id' => (int)$this->input_generator_language_id,
                'stdin' => (string)$student_id
            ]);
            $gen_res = $this->send_judge0_request($judge0_url, $gen_payload);
            if ($gen_res && isset($gen_res['status']['id']) && $gen_res['status']['id'] == 3) {
                $dynamic_input = $gen_res['stdout'] ?? '';
            }
        }

        $cases_to_run = [];
        if ($dynamic_input !== null) {
            $cases_to_run[] = [
                'input' => $dynamic_input,
                'expected' => '',
                'is_hidden' => false,
                'weight' => 1.0,
                'is_dynamic' => true
            ];
        }

        if (!empty($this->testcases)) {
            foreach ($this->testcases as $tc) {
                if (trim($tc->test_input) === '' && trim($tc->expected_output) === '') continue;
                $cases_to_run[] = [
                    'input' => $tc->test_input,
                    'expected' => $tc->expected_output,
                    'is_hidden' => $tc->is_hidden,
                    'weight' => $tc->weight,
                    'is_dynamic' => false
                ];
            }
        }

        if (empty($cases_to_run)) {
            $cases_to_run[] = [
                'input' => '',
                'expected' => $this->expected_output ?? '',
                'is_hidden' => false,
                'weight' => 1.0,
                'is_dynamic' => false
            ];
        }

        $passed = 0;
        $total = count($cases_to_run);
        $total_weight = 0.0;
        $earned_weight = 0.0;

        foreach ($cases_to_run as $case) {
            $expected = $case['expected'];

            if (trim($expected) === '' && !empty($this->reference_solution)) {
                $ref_payload = json_encode([
                    'source_code' => $this->reference_solution,
                    'language_id' => (int)$this->language_id,
                    'stdin' => $case['input']
                ]);
                $ref_res = $this->send_judge0_request($judge0_url, $ref_payload);
                if ($ref_res && isset($ref_res['status']['id']) && $ref_res['status']['id'] == 3) {
                    $expected = $ref_res['stdout'] ?? '';
                } else {
                    $expected = "ОШИБКА ИДЕАЛЬНОГО РЕШЕНИЯ: " . ($ref_res['stderr'] ?? 'Unknown');
                }
            }

            $payload = json_encode([
                'source_code' => $full_code,
                'language_id' => (int)$this->language_id,
                'stdin' => $case['input'],
                'expected_output' => $expected
            ]);
            
            $res = $this->send_judge0_request($judge0_url, $payload);
            if ($res === false) {
                $res = ['status' => ['id' => 13, 'description' => 'Internal Error / Connect Failed']];
            }
            
            $res['_testcase'] = [
                'input' => $case['input'],
                'expected' => $expected,
                'is_hidden' => $case['is_hidden'],
                'weight' => $case['weight'],
                'is_dynamic' => $case['is_dynamic']
            ];
            $this->last_judge0_response[] = $res;

            $w = (float)$case['weight'];
            if ($w <= 0) $w = 1.0;
            $total_weight += $w;

            if (isset($res['status']['id']) && $res['status']['id'] == 3) {
                $passed++;
                $earned_weight += $w;
            }
        }

        if ($total_weight <= 0) $total_weight = 1.0;
        $fraction = $earned_weight / $total_weight;

        if ($passed === $total) {
            return array($fraction, question_state::$gradedright);
        } elseif ($passed > 0) {
            return array($fraction, question_state::$gradedpartial);
        }
        return array(0.0, question_state::$gradedwrong);
    }

    private function send_judge0_request($url, $payload) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $result = curl_exec($ch);

        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_errno !== 0) {
            debugging("Judge0 cURL error #{$curl_errno}: {$curl_error}", DEBUG_DEVELOPER);
            return false;
        }
        return $result ? json_decode($result, true) : false;
    }

    public function summarise_response(array $response) { return isset($response['answer']) ? $response['answer'] : null; }
    public function is_complete_response(array $response) { return array_key_exists('answer', $response) && $response['answer'] !== ''; }
    public function is_gradable_response(array $response) { return $this->is_complete_response($response); }
    public function is_same_response(array $prevresponse, array $newresponse) { return question_utils::arrays_same_at_key_missing_is_blank($prevresponse, $newresponse, 'answer'); }
    public function get_validation_error(array $response) { return ''; }
    public function get_correct_response() { return array(); }

    public function get_response_summary_for_storage(array $response) {
        return json_encode($this->last_judge0_response ?? []);
    }
}