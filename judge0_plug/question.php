<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/questionbase.php');

class qtype_judge0_question extends question_graded_automatically {
    
    public function get_expected_data() {
        return array('answer' => PARAM_RAW);
    }
    
    public function grade_response(array $response) {
        return array(0.0, question_state::$gradedwrong);
    }
    
    public function summarise_response(array $response) {
        return isset($response['answer']) ? $response['answer'] : null;
    }

    public function is_complete_response(array $response) {
        return array_key_exists('answer', $response) && $response['answer'] !== '';
    }

    public function is_gradable_response(array $response) {
        return $this->is_complete_response($response);
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        return question_utils::arrays_same_at_key_missing_is_blank($prevresponse, $newresponse, 'answer');
    }

    public function get_validation_error(array $response) {
        return '';
    }

    public function get_correct_response() {
        return array();
    }
}
