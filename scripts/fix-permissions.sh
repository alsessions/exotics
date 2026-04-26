#!/usr/bin/env bash
set -euo pipefail

# Run from the project root:
#   bash scripts/fix-permissions.sh

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

WWWUSER="${WWWUSER:-www-data}"
WWWGROUP="${WWWGROUP:-www-data}"
WWWROOT="${WWWROOT:-web}"

info() {
  printf '=> %s\n' "$*"
}

if [ ! -d "$WWWROOT/cpresources" ]; then
  mkdir -p "$WWWROOT/cpresources"
fi

info "Setting ownership to ${WWWUSER}:${WWWGROUP} for storage, config, and ${WWWROOT}/cpresources"
if [ "$(id -u)" -eq 0 ]; then
  chown -R "$WWWUSER":"$WWWGROUP" storage config "$WWWROOT/cpresources"
else
  info "Not running as root; skipping ownership changes. Export WWWUSER/WWWGROUP and rerun as root if needed."
fi

info "Applying directory permissions"
find storage config "$WWWROOT/cpresources" -type d -exec chmod 775 {} +
find . -type d \( -path "./storage" -o -path "./config" -o -path "./${WWWROOT}/cpresources" \) -prune -o -type d -exec chmod 755 {} +

info "Applying file permissions"
find storage config "$WWWROOT/cpresources" -type f -exec chmod 664 {} +
find . -type f \( -path "./storage/*" -o -path "./config/*" -o -path "./${WWWROOT}/cpresources/*" \) -prune -o -type f -exec chmod 644 {} +

info "Making shell scripts executable"
find . -type f \( -name '*.sh' -o -name 'craft' \) -exec chmod 755 {} +

info "Permissions updated."
