<?php
defined('MOODLE_INTERNAL') || die();

class qtype_judge0_renderer extends qtype_renderer {

    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        $question = $qa->get_question();
        $currentAnswer = $qa->get_last_qt_var('answer', '');
        $inputname = $qa->get_qt_field_name('answer');
        $editorid = 'judge0_editor_' . uniqid();

        $html = html_writer::tag('div', $question->format_questiontext($qa), ['class' => 'qtext']);

        $html .= html_writer::start_tag('div', ['class' => 'judge0-editor-shell']);
        $html .= html_writer::tag('textarea', s($currentAnswer), [
            'id' => $editorid,
            'name' => $inputname,
            'rows' => 12,
            'spellcheck' => 'false',
            'class' => 'judge0-source'
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

        $html .= $this->render_assets_and_init($editorid);

        return $html;
    }

    private function get_success_box() {
        return "
            <div class='judge0-box judge0-box-success'>
                <div class='judge0-box-icon'>✅</div>
                <div class='judge0-box-body'>
                    <div class='judge0-box-title'>Поздравляем!</div>
                    <div class='judge0-box-text'>Ваше решение успешно прошло все тесты.</div>
                </div>
            </div>
        ";
    }

    private function get_debug_box($question, $code) {
        $checker = !empty($question->checker_code) ? "\n" . $question->checker_code : '';
        $full_code = $code . $checker;

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
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);

        $result = curl_exec($ch);

        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return $this->render_error_box('Не удалось получить результат проверки', $error);
        }

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode < 200 || $httpcode >= 300) {
            return $this->render_error_box(
                'Judge0 вернул HTTP-ошибку',
                'HTTP status: ' . $httpcode . "\n\n" . $result
            );
        }

        $data = json_decode($result, true);

        if (!is_array($data)) {
            return $this->render_error_box('Некорректный ответ от Judge0', $result);
        }

        $status = $data['status']['description'] ?? 'Ошибка';
        $stdout = $data['stdout'] ?? 'Пусто';
        $stderr = $data['stderr'] ?? '';

        $html = "
            <div class='judge0-box judge0-box-warning'>
                <div class='judge0-box-icon'>⚠️</div>
                <div class='judge0-box-body'>
                    <div class='judge0-box-title'>Тесты не пройдены: " . s($status) . "</div>
                    <div class='judge0-box-text'>Ниже показаны вывод, ошибки и ожидаемый результат.</div>
                </div>
            </div>
        ";

        $html .= $this->render_section('Вывод вашей программы', $stdout, 'output');

        if (!empty($stderr)) {
            $html .= $this->render_section('Ошибки выполнения', $stderr, 'error');
        }

        $html .= $this->render_section('Ожидалось', $question->expected_output, 'expected');

        return $html;
    }

    private function render_error_box($title, $details) {
        return "
            <div class='judge0-box judge0-box-error'>
                <div class='judge0-box-icon'>❌</div>
                <div class='judge0-box-body'>
                    <div class='judge0-box-title'>" . s($title) . "</div>
                    <pre class='judge0-pre judge0-pre-error'>" . s($details) . "</pre>
                </div>
            </div>
        ";
    }

    private function render_section($title, $content, $type) {
        return "
            <div class='judge0-section judge0-section-" . s($type) . "'>
                <div class='judge0-section-title'>" . s($title) . "</div>
                <pre class='judge0-pre'>" . s($content) . "</pre>
            </div>
        ";
    }

    private function render_assets_and_init($editorid) {
        global $CFG;

        static $assetsprinted = false;
        $base = $CFG->wwwroot . '/question/type/judge0_plug/codemirror';        
        $html = '';

        if (!$assetsprinted) {
            $assetsprinted = true;

            $html .= "
                <link rel='stylesheet' href='{$base}/lib/codemirror.css'>
                <link rel='stylesheet' href='{$base}/theme/monokai.css'>
                <script src='{$base}/lib/codemirror.js'></script>
                <script src='{$base}/mode/python/python.js'></script>
                <script src='{$base}/addon/edit/matchbrackets.js'></script>
                <script src='{$base}/addon/selection/active-line.js'></script>

                <style>
                    .judge0-editor-shell {
                        margin-top: 15px;
                        border-radius: 14px;
                        overflow: hidden;
                        border: 1px solid #2f3542;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
                    }

                    .judge0-source {
                        width: 100%;
                    }

                    .CodeMirror {
                        height: auto;
                        min-height: 320px;
                        font-size: 14px;
                        line-height: 1.6;
                        border-radius: 14px;
                        font-family: Consolas, 'Courier New', monospace;
                    }

                    .CodeMirror-scroll {
                        min-height: 320px;
                    }

                    .judge0-box {
                        margin-top: 15px;
                        padding: 16px 18px;
                        border-radius: 12px;
                        display: flex;
                        gap: 14px;
                        align-items: flex-start;
                    }

                    .judge0-box-success {
                        background: linear-gradient(180deg, #e8fff1 0%, #dff7ea 100%);
                        border: 1px solid #b7e4c7;
                        color: #14532d;
                    }

                    .judge0-box-warning {
                        background: linear-gradient(180deg, #fff9e6 0%, #fff2cc 100%);
                        border: 1px solid #ffd36b;
                        color: #7a5b00;
                    }

                    .judge0-box-error {
                        background: linear-gradient(180deg, #fff6f6 0%, #fdecec 100%);
                        border: 1px solid #f5b5b5;
                        color: #8a1f1f;
                    }

                    .judge0-box-icon {
                        font-size: 24px;
                        line-height: 1;
                        flex: 0 0 auto;
                    }

                    .judge0-box-title {
                        font-size: 16px;
                        font-weight: 700;
                        margin-bottom: 4px;
                    }

                    .judge0-box-text {
                        font-size: 14px;
                        opacity: .95;
                    }

                    .judge0-section {
                        margin-top: 12px;
                    }

                    .judge0-section-title {
                        font-size: 14px;
                        font-weight: 700;
                        margin-bottom: 6px;
                    }

                    .judge0-pre {
                        margin: 0;
                        padding: 12px;
                        border-radius: 8px;
                        font-size: 13px;
                        white-space: pre-wrap;
                        overflow-x: auto;
                        box-sizing: border-box;
                    }

                    .judge0-section-output .judge0-pre {
                        background: #f8f9fa;
                        border: 1px solid #d9d9d9;
                    }

                    .judge0-section-error .judge0-pre,
                    .judge0-pre-error {
                        background: #fff1f2;
                        color: #9f1239;
                        border: 1px solid #fecdd3;
                    }

                    .judge0-section-expected .judge0-pre {
                        background: #eef2f7;
                        border: 1px solid #d7dce3;
                    }
                </style>
            ";
        }

        $html .= "
            <script>
                (function() {
                    function initJudge0Editor() {
                        var ta = document.getElementById(" . json_encode($editorid) . ");
                        if (!ta || ta.dataset.judge0Inited === '1' || !window.CodeMirror) {
                            return;
                        }

                        ta.dataset.judge0Inited = '1';

                        var editor = CodeMirror.fromTextArea(ta, {
                            mode: 'python',
                            theme: 'monokai',
                            lineNumbers: true,
                            lineWrapping: true,
                            indentUnit: 4,
                            tabSize: 4,
                            indentWithTabs: true,
                            matchBrackets: true,
                            styleActiveLine: true,
                            viewportMargin: Infinity,
                            autofocus: false,
                            extraKeys: {
                                Tab: 'defaultTab',
                                'Shift-Tab': 'indentAuto'
                            }
                        });

                        setTimeout(function() {
                            editor.refresh();
                        }, 0);
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', initJudge0Editor);
                    } else {
                        initJudge0Editor();
                    }
                })();
            </script>
        ";

        return $html;
    }
}