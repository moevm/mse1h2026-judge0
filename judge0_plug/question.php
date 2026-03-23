<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/questionbase.php');

class qtype_judge0_question extends question_graded_automatically {
    public $checker_code;
    public $expected_output;

    public function get_expected_data() { return array('answer' => PARAM_RAW); }

    public function grade_response(array $response) {
        $code = $response['answer'] ?? '';
        if (trim($code) === '') {
            return array(0.0, question_state::$gradedwrong);
        }

        $full_code = $code . "\n" . $this->checker_code;
        $base_url = get_config('qtype_judge0', 'server_url') ?: 'http://server:2358';
        $judge0_url = rtrim($base_url, '/') . '/submissions?base64_encoded=false&wait=true';

        $payload = json_encode([
            'source_code' => $full_code,
            'language_id' => 71,
            'expected_output' => $this->expected_output
        ]);

        $ch = curl_init($judge0_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($ch);
        curl_close($ch);

        if ($result) {
            $data = json_decode($result, true);
            if (isset($data['status']['id']) && $data['status']['id'] == 3) {
                return array(1.0, question_state::$gradedright);
            }
        }
        return array(0.0, question_state::$gradedwrong);
    }

    public function summarise_response(array $response) { return isset($response['answer']) ? $response['answer'] : null; }
    public function is_complete_response(array $response) { return array_key_exists('answer', $response) && $response['answer'] !== ''; }
    public function is_gradable_response(array $response) { return $this->is_complete_response($response); }
    public function is_same_response(array $prevresponse, array $newresponse) { return question_utils::arrays_same_at_key_missing_is_blank($prevresponse, $newresponse, 'answer'); }
    public function get_validation_error(array $response) { return ''; }
    public function get_correct_response() { return array(); }
}