<?php
defined('MOODLE_INTERNAL') || die();

class qtype_judge0_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        $html = '';
        $html .= html_writer::tag('div', 'Здесь скоро будет редактор кода Judge0', array('class' => 'qtext'));
        return $html;
    }
}
