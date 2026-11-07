#!/usr/bin/env python3
"""PostToolUse: after Claude edits a PHP file, fix its style and run PHPStan on it.

PHPStan errors go back to Claude at once (exit 2), so they are fixed while the change is
fresh instead of at the end. With the app container stopped the hook stays quiet: the Stop
hook reports that nothing could be checked.
"""

import sys

from common import app_is_running, read_input, relative, run


def main():
    data = read_input()
    path = relative(data.get("tool_input", {}).get("file_path"))
    if not path or not path.endswith(".php") or not path.startswith(("src/", "tests/", "fixtures/")):
        return 0
    if not app_is_running():
        return 0

    app = ["docker", "compose", "exec", "-T", "app"]
    run(app + ["vendor/bin/php-cs-fixer", "fix", "--quiet", "--path-mode=intersection", "--", path])

    if path.startswith(("src/", "fixtures/")):
        result = run(app + ["vendor/bin/phpstan", "analyse", "--no-progress", "--error-format=raw", "--", path])
        if result.returncode != 0:
            print("PHPStan on %s:\n%s" % (path, (result.stdout + result.stderr).strip()), file=sys.stderr)
            return 2
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception:
        sys.exit(0)
