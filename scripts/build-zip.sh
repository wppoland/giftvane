#!/usr/bin/env bash
# Build a clean, installable ${NAME}.zip, honouring .distignore.
# Runtime is self-contained (bundled autoload/lib); vendor/ is dev-only and
# excluded by .distignore, so no composer step is needed here.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
NAME="$(basename "$ROOT_DIR")"
OUT_DIR="${1:-/tmp/${NAME}-build}"
STAGE="${OUT_DIR}/${NAME}"

rm -rf "${OUT_DIR}"
mkdir -p "${STAGE}"

rsync -a --exclude-from="${ROOT_DIR}/.distignore" \
    --exclude '.git' --exclude 'node_modules' --exclude '.DS_Store' \
    "${ROOT_DIR}/" "${STAGE}/"

find "${STAGE}" -name '.DS_Store' -delete

( cd "${OUT_DIR}" && zip -rqX "/tmp/${NAME}.zip" "${NAME}" -x '*.DS_Store' )
echo "Built /tmp/${NAME}.zip from ${STAGE}"
