from pydantic import BaseModel
from typing import List, Optional

class SubmitRequest(BaseModel):
    task: str
    language: str
    source_code: str
    run_only_tests: Optional[List[int]] = None

class TestResult(BaseModel):
    test: int
    verdict: str
    judge0_status: Optional[str] = None
    stdout: Optional[str] = None
    expected: Optional[str] = None
    stderr: Optional[str] = None
    compile_output: Optional[str] = None
    time: Optional[str] = None
    memory: Optional[str] = None

class SubmitResponse(BaseModel):
    task: str
    language: str
    total_tests_run: int
    passed: int
    results: List[TestResult]