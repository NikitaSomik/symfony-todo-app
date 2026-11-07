#!/usr/bin/env python3
"""Stop: before Claude finishes, run bin/verify-fast; while it fails, Claude keeps working.

After three refusals in a row Claude is let go, and Nikita is told the checks are still
red: past that point Claude is more likely to bend a test than to fix the cause. A failure
inside the hook lets Claude stop; CI checks the pull request anyway.
"""

import json
import os
import re
import sys

from common import PROJECT_DIR, read_input, run

ATTEMPTS = 3
TAIL = 80


def counter_file(session):
    directory = os.path.join(PROJECT_DIR, "var", "claude")
    os.makedirs(directory, exist_ok=True)
    return os.path.join(directory, "stop-%s" % re.sub(r"[^\w-]", "", session or "session"))


def read_count(path):
    try:
        with open(path) as f:
            return int(f.read().strip() or 0)
    except (OSError, ValueError):
        return 0


def main():
    data = read_input()
    counter = counter_file(data.get("session_id"))

    result = run([os.path.join(PROJECT_DIR, "bin", "verify-fast")], timeout=600)
    if result.returncode == 0:
        if os.path.exists(counter):
            os.remove(counter)
        return 0

    if result.returncode == 3 and data.get("stop_hook_active"):
        if os.path.exists(counter):
            os.remove(counter)
        print(json.dumps({"systemMessage": "The app container is stopped; nothing was checked."}))
        return 0

    attempt = read_count(counter) + 1
    if attempt > ATTEMPTS:
        os.remove(counter)
        print(json.dumps({"systemMessage": "verify-fast is still red after %d attempts; Claude stopped anyway." % ATTEMPTS}))
        return 0
    with open(counter, "w") as f:
        f.write(str(attempt))

    output = (result.stdout + result.stderr).strip().splitlines()
    if result.returncode == 3:
        message = (
            "The checks could not run: the app container is stopped. Do not report the work as "
            "checked. Say so to Nikita, or start it with 'make up' and finish."
        )
    else:
        message = (
            "bin/verify-fast failed (attempt %d of %d). Fix the cause in the code; do not change "
            "tests, check configs or add ignores to get green. Last lines:\n%s"
            % (attempt, ATTEMPTS, "\n".join(output[-TAIL:]))
        )
    print(message, file=sys.stderr)
    return 2


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception:
        sys.exit(0)
