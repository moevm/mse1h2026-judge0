<?php
defined('MOODLE_INTERNAL') || die();

class qtype_judge0_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        $question = $qa->get_question();
        $currentAnswer = $qa->get_last_qt_var('answer', '');
        $inputname = $qa->get_qt_field_name('answer');

        $safe_id = str_replace(':', '_', $inputname);
        $container_id = 'monaco_container_' . $safe_id;
        $textarea_id = 'hidden_textarea_' . $safe_id;

        $html = html_writer::tag('div', $question->format_questiontext($qa), array('class' => 'qtext'));
        
        $html .= html_writer::start_tag('div', array('style' => 'margin-top: 15px; position: relative;'));

        $html .= html_writer::tag('div', '', array(
            'id' => $container_id,
            'style' => 'width: 100%; height: 400px; border: 1px solid #ccc; border-radius: 4px; overflow: hidden; background: #1e1e1e;'
        ));

        
        $html .= html_writer::tag('textarea', htmlspecialchars($currentAnswer), array(
            'id' => $textarea_id,
            'name' => $inputname,
            'style' => 'display: none;' // Полностью скрываем поле
        ));

        $js = "
        <script src='https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs/loader.min.js'></script>
        <script>
            require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs' }});
            require(['vs/editor/editor.main'], function() {
                var container = document.getElementById('{$container_id}');
                var hiddenInput = document.getElementById('{$textarea_id}');

                // Создаем редактор
                var editor = monaco.editor.create(container, {
                    value: hiddenInput.value,    
                    language: 'python',             
                    theme: 'vs-dark',
                    automaticLayout: true,      
                    fontSize: 14,
                    minimap: { enabled: false }, 
                    scrollBeyondLastLine: false
                });

                // Синхронизируем код со скрытым полем при каждом изменении
                editor.onDidChangeModelContent(function() {
                    hiddenInput.value = editor.getValue();
                });
            });
        </script>
        ";
        $html .= $js;

        $html .= html_writer::end_tag('div');

        $state = $qa->get_state();
        
        if (!empty($currentAnswer)) {
            if ($state == question_state::$gradedright) {
                $html .= $this->get_success_box();
            }
            if ($state->is_finished()) {
                $html .= $this->get_debug_box($qa, $question);
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

    private function get_debug_box(question_attempt $qa, $question) {
        $stored = $qa->get_last_qt_var('_judge0_result', '');
        $data = json_decode($stored, true);
        
        if (empty($data) && !empty($question->last_judge0_response)) {
            $data = $question->last_judge0_response;
        }

        if (empty($data)) {
            return '';
        }

        if (isset($data['status'])) {
            $data = [$data];
        }

        $box = "<div style='margin-top: 15px;'>";
        $box .= "<h4 style='color: #856404;'>Результаты тестирования:</h4>";
        
        $box .= "<table style='width: 100%; border-collapse: collapse; margin-bottom: 15px;'>";
        $box .= "<tr style='background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;'>";
        $box .= "<th style='padding: 8px; text-align: left;'>Тест</th>";
        $box .= "<th style='padding: 8px; text-align: left;'>Ввод (stdin)</th>";
        $box .= "<th style='padding: 8px; text-align: left;'>Ожидалось</th>";
        $box .= "<th style='padding: 8px; text-align: left;'>Ваш вывод</th>";
        $box .= "<th style='padding: 8px; text-align: left;'>Статус</th>";
        $box .= "</tr>";

        $test_num = 1;
        foreach ($data as $res) {
            $status = $res['status']['description'] ?? 'Ошибка';
            $status_id = $res['status']['id'] ?? 0;
            $stdout = $res['stdout'] ?? '';
            $stderr = $res['stderr'] ?? '';
            
            $is_hidden = false;
            $input = '';
            $expected = $question->expected_output ?? ''; 

            $is_dynamic = false;
            if (isset($res['_testcase'])) {
                $is_hidden = !empty($res['_testcase']['is_hidden']);
                $is_dynamic = !empty($res['_testcase']['is_dynamic']);
                $input = $res['_testcase']['input'];
                $expected = $res['_testcase']['expected'];
            }

            if ($status_id == 3) {
                $status_html = "<span style='color: #28a745; font-weight: bold;'>✅ " . htmlspecialchars($status) . "</span>";
            } else {
                $status_html = "<span style='color: #dc3545; font-weight: bold;'>❌ " . htmlspecialchars($status) . "</span>";
            }

            if ($is_hidden) {
                $input_html = "<i>Скрыто</i>";
                $expected_html = "<i>Скрыто</i>";
                $stdout_html = "<i>Скрыто</i>";
            } else {
                $input_html = "<pre style='margin:0; font-size:12px;'>" . htmlspecialchars($input) . "</pre>";
                if ($is_dynamic) {
                    $input_html .= "<div style='font-size:10px; color:#17a2b8; margin-top:2px;'>(Сгенерировано)</div>";
                }
                
                $expected_html = "<pre style='margin:0; font-size:12px;'>" . htmlspecialchars($expected) . "</pre>";
                $stdout_disp = $stdout ?: '';
                if ($stderr) {
                    $stdout_disp .= "\n[STDERR]\n" . $stderr;
                }
                if (trim($stdout_disp) === '') $stdout_disp = 'Пусто';
                $stdout_html = "<pre style='margin:0; font-size:12px;'>" . htmlspecialchars($stdout_disp) . "</pre>";
            }

            $box .= "<tr style='border-bottom: 1px solid #e9ecef;'>";
            $box .= "<td style='padding: 8px;'>#" . $test_num . "</td>";
            $box .= "<td style='padding: 8px;'>" . $input_html . "</td>";
            $box .= "<td style='padding: 8px;'>" . $expected_html . "</td>";
            $box .= "<td style='padding: 8px;'>" . $stdout_html . "</td>";
            $box .= "<td style='padding: 8px;'>" . $status_html . "</td>";
            $box .= "</tr>";
            
            $test_num++;
        }
        $box .= "</table></div>";

        return $box;
    }
}