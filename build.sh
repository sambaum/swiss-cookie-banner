#!/bin/sh
# Builds swiss-cookie-banner.zip, ready to upload via Plugins -> Add New -> Upload.
set -e
cd "$(dirname "$0")"
rm -f swiss-cookie-banner.zip
zip -qr swiss-cookie-banner.zip swiss-cookie-banner
echo "Built swiss-cookie-banner.zip"
