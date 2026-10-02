#!/usr/bin/env bash
# Собирает установочный архив Joomla: dist/mod_dharma_form-<версия>.zip
# Перед сборкой пересобирает media/readme.html из README.md.
# Запуск из любого места:  tools/build.sh
set -euo pipefail

cd "$(dirname "$0")/.."

python3 tools/readme2html.py

VERSION=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' mod_dharma_form.xml | head -1)
OUT="dist/mod_dharma_form-${VERSION}.zip"

mkdir -p dist
rm -f "$OUT"

# Манифест должен лежать в корне архива; служебные файлы репозитория не включаем.
zip -X -q -r "$OUT" mod_dharma_form.xml README.md language media services src tmpl \
  -x '*.DS_Store' -x '__MACOSX/*'

echo "Готово: $OUT ($(du -h "$OUT" | cut -f1))"
