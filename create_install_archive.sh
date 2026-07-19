#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MANIFEST="${SCRIPT_DIR}/manifest.json"

if [[ ! -f "$MANIFEST" ]]; then
	echo "manifest.json not found in ${SCRIPT_DIR}" >&2
	exit 1
fi

ARCHIVE_NAME=$(python3 - "$MANIFEST" <<'PY'
import json
import re
import sys

CYRILLIC = {
	"а": "a", "б": "b", "в": "v", "г": "g", "д": "d", "е": "e", "ё": "yo",
	"ж": "zh", "з": "z", "и": "i", "й": "y", "к": "k", "л": "l", "м": "m",
	"н": "n", "о": "o", "п": "p", "р": "r", "с": "s", "т": "t", "у": "u",
	"ф": "f", "х": "h", "ц": "ts", "ч": "ch", "ш": "sh", "щ": "sch",
	"ъ": "", "ы": "y", "ь": "", "э": "e", "ю": "yu", "я": "ya",
}


def transliterate(text: str) -> str:
	result = []
	for char in text:
		lower = char.lower()
		if lower in CYRILLIC:
			result.append(CYRILLIC[lower])
		else:
			result.append(char)
	return "".join(result)


def slugify(name: str) -> str:
	text = transliterate(name).lower()
	text = re.sub(r"[^a-z0-9]+", "_", text)
	text = re.sub(r"_+", "_", text).strip("_")
	return text or "plugin"


def sanitize_version(version: str) -> str:
	return re.sub(r"[^a-zA-Z0-9._-]+", "_", str(version))


with open(sys.argv[1], encoding="utf-8") as manifest_file:
	data = json.load(manifest_file)

name = data.get("name")
version = data.get("version")
if not name or not version:
	raise SystemExit("manifest.json must contain name and version")

print(f"install_{slugify(name)}_{sanitize_version(version)}.zip")
PY
)

mkdir -p temp
rsync -avz upload/ temp/ >&2
find temp -name '.gitkeep' -type f -delete
if [[ -z "$(find temp -type f -print -quit)" ]]; then
	echo "No files to package in upload/ after removing .gitkeep placeholders" >&2
	rm -rf temp
	exit 1
fi
(
	cd temp
	zip -r temp_archive.zip . >&2
)
cp -f temp/temp_archive.zip "${SCRIPT_DIR}/${ARCHIVE_NAME}"
rm -rf temp
echo "$ARCHIVE_NAME"
