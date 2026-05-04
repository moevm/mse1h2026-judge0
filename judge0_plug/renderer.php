<?php
defined('MOODLE_INTERNAL') || die();

class qtype_judge0_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        
    
        $question = $qa->get_question();
        $currentAnswer = $qa->get_last_qt_var('answer', '');
        $inputname = $qa->get_qt_field_name('answer');

        $safe_id = str_replace(':', '_', $inputname);
        $container_id = 'monaco_iframe_' . $safe_id;
        $textarea_id = 'hidden_textarea_' . $safe_id;

        $html .= html_writer::tag('div', $question->format_questiontext($qa), array('class' => 'qtext'));
        $html .= html_writer::start_tag('div', array('style' => 'margin-top: 15px; position: relative;'));

        $iframe_html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; background: #1e1e1e; }</style>
        </head>
        <body>
            <div id="monaco-root" style="width: 100%; height: 100%;"></div>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs/loader.min.js"></script>
            <script>
                require.config({ paths: { "vs": "https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs" }});
                
                window.MonacoEnvironment = {
                    getWorkerUrl: function(workerId, label) {
                        return "data:text/javascript;charset=utf-8," + encodeURIComponent("self.MonacoEnvironment = { baseUrl: \"https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/\" }; importScripts(\"https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs/base/worker/workerMain.js\");");
                    }
                };

                require(["vs/editor/editor.main"], function() {
                    var editor = monaco.editor.create(document.getElementById("monaco-root"), {
                        value: "", 
                        language: "python",             
                        theme: "vs-dark",
                        automaticLayout: true,      
                        fontSize: 14,
                        minimap: { enabled: false },
                        scrollBeyondLastLine: false
                    });

                    editor.onDidChangeModelContent(function() {
                        window.parent.postMessage({ type: "monaco_change", id: "' . $safe_id . '", value: editor.getValue() }, "*");
                    });

                    window.addEventListener("message", function(event) {
                        if (event.data.type === "set_value" && event.data.id === "' . $safe_id . '") {
                            if (editor.getValue() !== event.data.value) {
                                editor.setValue(event.data.value);
                            }
                        }
                    });

                    window.parent.postMessage({ type: "monaco_ready", id: "' . $safe_id . '" }, "*");
                });
            </script>
        </body>
        </html>';

        $iframe_src = 'data:text/html;base64,' . base64_encode($iframe_html);

        $html .= html_writer::tag('iframe', '', array(
            'id' => $container_id,
            'src' => $iframe_src,
            'style' => 'width: 100%; height: 400px; border: 1px solid #ccc; border-radius: 4px; display: block;',
            'frameborder' => '0'
        ));

        $html .= html_writer::tag('textarea', htmlspecialchars($currentAnswer), array(
            'id' => $textarea_id,
            'name' => $inputname,
            'style' => 'display: none;'
        ));

        
        $safe_answer_js = json_encode((string)$currentAnswer, JSON_UNESCAPED_UNICODE);
        
        $js = "
        <script>
            (function() {
                var textarea = document.getElementById('{$textarea_id}');
                var initialValue = {$safe_answer_js};

                window.addEventListener('message', function(event) {
                    if (!event.data || event.data.id !== '{$safe_id}') return;

                    if (event.data.type === 'monaco_ready') {
                        var iframe = document.getElementById('{$container_id}');
                        iframe.contentWindow.postMessage({
                            type: 'set_value',
                            id: '{$safe_id}',
                            value: initialValue
                        }, '*');
                    } 
                    else if (event.data.type === 'monaco_change') {
                        textarea.value = event.data.value;
                    }
                });
            })();
        </script>
        ";

        $html .= $js;
        $html .= html_writer::end_tag('div');

        $state = $qa->get_state();
        
        if ($state == question_state::$gradedright) {
            $html .= $this->get_success_box();
        }
        if ($state->is_finished()) {
            $html .= $this->get_debug_box($qa, $question);
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
        $summary = $qa->get_response_summary();
        if (strpos($summary, '||JUDGE0_DEBUG||') !== false) {
            $parts = explode('||JUDGE0_DEBUG||', $summary);
            $data = json_decode($parts[1], true);
        } else {
            return '';
        }

        if (empty($data)) {
            return '';
        }

        $html = '<div style="margin-top: 25px; border-top: 2px solid #dee2e6; padding-top: 15px;">';
        $html .= '<h4 style="margin-bottom: 15px; color: #495057;">Результаты тестирования:</h4>';
        
        $html .= '<div class="table-responsive">';
        $html .= '<table class="generaltable table table-bordered table-striped table-hover" style="width: auto; min-width: 50%; text-align: left; background-color: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">';
        
        $html .= '<thead style="background-color: #f8f9fa;"><tr>';
        $html .= '<th scope="col" style="padding: 12px 20px; border-bottom: 2px solid #dee2e6; width: 1%;">#</th>';
        $html .= '<th scope="col" style="padding: 12px 20px; border-bottom: 2px solid #dee2e6; white-space: nowrap;">Ввод (stdin)</th>';
        $html .= '<th scope="col" style="padding: 12px 20px; border-bottom: 2px solid #dee2e6; white-space: nowrap;">Ожидалось</th>';
        $html .= '<th scope="col" style="padding: 12px 20px; border-bottom: 2px solid #dee2e6; white-space: nowrap;">Ваш вывод</th>';
        $html .= '<th scope="col" style="padding: 12px 20px; border-bottom: 2px solid #dee2e6; width: 1%; white-space: nowrap;">Статус</th>';
        $html .= '</tr></thead>';

        foreach ($data as $index => $res) {
            $num = $index + 1;
            
            $is_hidden = isset($res['_testcase']['is_hidden']) && $res['_testcase']['is_hidden'];
            
            if ($is_hidden) {
                $input = '<span style="color: #6c757d; font-style: italic;">Скрытый тест</span>';
                $expected = '<span style="color: #6c757d; font-style: italic;">Скрыто</span>';
                $output = '<span style="color: #6c757d; font-style: italic;">Скрыто</span>';
            } else {
                $input = '<pre style="margin: 0; font-size: 13px; background: transparent; border: none; padding: 0;">' . htmlspecialchars($res['_testcase']['input'] ?? '') . '</pre>';
                $expected = '<pre style="margin: 0; font-size: 13px; background: transparent; border: none; padding: 0;">' . htmlspecialchars($res['_testcase']['expected'] ?? '') . '</pre>';
                
                $raw_output = $res['stdout'] ?? $res['compile_output'] ?? $res['stderr'] ?? '';
                $output = '<pre style="margin: 0; font-size: 13px; background: transparent; border: none; padding: 0;">' . htmlspecialchars($raw_output) . '</pre>';
            }
            
            $status_desc = $res['status']['description'] ?? 'Unknown';
            $status_id = $res['status']['id'] ?? 0;
            
            if ($status_id == 3) {
                $status_html = '<span style="display: inline-block; background-color: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 13px;">' . $status_desc . '</span>';
            } else {
                $status_html = '<span style="display: inline-block; background-color: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 13px;">' . $status_desc . '</span>';
            }

            $html .= '<tr>';
            $html .= '<td style="padding: 12px; vertical-align: middle;"><b>' . $num . '</b></td>';
            $html .= '<td style="padding: 12px; vertical-align: middle;">' . $input . '</td>';
            $html .= '<td style="padding: 12px; vertical-align: middle;">' . $expected . '</td>';
            $html .= '<td style="padding: 12px; vertical-align: middle;">' . $output . '</td>';
            $html .= '<td style="padding: 12px; vertical-align: middle;">' . $status_html . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table></div></div>';
        return $html;
    }
}