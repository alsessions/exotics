#!/usr/bin/env bash
set -euo pipefail

# Run from anywhere in the repo:
#   bash scripts/fix-permissions.sh

if [ -n "${BASH_SOURCE:-}" ]; then
  ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
else
  ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
fi
cd "$ROOT_DIR"

WWWUSER="${WWWUSER:-www-data}"
WWWGROUP="${WWWGROUP:-www-data}"
WWWROOT="${WWWROOT:-web}"

info() {
  printf '=> %s\n' "$*"
}

writable_dirs=(storage config "$WWWROOT/cpresources")
existing_dirs=()
for dir in "${writable_dirs[@]}"; do
  if [ -d "$dir" ]; then
    existing_dirs+=("$dir")
  elif [ "$dir" = "$WWWROOT/cpresources" ]; then
    mkdir -p "$dir"
    existing_dirs+=("$dir")
  else
    info "Skipping missing path: $dir"
  fi
done

if [ "${#existing_dirs[@]}" -gt 0 ]; then
  info "Setting ownership to ${WWWUSER}:${WWWGROUP} for: ${existing_dirs[*]}"
  if [ "$(id -u)" -eq 0 ]; then
    chown -R "$WWWUSER":"$WWWGROUP" "${existing_dirs[@]}"
  else
    info "Not running as root; skipping ownership changes. Export WWWUSER/WWWGROUP and rerun as root if needed."
  fi

  info "Applying directory permissions"
  find "${existing_dirs[@]}" -type d -exec chmod 775 {} +

  info "Applying file permissions"
  find "${existing_dirs[@]}" -type f -exec chmod 664 {} +
else
  info "No writable paths found; nothing to update."
fi

info "Applying general repository permissions"
find . -type d \( -path './storage' -o -path './config' -o -path './${WWWROOT}/cpresources' \) -prune -o -type d -exec chmod 755 {} +
find . -type f \( -path './storage/*' -o -path './config/*' -o -path './${WWWROOT}/cpresources/*' \) -prune -o -type f -exec chmod 644 {} +

info "Making shell scripts executable"
find . -type f \( -name '*.sh' -o -name 'craft' \) -exec chmod 755 {} +

info "Permissions updated."
