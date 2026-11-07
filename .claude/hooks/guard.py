#!/usr/bin/env python3
"""PreToolUse: refuses the edits and commands that would loosen the checks or leave the task.

- the checks themselves and these rails are not edited (lift with CLAUDE_GUARD_UNLOCK=1
  when starting Claude);
- a migration already on main is not edited: a new migration changes the schema;
- no new @phpstan-ignore and no skipped tests;
- with .claude/scope.txt present, only the paths it lists may change;
- no shell command that writes over a protected file or throws away work.

A refusal comes back to Claude with the reason. A failure inside the guard lets the call
through: a broken guard must not stop the work, and CI still checks everything.
"""

import fnmatch
import json
import os
import re
import sys

from common import PROJECT_DIR, read_input, relative, run

PROTECTED = [
    "phpstan.neon", "phpstan.dist.neon", "phpstan-baseline.neon",
    "deptrac.php", "deptrac.yaml",
    ".php-cs-fixer.php", ".php-cs-fixer.dist.php",
    "phpunit.xml", "phpunit.dist.xml",
    "composer.json", "composer.lock",
    "Makefile", "bin/verify-fast",
    ".github/*",
    ".claude/settings.json", ".claude/hooks/*",
]

SUPPRESSIONS = re.compile(r"@phpstan-ignore|phpstan-ignore-(?:next-)?line|markTestSkipped|markTestIncomplete")

DESTRUCTIVE = [
    (re.compile(r"\bgit\s+reset\s+--hard\b"), "git reset --hard throws away uncommitted work."),
    (re.compile(r"\bgit\s+clean\s+-\w*f"), "git clean -f deletes untracked files."),
    (re.compile(r"\bdocker\s+compose\s+down\b[^|;&]*(?:\s-v\b|--volumes)"), "This deletes the database volume."),
    (re.compile(r"doctrine:(?:schema|database):drop"), "Dropping the schema or database is Nikita's call."),
]

SHELL_WRITE = r"(?:(?:\bsed\s+-\w*i|\bperl\s+-\w*i|\b(?:mv|cp|rm|truncate|chmod)\b)[^|;&]*\s|>>?\s*|\btee\s+(?:-a\s+)?)"


def deny(reason):
    print(json.dumps({
        "hookSpecificOutput": {
            "hookEventName": "PreToolUse",
            "permissionDecision": "deny",
            "permissionDecisionReason": reason,
        }
    }))
    return 0


def is_protected(path):
    return any(fnmatch.fnmatch(path, pattern) for pattern in PROTECTED)


def on_main(path):
    for ref in ("origin/main", "main"):
        if run(["git", "cat-file", "-e", "%s:%s" % (ref, path)], timeout=10).returncode == 0:
            return True
    return False


def scope():
    try:
        with open(os.path.join(PROJECT_DIR, ".claude", "scope.txt")) as f:
            lines = [line.strip() for line in f]
    except OSError:
        return None
    patterns = [line for line in lines if line and not line.startswith("#")]
    return patterns or None


def suppressions(text):
    return len(SUPPRESSIONS.findall(text or ""))


def added_suppression(tool, tool_input, path):
    if tool == "Edit":
        return suppressions(tool_input.get("new_string")) > suppressions(tool_input.get("old_string"))
    if tool == "MultiEdit":
        edits = tool_input.get("edits", [])
        return sum(suppressions(e.get("new_string")) for e in edits) > sum(suppressions(e.get("old_string")) for e in edits)
    if tool == "Write":
        try:
            with open(os.path.join(PROJECT_DIR, path)) as f:
                before = f.read()
        except OSError:
            before = ""
        return suppressions(tool_input.get("content")) > suppressions(before)
    return False


def check_edit(tool, tool_input):
    path = relative(tool_input.get("file_path") or tool_input.get("notebook_path"))
    if path is None:
        return 0

    unlocked = os.environ.get("CLAUDE_GUARD_UNLOCK") == "1"
    if is_protected(path) and not unlocked:
        return deny(
            "%s is part of the checks or of the rails around them. Fix the code instead; if the "
            "check itself is wrong, say why and let Nikita change it." % path
        )
    if path.startswith("migrations/") and on_main(path):
        return deny("%s is already on main and may have run somewhere. Write a new migration instead." % path)
    if path.endswith(".php") and added_suppression(tool, tool_input, path):
        return deny(
            "Silencing PHPStan or skipping a test hides the problem. Fix the cause, or explain "
            "to Nikita why the check is wrong."
        )
    patterns = scope()
    if patterns and not any(fnmatch.fnmatch(path, p) for p in patterns):
        return deny(
            "%s is outside the agreed scope (.claude/scope.txt). Ask Nikita before widening it." % path
        )
    return 0


def check_command(command):
    for pattern, reason in DESTRUCTIVE:
        if pattern.search(command):
            return deny(reason)
    if os.environ.get("CLAUDE_GUARD_UNLOCK") == "1":
        return 0
    for pattern in PROTECTED:
        literal = re.escape(pattern.rstrip("*").rstrip("/"))
        if re.search(SHELL_WRITE + r"\S*" + literal, command):
            return deny(
                "The command writes to %s, which is part of the checks or of the rails around them." % pattern
            )
    return 0


def main():
    data = read_input()
    tool = data.get("tool_name", "")
    tool_input = data.get("tool_input", {})
    if tool == "Bash":
        return check_command(tool_input.get("command", ""))
    if tool in ("Edit", "MultiEdit", "Write", "NotebookEdit"):
        return check_edit(tool, tool_input)
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception:
        sys.exit(0)
