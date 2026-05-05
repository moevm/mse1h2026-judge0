import unittest
import requests

JUDGE0_URL = "http://localhost:2358"
SYNC_URL = f"{JUDGE0_URL}/submissions?base64_encoded=false&wait=true"

class TestJudge0API(unittest.TestCase):
    
    def setUp(self):
        try:
            requests.get(f"{JUDGE0_URL}/system_info", timeout=5).raise_for_status()
            self.judge0_alive = True
        except Exception:
            self.judge0_alive = False

    def test_c_template_checker_plus_student(self):
        """C-шаблон с main() + функции студента → checker_code + student_code."""
        if not self.judge0_alive:
            self.skipTest("Judge0 is not running at localhost:2358")
            
        checker_code = '#include <stdio.h>\nint add(int a, int b);\nint main() { printf("%d\\n", add(2,3)); return 0; }'
        student_code = 'int add(int a, int b) { return a + b; }'
        full_code = checker_code + "\n" + student_code
        
        payload = {
            "source_code": full_code,
            "language_id": 50, # C
            "stdin": ""
        }
        res = requests.post(SYNC_URL, json=payload, timeout=10)
        self.assertEqual(res.status_code, 201)
        self.assertEqual(res.json().get("stdout", "").strip(), "5")

if __name__ == '__main__':
    unittest.main()
