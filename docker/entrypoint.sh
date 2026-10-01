#!/bin/sh
# Runs every time the Railway container starts.
set -e

UP=/var/www/html/flower_shop/uploads

# The Railway volume is mounted on uploads/ and starts empty:
# create the folders and copy in the photos that came with the app
mkdir -p "$UP/flowers" "$UP/events" "$UP/payments"
cp -rn /opt/seed_uploads/. "$UP/" 2>/dev/null || true

# Apache (www-data) must be able to save new photos and payment screenshots
chown -R www-data:www-data "$UP"

exec apache2-foreground
