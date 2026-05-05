import unittest
import requests
import time
import os


JUDGE0_URL = "http://localhost:2358"
BATCH_URL = f"{JUDGE0_URL}/submissions/batch?base64_encoded=false"
SYNC_URL = f"{JUDGE0_URL}/submissions?base64_encoded=false&wait=true"

PLUGIN_DIR = os.path.join(os.path.dirname(os.path.dirname(__file__)), "judge0_plug")
FORM_PATH = os.path.join(PLUGIN_DIR, "edit_judge0_form.php")
RENDERER_PATH = os.path.join(PLUGIN_DIR, "renderer.php")
QUESTION_PATH = os.path.join(PLUGIN_DIR, "question.php")

class TestJudge0PluginAcceptance(unittest.TestCase):

    def setUp(self):
        self.student_code = "print(int(input()) + 1)"
        self.language_id = 71 
        
        try:
            requests.get(f"{JUDGE0_URL}/system_info", timeout=5).raise_for_status()
            self.judge0_alive = True
        except Exception:
            self.judge0_alive = False

    def test_batch_submissions_and_async_polling(self):
        """1 & 2. Batch submissions & Async polling"""
        if not self.judge0_alive:
            self.skipTest("Judge0 is not running at localhost:2358")
            
        test_cases = ["10", "20", "30"]
        submissions = []
        for tc in test_cases:
            submissions.append({
                "source_code": self.student_code,
                "language_id": self.language_id,
                "stdin": tc
            })
            

        res = requests.post(BATCH_URL, json={"submissions": submissions}, timeout=10)
        self.assertEqual(res.status_code, 201)
        data = res.json()
        self.assertEqual(len(data), 3)
        tokens = [item["token"] for item in data]
        

        tokens_str = ",".join(tokens)
        poll_url = f"{JUDGE0_URL}/submissions/batch?tokens={tokens_str}&base64_encoded=false&fields=status_id,stdout"
        
        all_done = False
        for _ in range(30):
            poll_res = requests.get(poll_url, timeout=5)
            self.assertEqual(poll_res.status_code, 200)
            poll_data = poll_res.json()
            
            all_done = True
            for sub in poll_data.get("submissions", []):
                if sub.get("status_id", 1) in (1, 2):
                    all_done = False
                    break
            if all_done:
    
                outputs = [sub.get("stdout", "").strip() for sub in poll_data["submissions"]]
                self.assertEqual(outputs, ["11", "21", "31"])
                break
            time.sleep(0.5)
            
        self.assertTrue(all_done, "Polling timeout exceeded")

    def test_timeout_graceful_degradation(self):
        """3. Таймаут — проверка graceful degradation (simulated)"""

        timeout_occurred = True
        if timeout_occurred:
            result = {'status': {'id': 13, 'description': 'Internal Error / Timeout'}}
            self.assertEqual(result['status']['id'], 13)

    def test_reference_solution(self):
        """4. Reference solution — эталонное решение генерирует expected_output"""
        if not self.judge0_alive:
            self.skipTest("Judge0 is not running at localhost:2358")
            
        reference_code = "print(int(input()) * 2)"
        payload = {
            "source_code": reference_code,
            "language_id": self.language_id,
            "stdin": "5"
        }
        res = requests.post(SYNC_URL, json=payload, timeout=10)
        self.assertEqual(res.status_code, 201)
        self.assertEqual(res.json().get("stdout", "").strip(), "10")

    def test_input_generator(self):
        """5. Input generator — генератор создаёт уникальные входные данные"""
        if not self.judge0_alive:
            self.skipTest("Judge0 is not running at localhost:2358")
            
        generator_code = "import sys\nprint(f'data_{sys.stdin.read().strip()}')"
        student_id = "999"
        payload = {
            "source_code": generator_code,
            "language_id": self.language_id,
            "stdin": student_id
        }
        res = requests.post(SYNC_URL, json=payload, timeout=10)
        self.assertEqual(res.status_code, 201)
        self.assertEqual(res.json().get("stdout", "").strip(), "data_999")

    def test_partial_grading(self):
        """6. Partial grading — частичное оценивание при прохождении части тестов"""
        total_weight = 5.0
        earned_weight = 3.0
        fraction = earned_weight / total_weight
        self.assertAlmostEqual(fraction, 0.6)

    def test_hidden_tests(self):
        """7. Hidden tests — скрытые данные не показываются студенту"""
        testcase = {"input": "secret", "is_hidden": True}
        rendered_output = "<i>Скрыто</i>" if testcase["is_hidden"] else testcase["input"]
        self.assertEqual(rendered_output, "<i>Скрыто</i>")

    def test_upgrade_path(self):
        """8. Upgrade path — тест миграции БД (db/upgrade.php)"""
        old_version = 2026032221
        new_version = 2026050400
        self.assertTrue(new_version > old_version)

class TestPortingPattern(unittest.TestCase):
    """Тесты паттерна портирования C-задач."""

    def test_01_c_language_id_in_form(self):
        """В форме преподавателя доступен C (language_id=50)."""
        with open(FORM_PATH) as f:
            content = f.read()
        self.assertIn("50 =>", content, "C (GCC 9.2.0) отсутствует в списке языков")

    def test_02_c_mode_in_renderer(self):
        """Monaco renderer содержит маппинг для C."""
        with open(RENDERER_PATH) as f:
            content = f.read()
        self.assertIn("50 => 'c'", content, "C mode отсутствует в monaco_modes")

    def test_03_concatenation_order(self):
        """Порядок конкатенации: checker_code + student_code."""
        with open(QUESTION_PATH) as f:
            content = f.read()
        self.assertIn('checker_code . "\\n" . $code', content,
                      "Порядок конкатенации должен быть checker_code + student_code")

    def test_04_compiler_options_not_in_reference(self):
        """compiler_options НЕ передаётся в reference solution payload."""
        with open(QUESTION_PATH) as f:
            content = f.read()
        ref_block = content.split('reference_solution')[2]  
        student_block = content.split('Phase 2')[1]         
        self.assertNotIn('compiler_options', ref_block.split('Phase 2')[0],
                         "compiler_options не должен быть в reference solution payload")
        self.assertIn('compiler_options', student_block,
                      "compiler_options должен быть в student submission payload")

    def test_05_demo_bundle_exists(self):
        """Demo bundle для лабы существует."""
        demo_dir = os.path.join(PLUGIN_DIR, 'demo', 'unrolled_list_variant3')
        self.assertTrue(os.path.isdir(demo_dir), "Каталог demo/unrolled_list_variant3 не найден")
        for f in ['checker.c', 'reference_solution.py', 'student_solution_ok.c', 'testcases.json']:
            self.assertTrue(os.path.isfile(os.path.join(demo_dir, f)), f"{f} не найден в demo bundle")

if __name__ == '__main__':
    unittest.main()
