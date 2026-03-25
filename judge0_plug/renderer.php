<?php
defined('MOODLE_INTERNAL') || die();

class qtype_judge0_renderer extends qtype_renderer {

    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        $question = $qa->get_question();
        $currentAnswer = $qa->get_last_qt_var('answer', '');
        $inputname = $qa->get_qt_field_name('answer');

        $html = html_writer::tag('div', $question->format_questiontext($qa), ['class' => 'qtext']);

        $html .= html_writer::start_tag('div', [
            'style' => 'margin-top: 15px; position: relative;'
        ]);

        $html .= html_writer::tag('textarea', s($currentAnswer), [
            'name' => $inputname,
            'rows' => 12,
            'spellcheck' => 'false',
            'style' => implode(';', [
                'width: 100%',
                'min-height: 280px',
                'font-family: Consolas, "Courier New", monospace',
                'font-size: 14px',
                'line-height: 1.5',
                'background: linear-gradient(180deg, #1f232a 0%, #171a20 100%)',
                'color: #e6edf3',
                'padding: 16px',
                'border-radius: 12px',
                'border: 1px solid #30363d',
                'outline: none',
                'resize: vertical',
                'box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12)',
                'transition: border-color .2s ease, box-shadow .2s ease'
            ])
        ]);

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
        return "
            <div style='
                margin-top: 15px;
                padding: 16px 18px;
                border-radius: 12px;
                background: linear-gradient(180deg, #e8fff1 0%, #dff7ea 100%);
                border: 1px solid #b7e4c7;
                color: #14532d;
                display: flex;
                align-items: flex-start;
                gap: 14px;
                box-shadow: 0 8px 24px rgba(20, 83, 45, 0.08);
            '>
                <div style='font-size: 24px; line-height: 1;'>✅</div>
                <div>
                    <div style='font-size: 16px; font-weight: 700; margin-bottom: 4px;'>Поздравляем!</div>
                    <div style='font-size: 14px; opacity: .95;'>Ваше решение успешно прошло все тесты.</div>
                </div>
            </div>
        ";
    }

    private function get_debug_box($question, $code) {
        $full_code = $code . "\n" . $question->checker_code;

        $base_url = get_config('qtype_judge0', 'server_url') ?: 'http://server:2358';
        $judge0_url = rtrim($base_url, '/') . '/submissions?base64_encoded=false&wait=true';

        $payload = json_encode([
            'source_code' => $full_code,
            'language_id' => 71,
            'expected_output' => $question->expected_output
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($judge0_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 5,
        ]);

        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return "
                <div style='
                    margin-top: 15px;
                    padding: 16px 18px;
                    border-radius: 12px;
                    background: linear-gradient(180deg, #fff6f6 0%, #fdecec 100%);
                    border: 1px solid #f5b5b5;
                    color: #8a1f1f;
                '>
                    <div style='font-weight: 700; margin-bottom: 8px;'>⚠️ Не удалось получить результат проверки</div>
                    <pre style='margin:0; white-space: pre-wrap; background:#fff; border:1px solid #f2c4c4; padding:12px; border-radius:8px; font-size:13px;'>".s($error)."</pre>
                </div>
            ";
        }

        curl_close($ch);

        $data = json_decode($result, true);

        if (!is_array($data)) {
            return "
                <div style='
                    margin-top: 15px;
                    padding: 16px 18px;
                    border-radius: 12px;
                    background: linear-gradient(180deg, #fff6f6 0%, #fdecec 100%);
                    border: 1px solid #f5b5b5;
                    color: #8a1f1f;
                '>
                    <div style='font-weight: 700; margin-bottom: 8px;'>⚠️ Некорректный ответ от Judge0</div>
                    <pre style='margin:0; white-space: pre-wrap; background:#fff; border:1px solid #f2c4c4; padding:12px; border-radius:8px; font-size:13px;'>".s($result)."</pre>
                </div>
            ";
        }

        $status = $data['status']['description'] ?? 'Ошибка';
        $stdout = $data['stdout'] ?? 'Пусто';
        $stderr = $data['stderr'] ?? '';

        $box = "
            <div style='
                margin-top: 15px;
                padding: 16px 18px;
                border-radius: 12px;
                background: linear-gradient(180deg, #fff9e6 0%, #fff2cc 100%);
                border: 1px solid #ffd36b;
                color: #7a5b00;
                box-shadow: 0 8px 24px rgba(122, 91, 0, 0.08);
            '>
                <div style='font-size: 16px; font-weight: 700; margin-bottom: 12px;'>⚠️ Тесты не пройдены: " . s($status) . "</div>
                <div style='margin-bottom: 8px; font-weight: 700;'>Вывод вашей программы:</div>
                <pre style='background:#f8f9fa; padding:12px; margin:0 0 12px 0; border:1px solid #d9d9d9; border-radius:8px; font-size:13px; white-space: pre-wrap; overflow-x:auto;'>" . s($stdout) . "</pre>
        ";

        if (!empty($stderr)) {
            $box .= "
                <div style='margin-bottom: 8px; font-weight: 700;'>Ошибки выполнения:</div>
                <pre style='background:#fff1f2; color:#9f1239; padding:12px; margin:0 0 12px 0; border:1px solid #fecdd3; border-radius:8px; font-size:13px; white-space: pre-wrap; overflow-x:auto;'>" . s($stderr) . "</pre>
            ";
        }

        $box .= "
                <div style='margin-bottom: 8px; font-weight: 700;'>Ожидалось:</div>
                <pre style='background:#eef2f7; padding:12px; margin:0; border:1px solid #d7dce3; border-radius:8px; font-size:13px; white-space: pre-wrap; overflow-x:auto;'>" . s($question->expected_output) . "</pre>
            </div>
        ";

        return $box;
    }
}