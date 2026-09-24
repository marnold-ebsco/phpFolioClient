#!/usr/bin/env bash
# CLI installer for phpFolioClient.
#
# Bootstraps a new project directory that depends on phpFolioClient via
# Composer, without requiring you to clone this repository yourself.
#
# Usage:
#   curl -sSL https://raw.githubusercontent.com/marnold-ebsco/phpFolioClient/main/bin/install.sh | bash -s -- [options]
#
# Options:
#   -d, --dir DIR        Target project directory (default: ./phpfolioclient-project)
#   -v, --version RANGE  Composer version constraint (default: ^2.0.0)
#   -n, --name NAME      Base filename for the generated .ini/.php sample files (default: sample)
#   -f, --force          Overwrite composer.json/sample files if they already exist
#   -h, --help           Show this help and exit
set -euo pipefail

REPO_URL="https://github.com/marnold-ebsco/phpfolioclient.git"
PACKAGE="marnold-ebsco/phpfolioclient"

dir="./phpfolioclient-project"
version="^2.0.0"
name="sample"
force=0

usage() {
    sed -n '2,17p' "$0" | sed 's/^# \{0,1\}//'
}

while [ $# -gt 0 ]; do
    case "$1" in
        -d|--dir) dir="$2"; shift 2 ;;
        -v|--version) version="$2"; shift 2 ;;
        -n|--name) name="$2"; shift 2 ;;
        -f|--force) force=1; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage; exit 1 ;;
    esac
done

if ! command -v composer >/dev/null 2>&1; then
    echo "Error: composer is not installed or not on PATH. Install it from https://getcomposer.org/ first." >&2
    exit 1
fi

if ! command -v php >/dev/null 2>&1; then
    echo "Error: php is not installed or not on PATH." >&2
    exit 1
fi

php_version=$(php -r 'echo PHP_VERSION;')
if ! php -r 'exit(version_compare(PHP_VERSION, "8.1.0", ">=") ? 0 : 1);'; then
    echo "Error: phpFolioClient requires PHP >= 8.1, found $php_version." >&2
    exit 1
fi

mkdir -p "$dir"
cd "$dir"

if [ -f composer.json ] && [ "$force" -ne 1 ]; then
    echo "Error: composer.json already exists in $dir (use --force to overwrite)." >&2
    exit 1
fi

echo "Writing composer.json..."
cat << EOF > composer.json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "$REPO_URL"
        }
    ],
    "require": {
        "$PACKAGE": "$version"
    }
}
EOF

echo "Installing $PACKAGE ($version)..."
composer require "$PACKAGE:$version"

ini_file="$name.ini"
if [ ! -f "$ini_file" ] || [ "$force" -eq 1 ]; then
    echo "Writing $ini_file..."
    cat << EOF > "$ini_file"
name        = $name
okapiUrl    =
tenant_id   =
username    =
password    =
sslVerify   = "vendor/marnold-ebsco/phpfolioclient/src/folio/cacert.pem"
EOF
else
    echo "Skipping $ini_file (already exists, use --force to overwrite)."
fi

example_file="$name-example.php"
if [ ! -f "$example_file" ] || [ "$force" -eq 1 ]; then
    echo "Writing $example_file..."
    cat << EOF > "$example_file"
<?php
require_once('vendor/autoload.php');

use phpFolioClient\FolioConfig;
use phpFolioClient\FolioAuth;
use phpFolioClient\FolioLogger;
use phpFolioClient\FolioClient;
use phpFolioClient\FolioUtils;
use phpFolioClient\FolioInformation;
use phpFolioClient\FolioReferenceDataManager;
use phpFolioClient\FolioFileHandler;

\$hostname = "$name"; // must match an existing .ini file, e.g. $name.ini

try {
    \$config = new FolioConfig(\$hostname . ".ini");
    \$utils = new FolioUtils();
    \$auth = new FolioAuth(\$config);
    \$logger = new FolioLogger('folioClientLog.txt');
    \$information = new FolioInformation(\$config, \$auth);

    \$folio = new FolioClient(\$config, \$auth, \$utils, \$logger);

    \$refData = new FolioReferenceDataManager(\$folio);
    \$fileHandler = new FolioFileHandler(\$folio);

} catch (Exception \$e) {
    print "Error: " . \$e->getMessage();
    exit;
}
EOF
else
    echo "Skipping $example_file (already exists, use --force to overwrite)."
fi

cat << EOF

Done. phpFolioClient is installed in: $(pwd)

Next steps:
  1. Fill in $ini_file with your Okapi URL, tenant, and credentials.
  2. Run: php $example_file
EOF
