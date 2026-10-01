#!/bin/sh
# Runs every time the Railway container starts.
set -e

UP=/var/www/html/flower_shop/uploads

# Apache must load exactly ONE "MPM" engine. PHP needs "prefork".
# (Fixes "AH00534: More than one MPM loaded" on Railway.)
rm -f /etc/apache2/mods-enabled/mpm_event.load  /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
if [ ! -e /etc/apache2/mods-enabled/mpm_prefork.load ]; then
  a2enmod mpm_prefork >/dev/null
fi

# The Railway volume is mounted on uploads/ and starts empty:
# create the folders and copy in the photos that came with the app
mkdir -p "$UP/flowers" "$UP/events" "$UP/payments"
cp -rn /opt/seed_uploads/. "$UP/" 2>/dev/null || true

# Apache (www-data) must be able to save new photos and payment screenshots
chown -R www-data:www-data "$UP"

exec apache2-foreground
