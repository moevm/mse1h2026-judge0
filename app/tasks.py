import os
from typing import Dict, List

class TaskManager:
    def __init__(self, tests_dir: str = "tests"):
        self.tests_dir = os.path.abspath(tests_dir)
        self.task_data: Dict = {
            "greedy": {"description": "Считайте число N. В следующей строке считайте N целых чисел. Выведите их сумму."},
            "sorting": {"description": "Считайте число N. В следующей строке считайте N целых чисел. Выведите их отсортированными по возрастанию."},
            "dp": {"description": "Считайте число N. В следующей строке считайте N целых чисел. Найдите максимальную сумму подмассива."}
        }

    def list_tasks(self):
        return [
            {"task": t, "available_tests": self.discover_test_count(t)}
            for t in self.task_data
        ]

    def discover_test_count(self, task: str) -> int:
        base = os.path.join(self.tests_dir, task)
        if not os.path.isdir(base):
            return 0
        i = 1
        while os.path.isfile(os.path.join(base, f"{i}.in")) and os.path.isfile(os.path.join(base, f"{i}.out")):
            i += 1
        return i - 1

    def load_test_case(self, task: str, index: int):
        base = os.path.join(self.tests_dir, task)
        with open(os.path.join(base, f"{index}.in"), encoding="utf-8") as f:
            inp = f.read()
        with open(os.path.join(base, f"{index}.out"), encoding="utf-8") as f:
            out = f.read()
        return inp, out

    def validate_task(self, task: str) -> bool:
        return task in self.task_data and self.discover_test_count(task) > 0