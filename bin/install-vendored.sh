#!/usr/bin/env bash
# install-vendored.sh -- fetch and set up phpFolioClient without cloning the
# full repo or requiring it via Composer's VCS repository mechanism.
#
# Downloads only the files phpFolioClient actually needs to run, pinned to
# one commit SHA, drops a local composer.json that autoloads them directly
# (still pulling guzzlehttp/guzzle from Packagist) and runs `composer
# install`. Safe to re-run: with no flags it checks for a newer commit and
# updates the vendored files in place.
#
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/marnold-ebsco/phpFolioClient/main/bin/install-vendored.sh | bash -s -- [options]
#   ./install-vendored.sh [options]
#
# Options:
#   --dir PATH       Install location (default: ./phpFolioClient)
#   --ref REF        Branch or tag to install from (default: main)
#   --no-composer    Download files only; skip `composer install`
#   --check          Only report whether an update is available; change nothing
#   -h, --help       Show this help

set -euo pipefail

REPO="marnold-ebsco/phpFolioClient"
INSTALL_DIR="./phpFolioClient"
REF="main"
NO_COMPOSER=0
CHECK_ONLY=0
DIR_EXPLICIT=0

# Files needed to run phpFolioClient, relative to repo root. Deliberately
# excludes tests/, fixtures, and the bin/ installers themselves.
FILES=(
  "src/bootstrap.php"
  "src/folio/phpFolioClient.php"
  "src/folio/FolioAuth.php"
  "src/folio/FolioClient.php"
  "src/folio/FolioConfig.php"
  "src/folio/FolioDataExport.php"
  "src/folio/FolioFileHandler.php"
  "src/folio/FolioInformation.php"
  "src/folio/FolioLogger.php"
  "src/folio/FolioReferenceDataManager.php"
  "src/folio/FolioUtils.php"
  "src/folio/cacert.pem"
  "README.md"
  "ISSUES.md"
)

VERSION_MARKER=".phpfolioclient_install_version"

usage() {
  sed -n '/^# Usage:/,/^set -euo/p' "$0" | sed '$d; s/^# \{0,1\}//'
}

# `set -u` turns a missing $2 (e.g. a bare trailing "--dir") into an
# "unbound variable" crash with no context -- this checks for it up
# front so a dropped value gets a clear error instead.
need_value() {
  if [[ $# -lt 2 ]]; then
    echo "Error: $1 requires a value" >&2
    exit 1
  fi
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dir) need_value "$@"; INSTALL_DIR="$2"; DIR_EXPLICIT=1; shift 2 ;;
    --ref) need_value "$@"; REF="$2"; shift 2 ;;
    --no-composer) NO_COMPOSER=1; shift ;;
    --check) CHECK_ONLY=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown option: $1" >&2; usage; exit 1 ;;
  esac
done

# Catch the easy-to-make mistake of running this (with no --dir) from
# *inside* an existing install -- with the default "./phpFolioClient",
# that creates a nested phpFolioClient/phpFolioClient/ instead of
# updating the one you're standing in, and the only sign something's
# wrong is that the outer VERSION_MARKER silently never changes. Only
# fires when --dir was left at its default, since an explicit
# "--dir ./phpFolioClient" from here is unambiguous.
if [[ "$DIR_EXPLICIT" -eq 0 && -f "./${VERSION_MARKER}" ]]; then
  echo "Error: the current directory already looks like a phpFolioClient install" >&2
  echo "(found ./${VERSION_MARKER} here). Running with no --dir would create a" >&2
  echo "nested ${INSTALL_DIR} inside it instead of updating this one in place." >&2
  echo >&2
  echo "Did you mean:" >&2
  echo "  ./install-vendored.sh --dir ." >&2
  echo >&2
  echo "Or, if you really do want a nested install here, pass --dir explicitly:" >&2
  echo "  ./install-vendored.sh --dir ${INSTALL_DIR}" >&2
  exit 1
fi

need() {
  command -v "$1" >/dev/null 2>&1 || { echo "Error: '$1' is required but not found on PATH." >&2; exit 1; }
}
need curl
need php

php_version=$(php -r 'echo PHP_VERSION;')
if ! php -r 'exit(version_compare(PHP_VERSION, "8.1.0", ">=") ? 0 : 1);'; then
  echo "Error: phpFolioClient requires PHP >= 8.1, found $php_version." >&2
  exit 1
fi

if [[ "$NO_COMPOSER" -eq 0 ]]; then
  need composer
fi

# Prints "<sha> <committer date>" for REF (the date goes into the install
# info file below, so a run's log can say when its commit was made).
resolve_commit() {
  curl -fsSL "https://api.github.com/repos/${REPO}/commits/${REF}" \
    | php -r '$d=json_decode(stream_get_contents(STDIN), true); echo $d["sha"] . " " . $d["commit"]["committer"]["date"];'
}

