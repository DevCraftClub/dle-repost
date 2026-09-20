#!/usr/bin/env bash
# Зеркало на GitHub: коммит поверх github/main + ветка releases/v* + запрос на слияние.
# Нельзя пушить историю Forgejo как есть — у GitHub main другой предок, сравнение ломается.
set -euo pipefail

test -n "${GH_USER:-}" || { echo "Секрет GH_USER пуст"; exit 1; }
test -n "${GH_ACCESS_TOKEN:-}" || { echo "Секрет GH_ACCESS_TOKEN пуст"; exit 1; }
test -f manifest.json || { echo "manifest.json не найден"; ls -la; exit 1; }

VERSION="$(jq -r '.version' manifest.json)"
test -n "${VERSION}" && test "${VERSION}" != "null"

BRANCH="releases/v${VERSION}"
TAG="v-${VERSION}-auto"
REPO="DevCraftClub/dle-repost"
AUTH_URL="https://${GH_USER}:${GH_ACCESS_TOKEN}@github.com/${REPO}.git"
API="https://api.github.com/repos/${REPO}"
AUTH_HDR="Authorization: Bearer ${GH_ACCESS_TOKEN}"
ACCEPT_HDR="Accept: application/vnd.github+json"

echo "VERSION=${VERSION} BRANCH=${BRANCH} TAG=${TAG}"

SRC="${CI_WORKSPACE:-.}"
cd "$SRC"

WORKDIR="$(mktemp -d)"
PAYLOAD="$(mktemp -d)"
trap 'rm -rf "$WORKDIR" "$PAYLOAD"' EXIT

RSYNC_EXCLUDES=(--exclude='.git/' --exclude='.woodpecker/.tmp/')
if [ -f .publishignore ]; then
	RSYNC_EXCLUDES+=(--exclude='.publishignore' --exclude-from=.publishignore)
fi
rsync -a "${RSYNC_EXCLUDES[@]}" ./ "$PAYLOAD/"

if ! git clone --depth 1 --branch main "$AUTH_URL" "$WORKDIR" 2>/tmp/gh-clone.err; then
	echo "Не удалось клонировать github/main:"
	cat /tmp/gh-clone.err
	exit 1
fi

cd "$WORKDIR"
git config user.email "ci@devcraft.club"
git config user.name "DevBot CI"
git checkout -B "$BRANCH"

find . -mindepth 1 -maxdepth 1 ! -name '.git' -exec rm -rf {} +
cp -a "$PAYLOAD"/. .

git add -A
if git diff --cached --quiet; then
	echo "Файлы уже совпадают с github/main — ветка без нового коммита"
else
	git commit -m "Publish v${VERSION}"
fi

git push -u origin "HEAD:${BRANCH}" --force

COMPARE_URL="https://github.com/${REPO}/compare/main...${BRANCH}"
echo "Сравнение: ${COMPARE_URL}"

PR_BODY="$(printf '%s\n' \
	"Автоматический релиз v${VERSION} (Forgejo → GitHub)." \
	"" \
	"Ветка собрана поверх \`main\`, не из истории git.hrdr.dev." \
	"" \
	"Сравнение: ${COMPARE_URL}"
)"

PR_JSON="$(curl -sS -X POST "${API}/pulls" \
	-H "$AUTH_HDR" -H "$ACCEPT_HDR" -H "Content-Type: application/json" \
	-d "$(jq -n \
		--arg title "Release v${VERSION}" \
		--arg head "${BRANCH}" \
		--arg body "${PR_BODY}" \
		'{title:$title, head:$head, base:"main", body:$body}')"
)"

PR_NUMBER="$(printf '%s' "$PR_JSON" | jq -r '.number // empty')"
if [ -z "$PR_NUMBER" ] || [ "$PR_NUMBER" = "null" ]; then
	EXISTING="$(printf '%s' "$PR_JSON" | jq -r '.errors[0].message // .message // empty')"
	echo "Запрос на слияние не создан: ${EXISTING:-$PR_JSON}"
	echo "Если он уже открыт — это нормально. Сравнение: ${COMPARE_URL}"
else
	echo "Запрос на слияние #${PR_NUMBER}: https://github.com/${REPO}/pull/${PR_NUMBER}"
fi

REL_JSON="$(curl -sS -X POST "${API}/releases" \
	-H "$AUTH_HDR" -H "$ACCEPT_HDR" -H "Content-Type: application/json" \
	-d "$(jq -n \
		--arg tag "${TAG}" \
		--arg target "${BRANCH}" \
		--arg name "Release v${VERSION}" \
		--arg body "Автоматический релиз версии ${VERSION}" \
		'{tag_name:$tag, target_commitish:$target, name:$name, body:$body}')"
)"
if printf '%s' "$REL_JSON" | jq -e '.id' >/dev/null 2>&1; then
	echo "Релиз: $(printf '%s' "$REL_JSON" | jq -r '.html_url')"
else
	echo "Релиз с тегом ${TAG} уже есть или не создан: $(printf '%s' "$REL_JSON" | jq -r '.message // .')"
fi
