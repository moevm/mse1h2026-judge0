<?php
defined('MOODLE_INTERNAL') || die();

class qtype_judge0_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        $question = $qa->get_question();
        $currentAnswer = $qa->get_last_qt_var('answer', '');
        $inputname = $qa->get_qt_field_name('answer');


        $html = html_writer::tag('div', $question->format_questiontext($qa), array('class' => 'qtext'));
        
        $html .= html_writer::start_tag('div', array('style' => 'margin-top: 15px; position: relative;'));
        $html .= html_writer::tag('textarea', htmlspecialchars($currentAnswer), array(
            'name' => $inputname,
            'rows' => 10,
            'style' => 'width: 100%; font-family: "Courier New", monospace; background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; border: 2px solid #333; outline: none; resize: vertical;',
            'spellcheck' => 'false'
        ));
        $html .= html_writer::end_tag('div');

        $state = $qa->get_state();
        
        if (!empty($currentAnswer)) {
            if ($state == question_state::$gradedright) {
                $html .= $this->get_success_box();
            } else if ($state->is_finished()) {
                $html .= $this->get_debug_box($question, $currentAnswer);
            }
        }

        return $html;
    }

    private function get_success_box() {
        $html = "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; border: 1px solid #c3e6cb; margin-top: 15px; display: flex; align-items: center;'>";
        $html .= "<span style='font-size: 24px; margin-right: 15px;'>✅</span>";
        $html .= "<div>";
        $html .= "<h4 style='margin: 0; color: #155724;'>Поздравляем!</h4>";
        $html .= "<p style='margin: 5px 0 0 0;'>Ваше решение успешно прошло все тесты.</p>";
        $html .= "</div></div>";
        return $html;
    }

    private function get_debug_box($question, $code) {
        $full_code = $code . "\n" . $question->checker_code;
        $base_url = get_config('qtype_judge0', 'server_url') ?: 'http://server:2358';
        $judge0_url = rtrim($base_url, '/') . '/submissions?base64_encoded=false&wait=true';

        $payload = json_encode(['source_code' => $full_code, 'language_id' => 71, 'expected_output' => $question->expected_output]);
        $ch = curl_init($judge0_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $result = curl_exec($ch);
        curl_close($ch);

        if ($result) {
            $data = json_decode($result, true);
            $status = $data['status']['description'] ?? 'Ошибка';
            $stdout = $data['stdout'] ?? 'Пусто';
            $stderr = $data['stderr'] ?? '';

            $box = "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; border: 1px solid #ffeeba; margin-top: 15px;'>";
            $box .= "<h4 style='margin-top:0;'>⚠️ Тесты не пройдены: {$status}</h4>";
            $box .= "<b>Вывод вашей программы:</b><pre style='background:#f8f9fa; padding:10px; margin:5px 0; border:1px solid #ccc; font-size: 13px;'>".htmlspecialchars($stdout)."</pre>";
            if ($stderr) {
                $box .= "<b>Ошибки выполнения (Python):</b><pre style='background:#f8d7da; color:#721c24; padding:10px; margin:5px 0; font-size: 13px;'>".htmlspecialchars($stderr)."</pre>";
            }
            $box .= "<b>Ожидалось:</b><pre style='background:#e2e3e5; padding:10px; margin:5px 0; font-size: 13px;'>".htmlspecialchars($question->expected_output)."</pre>";
            $box .= "</div>";
            return $box;
        }
        return "";
    }
}