echo "Resolving latest commit for ${REPO}@${REF}..."
COMMIT_INFO="$(resolve_commit)"
read -r REMOTE_SHA REMOTE_DATE <<< "$COMMIT_INFO"
echo "Latest commit: ${REMOTE_SHA} (committed ${REMOTE_DATE})"

# This script isn't in FILES below -- it can't safely rewrite itself
# mid-loop the way those files get rewritten -- so a stale local copy
# would otherwise silently keep running old logic forever, even once
# its downloaded files report "up to date". Check and refresh it
# separately, here, before anything else runs.
if [[ "$0" == *install-vendored.sh && -f "$0" ]]; then
  TMP_SELF="$(mktemp)"
  curl -fsSL "https://raw.githubusercontent.com/${REPO}/${REMOTE_SHA}/bin/install-vendored.sh" -o "$TMP_SELF"
  if ! cmp -s "$TMP_SELF" "$0"; then
    if [[ "$CHECK_ONLY" -eq 1 ]]; then
      echo "install-vendored.sh itself has an update available upstream (in addition to any shown below)."
      rm -f "$TMP_SELF"
    else
      echo "install-vendored.sh itself changed upstream -- updating it now; re-run the same command to pick up the fix."
      cp "$TMP_SELF" "$0"
      chmod +x "$0"
      rm -f "$TMP_SELF"
      exit 0
    fi
  else
    rm -f "$TMP_SELF"
  fi
fi

LOCAL_SHA=""
if [[ -f "${INSTALL_DIR}/${VERSION_MARKER}" ]]; then
  LOCAL_SHA="$(cat "${INSTALL_DIR}/${VERSION_MARKER}")"
fi

if [[ -n "$LOCAL_SHA" && "$LOCAL_SHA" == "$REMOTE_SHA" ]]; then
  echo "Already up to date (${LOCAL_SHA})."
  if [[ "$CHECK_ONLY" -eq 1 ]]; then exit 0; fi
elif [[ -n "$LOCAL_SHA" ]]; then
  echo "Update available: ${LOCAL_SHA} -> ${REMOTE_SHA}"
  if [[ "$CHECK_ONLY" -eq 1 ]]; then exit 0; fi
else
  echo "No existing install found at ${INSTALL_DIR}; installing fresh."
  if [[ "$CHECK_ONLY" -eq 1 ]]; then exit 0; fi
fi

mkdir -p "${INSTALL_DIR}/src/folio"

echo "Fetching files pinned to ${REMOTE_SHA}..."
for f in "${FILES[@]}"; do
  url="https://raw.githubusercontent.com/${REPO}/${REMOTE_SHA}/${f}"
  dest="${INSTALL_DIR}/${f}"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "$url" -o "$dest"
  echo "  ${f}"
done

echo "${REMOTE_SHA}" > "${INSTALL_DIR}/${VERSION_MARKER}"
printf 'sha=%s\ndate=%s\nref=%s\n' "${REMOTE_SHA}" "${REMOTE_DATE}" "${REF}" \
  > "${INSTALL_DIR}/.phpfolioclient_install_info"

# Vendored files are autoloaded straight out of src/folio/ via a local
# composer.json -- this avoids requiring phpFolioClient itself through
# Composer's VCS repository (which would need a git clone of this repo),
# while still letting Composer resolve the one real dependency, Guzzle,
# from Packagist.
if [[ "$NO_COMPOSER" -eq 0 ]]; then
  COMPOSER_JSON="${INSTALL_DIR}/composer.json"
  if [[ ! -f "$COMPOSER_JSON" ]]; then
    echo "Writing composer.json..."
    cat << EOF > "$COMPOSER_JSON"
{
    "require": {
        "php": ">=8.1",
        "guzzlehttp/guzzle": "^8.0"
    },
    "autoload": {
        "psr-4": {
            "phpFolioClient\\\\": "src/folio/"
        }
    }
}
EOF
  fi

  echo "Running composer install in ${INSTALL_DIR}..."
  ( cd "${INSTALL_DIR}" && composer install --quiet )
fi

cat <<EOF

Done. phpFolioClient (commit ${REMOTE_SHA:0:12}) is ready at:
  ${INSTALL_DIR}
EOF

if [[ "$NO_COMPOSER" -eq 0 ]]; then
  cat <<EOF

Use it with:
  require_once '${INSTALL_DIR}/vendor/autoload.php';
  use phpFolioClient\FolioConfig;
  use phpFolioClient\FolioAuth;
  use phpFolioClient\FolioClient;
  // ...
EOF
else
  cat <<EOF

--no-composer was passed, so no vendor/ directory was created. You'll need
to provide your own PSR-4 autoloading of the phpFolioClient namespace to
${INSTALL_DIR}/src/folio/, plus guzzlehttp/guzzle, yourself.
EOF
fi

cat <<EOF

Check for updates later without changing anything:
  $0 --dir "${INSTALL_DIR}" --ref ${REF} --check

Apply an update in place:
  $0 --dir "${INSTALL_DIR}" --ref ${REF}
EOF
