#!/bin/sh
# PreToolUse (Bash): block destructive commands, .env.local access and host PHP/Composer.
# Exit 2 + stderr tells the agent why and what to do instead.
CMD=$(python3 -c 'import json,sys; print(json.load(sys.stdin).get("tool_input",{}).get("command",""))')

block() { echo "Blocked by .claude/hooks/block-dangerous.sh: $1" >&2; exit 2; }
has() { printf '%s' "$CMD" | grep -Eq "$1"; }

has 'doctrine:(schema|database):drop' && block "dropping the schema/database is destructive. Ask the user."
has 'docker[ -]compose.*[[:space:]]down([[:space:]].*)?[[:space:]]-[a-zA-Z]*v|docker[ -]compose.*--volumes' && block "'docker compose down -v' deletes volumes (Postgres/Kafka data). Ask the user."
has 'docker[[:space:]]+volume[[:space:]]+(rm|prune)' && block "removing Docker volumes deletes data. Ask the user."
has '(^|[;&|(][[:space:]]*)rm[[:space:]]+(-[a-zA-Z]*r[a-zA-Z]*f|-[a-zA-Z]*f[a-zA-Z]*r|-r[[:space:]]+-f|-f[[:space:]]+-r|--recursive)' && block "rm -rf is not allowed. Ask the user."
has 'kafka-topics.*--delete' && block "deleting Kafka topics is not allowed. Ask the user."
has 'kafka-consumer-groups.*--(reset-offsets|delete)' && block "resetting/deleting consumer group offsets is not allowed. Ask the user."
has '\.env\.local' && block ".env.local holds real secrets and is off limits."
has '(^|[;&|(][[:space:]]*)(sudo[[:space:]]+)?(php|composer)([[:space:]]|$)' && block "no PHP/Composer on the host. Use: docker compose exec -T php <command> (ADR-013)."
exit 0
