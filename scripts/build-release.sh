#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="$ROOT_DIR/dist"
STAGING_DIR="$DIST_DIR/staging"
PACKAGE_DIR="$STAGING_DIR/wp-home-inference"
ZIP_PATH="$DIST_DIR/wp-home-inference.zip"

rm -rf "$STAGING_DIR" "$ZIP_PATH"
mkdir -p "$PACKAGE_DIR"

rsync -a \
	--exclude '.git' \
	--exclude '.github' \
	--exclude '.claude' \
	--exclude '.codex' \
	--exclude 'dist' \
	--exclude 'node_modules' \
	--exclude 'scripts' \
	--exclude 'tests' \
	--exclude 'vendor' \
	--exclude 'local/.env' \
	"$ROOT_DIR/" "$PACKAGE_DIR/"

(
	cd "$STAGING_DIR"
	zip -qr "$ZIP_PATH" wp-home-inference
)

echo "Created $ZIP_PATH"
