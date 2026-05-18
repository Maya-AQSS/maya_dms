#!/usr/bin/env bash
# ops/audit-task.sh — Mini audit after task completion
#
# Runs lightweight checks on the current project directory.
# Designed to be fast (<10s) and non-blocking.
# Returns structured output for memory logging.
#
# Usage: ops/audit-task.sh [project_path] [task_description]

set -euo pipefail

PROJECT="${1:-.}"
TASK_DESC="${2:-tarea sin nombre}"
AUDIT_LOG="${PROJECT}/.claude/memory/audit-log.md"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M')

cd "$PROJECT"

PASS=0
WARN=0
FAIL=0
FINDINGS=""

# --- Check 1: Secrets scan ---
SECRETS_PATTERN='(sk-[a-zA-Z0-9]{20,}|AKIA[0-9A-Z]{16}|ghp_[a-zA-Z0-9]{36}|password\s*=\s*["\x27][^"\x27]{4,})'
if FOUND=$(grep -rn --include="*.ts" --include="*.tsx" --include="*.js" --include="*.py" --include="*.php" --include="*.go" --include="*.env" -E "$SECRETS_PATTERN" . 2>/dev/null | grep -v node_modules | grep -v vendor | grep -v '.git/' | head -5); then
    if [ -n "$FOUND" ]; then
        FAIL=$((FAIL + 1))
        FINDINGS="${FINDINGS}\n  ❌ SECRETS: Posibles credenciales encontradas"
    else
        PASS=$((PASS + 1))
    fi
else
    PASS=$((PASS + 1))
fi

# --- Check 2: Console.log / print debugging ---
if FOUND=$(grep -rn --include="*.ts" --include="*.tsx" --include="*.js" --include="*.jsx" 'console\.log' . 2>/dev/null | grep -v node_modules | grep -v '\.test\.' | grep -v '\.spec\.' | grep -v __tests__ | wc -l); then
    if [ "$FOUND" -gt 5 ]; then
        WARN=$((WARN + 1))
        FINDINGS="${FINDINGS}\n  ⚠️  DEBUG: ${FOUND} console.log encontrados"
    else
        PASS=$((PASS + 1))
    fi
else
    PASS=$((PASS + 1))
fi

# --- Check 3: Tests exist and pass (if test runner available) ---
if [ -f "package.json" ] && grep -q '"test"' package.json 2>/dev/null; then
    if npm test --silent 2>/dev/null; then
        PASS=$((PASS + 1))
    else
        WARN=$((WARN + 1))
        FINDINGS="${FINDINGS}\n  ⚠️  TESTS: npm test falló o no configurado"
    fi
elif [ -f "composer.json" ] && command -v php &>/dev/null; then
    if php artisan test --no-interaction 2>/dev/null; then
        PASS=$((PASS + 1))
    else
        WARN=$((WARN + 1))
        FINDINGS="${FINDINGS}\n  ⚠️  TESTS: php artisan test falló"
    fi
elif [ -f "go.mod" ]; then
    if go test ./... 2>/dev/null; then
        PASS=$((PASS + 1))
    else
        WARN=$((WARN + 1))
        FINDINGS="${FINDINGS}\n  ⚠️  TESTS: go test falló"
    fi
elif [ -f "pyproject.toml" ] || [ -f "requirements.txt" ]; then
    if python -m pytest --tb=no -q 2>/dev/null; then
        PASS=$((PASS + 1))
    else
        WARN=$((WARN + 1))
        FINDINGS="${FINDINGS}\n  ⚠️  TESTS: pytest falló"
    fi
else
    PASS=$((PASS + 1))  # No test runner detected, skip
fi

# --- Check 4: Lint (if available) ---
if [ -f "package.json" ] && grep -q '"lint"' package.json 2>/dev/null; then
    if npm run lint --silent 2>/dev/null; then
        PASS=$((PASS + 1))
    else
        WARN=$((WARN + 1))
        FINDINGS="${FINDINGS}\n  ⚠️  LINT: npm run lint reportó errores"
    fi
else
    PASS=$((PASS + 1))  # No linter configured, skip
fi

# --- Check 5: Uncommitted changes ---
if git rev-parse --is-inside-work-tree &>/dev/null; then
    STAGED=$(git diff --cached --name-only 2>/dev/null | wc -l)
    UNSTAGED=$(git diff --name-only 2>/dev/null | wc -l)
    if [ "$UNSTAGED" -gt 0 ]; then
        WARN=$((WARN + 1))
        FINDINGS="${FINDINGS}\n  ⚠️  GIT: ${UNSTAGED} archivos modificados sin stage"
    else
        PASS=$((PASS + 1))
    fi
fi

# --- Results ---
TOTAL=$((PASS + WARN + FAIL))
STATUS="✅ PASS"
[ "$FAIL" -gt 0 ] && STATUS="❌ FAIL"
[ "$WARN" -gt 2 ] && STATUS="⚠️  WARN"

RESULT="${STATUS} — ${PASS}/${TOTAL} checks passed (${WARN} warnings, ${FAIL} failures)"

echo ""
echo "── Audit: ${TASK_DESC} ──"
echo "$RESULT"
if [ -n "$FINDINGS" ]; then
    echo -e "$FINDINGS"
fi
echo ""

# --- Append to audit log ---
mkdir -p "$(dirname "$AUDIT_LOG")"
{
    echo "### ${TIMESTAMP} — ${TASK_DESC}"
    echo "${RESULT}"
    if [ -n "$FINDINGS" ]; then
        echo -e "$FINDINGS"
    fi
    echo ""
} >> "$AUDIT_LOG"

# Keep log under 200 lines
if [ -f "$AUDIT_LOG" ] && [ "$(wc -l < "$AUDIT_LOG")" -gt 200 ]; then
    tail -100 "$AUDIT_LOG" > "${AUDIT_LOG}.tmp"
    mv "${AUDIT_LOG}.tmp" "$AUDIT_LOG"
fi

# Exit code for pipeline integration
[ "$FAIL" -gt 0 ] && exit 1
exit 0
