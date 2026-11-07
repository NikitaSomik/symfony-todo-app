"""Shared pieces of the Claude Code hooks. Standard library only: hooks run on the host."""

import json
import os
import subprocess
import sys

PROJECT_DIR = os.environ.get("CLAUDE_PROJECT_DIR") or os.getcwd()


def read_input():
    return json.load(sys.stdin)


def relative(path):
    """The path relative to the project, or None when it lies outside it."""
    if not path:
        return None
    absolute = os.path.realpath(os.path.join(PROJECT_DIR, path))
    root = os.path.realpath(PROJECT_DIR)
    if absolute != root and not absolute.startswith(root + os.sep):
        return None
    return os.path.relpath(absolute, root)


def run(args, timeout=120):
    return subprocess.run(args, cwd=PROJECT_DIR, capture_output=True, text=True, timeout=timeout)


def app_is_running():
    result = run(["docker", "compose", "ps", "--status", "running", "--services"], timeout=15)
    return result.returncode == 0 and "app" in result.stdout.split()
