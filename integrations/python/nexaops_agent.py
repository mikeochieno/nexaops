"""
NexaOps Python Log Agent
=========================
Drop-in logging handler that sends logs from Python apps (like ai_app)
to the NexaOps management platform.

Usage:
    from integrations.python.nexaops_agent import NexaOpsHandler

    # As a logging handler
    handler = NexaOpsHandler(api_key='your_key', app_name='ai_app')
    logging.getLogger().addHandler(handler)

    # Direct usage
    agent = NexaOpsAgent(api_key='your_key', app_name='ai_app')
    agent.log('QUERY_COMPLETE', 'SQL query returned 42 rows', user_id='admin')
    agent.ai_usage(model='gpt-4o', tokens_prompt=500, tokens_completion=200, cost_usd=0.01)
"""

import json
import logging
import threading
import urllib.request
import urllib.error
from datetime import datetime
from typing import Any, Optional


class NexaOpsAgent:
    """Non-blocking log sender to NexaOps platform."""

    def __init__(
        self,
        api_key: str,
        app_name: str = "python_app",
        base_url: str = "http://localhost/app_manager/api",
        timeout: int = 5,
    ):
        self.api_key = api_key
        self.app_name = app_name
        self.base_url = base_url.rstrip("/")
        self.timeout = timeout

    def log(
        self,
        action: str,
        description: str = "",
        level: str = "info",
        user_id: str = "system",
        metadata: Optional[dict] = None,
    ) -> None:
        payload = {
            "api_key": self.api_key,
            "logs": [
                {
                    "user_id": user_id,
                    "action": action,
                    "description": description,
                    "level": level,
                    "metadata": metadata or {},
                    "created_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                }
            ],
        }
        self._send_async("/collect/log", payload)

    def log_batch(self, logs: list[dict]) -> None:
        entries = []
        for l in logs:
            entries.append(
                {
                    "user_id": l.get("user_id", "system"),
                    "action": l.get("action", "unknown"),
                    "description": l.get("description", ""),
                    "level": l.get("level", "info"),
                    "metadata": l.get("metadata", {}),
                    "created_at": l.get("created_at", datetime.now().strftime("%Y-%m-%d %H:%M:%S")),
                }
            )
        self._send_async("/collect/log", {"api_key": self.api_key, "logs": entries})

    def ai_usage(
        self,
        model: str = "unknown",
        provider: str = "openai",
        operation: str = "chat",
        tokens_prompt: int = 0,
        tokens_completion: int = 0,
        cost_usd: float = 0.0,
        latency_ms: int = 0,
        success: bool = True,
        user_id: str = "system",
        metadata: Optional[dict] = None,
    ) -> None:
        payload = {
            "api_key": self.api_key,
            "app_id": None,  # resolved server-side by api_key
            "provider": provider,
            "model": model,
            "operation": operation,
            "tokens_prompt": tokens_prompt,
            "tokens_completion": tokens_completion,
            "cost_usd": cost_usd,
            "latency_ms": latency_ms,
            "success": 1 if success else 0,
            "user_id": user_id,
            "metadata": metadata or {},
        }
        self._send_async("/collect/ai-usage", payload)

    def _send_async(self, path: str, payload: dict) -> None:
        """Fire-and-forget HTTP POST in a daemon thread."""

        def _do_send():
            try:
                data = json.dumps(payload).encode("utf-8")
                req = urllib.request.Request(
                    f"{self.base_url}{path}",
                    data=data,
                    headers={
                        "Content-Type": "application/json",
                        "X-API-Key": self.api_key,
                    },
                    method="POST",
                )
                with urllib.request.urlopen(req, timeout=self.timeout) as resp:
                    pass
            except Exception:
                pass  # non-blocking; failures are silent

        t = threading.Thread(target=_do_send, daemon=True)
        t.start()


class NexaOpsHandler(logging.Handler):
    """Python logging.Handler that forwards log records to NexaOps."""

    def __init__(self, api_key: str, app_name: str = "python_app", **kwargs):
        super().__init__()
        self.agent = NexaOpsAgent(api_key=api_key, app_name=app_name, **kwargs)

    def emit(self, record: logging.LogRecord) -> None:
        try:
            level = record.levelname.lower()
            self.agent.log(
                action="PYTHON_LOG",
                description=self.format(record),
                level=level,
                user_id=getattr(record, "user_id", "system"),
                metadata={
                    "module": record.module,
                    "funcName": record.funcName,
                    "lineno": record.lineno,
                },
            )
        except Exception:
            pass
