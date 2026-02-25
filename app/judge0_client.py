import logging
import httpx
from typing import Dict, Any

logger = logging.getLogger(__name__)


class Judge0Client:
    def __init__(self, base_url: str = "http://localhost:2358", timeout: float = 20.0):
        self.base_url = base_url.rstrip("/")
        self.lang_ids: Dict[str, int] = {}
        # keep a reasonably permissive timeout for network and execution
        self._client = httpx.AsyncClient(timeout=timeout)

    async def close(self):
        await self._client.aclose()

    async def init_languages(self) -> None:
        try:
            logger.debug("Requesting languages from judge0 at %s/languages", self.base_url)
            r = await self._client.get(f"{self.base_url}/languages")
            r.raise_for_status()
            langs = r.json()
            logger.debug("Received %d languages from judge0", len(langs))

            python_id = max((l['id'] for l in langs if 'python' in l['name'].lower() and '3.' in l['name']), default=None)
            cpp_id = max((l['id'] for l in langs if 'c++' in l['name'].lower() or 'gcc' in l['name'].lower()), default=None)
            js_id = max((l['id'] for l in langs if 'javascript' in l['name'].lower() or 'node' in l['name'].lower()), default=None)

            if python_id:
                self.lang_ids['python'] = python_id
            if cpp_id:
                self.lang_ids['cpp'] = cpp_id
            if js_id:
                self.lang_ids['javascript'] = js_id

            logger.info("Initialized language ids: %s", self.lang_ids)
        except Exception as exc:
            logger.exception("Failed to init languages from judge0: %s", exc)
            raise

    async def submit(self, source_code: str, language_id: int, stdin: str) -> Any:
        payload = {"source_code": source_code, "language_id": language_id, "stdin": stdin}
        try:
            logger.debug("Submitting to judge0: lang_id=%s, stdin_len=%d, source_len=%d",
                         language_id, len(stdin) if stdin is not None else 0, len(source_code) if source_code is not None else 0)

            r = await self._client.post(
                f"{self.base_url}/submissions",
                params={"base64_encoded": "false", "wait": "true"},
                json=payload
            )
            logger.debug("Judge0 response status: %s", r.status_code)
            r.raise_for_status()
            jres = r.json()
            # Log a compact summary of the response for debugging
            try:
                status = jres.get('status', {})
                logger.info("Judge0 result: status=%s id=%s time=%s memory=%s",
                            status.get('description'), status.get('id'), jres.get('time'), jres.get('memory'))
            except Exception:
                logger.debug("Full judge0 response: %s", jres)

            return jres
        except Exception as exc:
            logger.exception("Failed to submit to judge0: %s", exc)
            raise