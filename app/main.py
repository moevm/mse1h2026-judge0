from fastapi import FastAPI, HTTPException
from fastapi.responses import FileResponse, JSONResponse
from fastapi.staticfiles import StaticFiles
from contextlib import asynccontextmanager
from pathlib import Path
import logging

from judge0_client import Judge0Client
from tasks import TaskManager
from schemas import SubmitRequest, SubmitResponse

logging.basicConfig(level=logging.DEBUG, format="%(asctime)s %(levelname)-8s %(name)s: %(message)s")
logger = logging.getLogger("mini-judge")

judge = Judge0Client()
tasks = TaskManager()


@asynccontextmanager
async def lifespan(app: FastAPI):
    try:
        await judge.init_languages()
    except Exception as e:
        logger.warning("judge0 languages init failed: %s", e)
    yield
    await judge.close()


app = FastAPI(lifespan=lifespan)

app.mount("/static", StaticFiles(directory="static"), name="static")


@app.get("/")
def index():
    return FileResponse("static/index.html")


@app.get("/tasks")
def list_tasks():
    return tasks.list_tasks()


@app.get("/tasks/{task_name}")
def task_info(task_name: str):
    if task_name not in tasks.task_data:
        raise HTTPException(404, "task not found")
    return tasks.task_data[task_name]


@app.post("/submit", response_model=SubmitResponse)
async def submit(req: SubmitRequest):
    logger.debug("Received submit request: task=%s language=%s source_len=%d",
                 req.task, req.language, len(req.source_code) if req.source_code else 0)

    lang_key = req.language.lower()
    if lang_key not in judge.lang_ids:
        logger.error("Unsupported language requested: %s", req.language)
        raise HTTPException(400, "unsupported language")
    language_id = judge.lang_ids[lang_key]

    if not tasks.validate_task(req.task):
        logger.error("Task not found: %s", req.task)
        raise HTTPException(400, "task not found")

    total = tasks.discover_test_count(req.task)
    indices = req.run_only_tests or list(range(1, total + 1))

    results = []
    passed = 0

    logger.debug("Running tests %s for task %s (total available: %d)", indices, req.task, total)

    for idx in indices:
        try:
            stdin, expected = tasks.load_test_case(req.task, idx)
        except FileNotFoundError:
            logger.warning("Missing test files for task=%s idx=%d", req.task, idx)
            results.append({"test": idx, "verdict": "missing_test_files"})
            continue

        logger.debug("Submitting test %d (stdin_len=%d expected_len=%d)", idx, len(stdin), len(expected))
        jres = await judge.submit(req.source_code, language_id, stdin)
        logger.debug("Full judge0 response: %s", jres)

        status = jres.get("status", {})
        status_id = status.get("id")
        status_desc = status.get("description", "Unknown")

        stdout = (jres.get("stdout") or "").replace("\r\n", "\n").strip()
        expected = expected.replace("\r\n", "\n").strip()
        stderr = (jres.get("stderr") or "").replace("\r\n", "\n").strip()
        compile_output = (jres.get("compile_output") or "").replace("\r\n", "\n").strip()

        if status_id == 3:
            verdict = "Accepted" if stdout == expected else "Wrong Answer"
        elif status_id == 4:
            verdict = "Wrong Answer"
        elif status_id == 5:
            verdict = "Time Limit Exceeded"
        elif status_id == 6:
            verdict = "Compilation Error"
        elif status_id in (7,8,9,10,11,12):
            verdict = "Runtime Error"
        elif status_id == 13:
            verdict = "Memory Limit Exceeded"
        elif status_id == 14:
            verdict = "Output Limit Exceeded"
        else:
            verdict = status_desc

        if verdict == "Accepted":
            passed += 1

        logger.info("Test %d verdict=%s status=%s time=%s memory=%s", idx, verdict, status_desc, jres.get("time"), jres.get("memory"))

        results.append({
            "test": idx,
            "verdict": verdict,
            "judge0_status": status_desc,
            "stdout": stdout,
            "expected": expected,
            "stderr": stderr,
            "compile_output": compile_output,
            "time": jres.get("time"),
            "memory": jres.get("memory")
        })

    return JSONResponse({
        "task": req.task,
        "language": req.language,
        "total_tests_run": len(indices),
        "passed": passed,
        "results": results
    })