#!/bin/sh
# deploy-v1-likehome — copiere whitelist în public_html
#
# uploads/ is never deployed — production property images stay on the server.
# Not copied: .env, uploads/, scripts/, .git/, logs, meta-dev files.

DEPLOYPATH="${DEPLOYPATH:-/home/CPANEL_USER/public_html/}"
HTACCESS_PATHS="cron/.htaccess"
DEPLOY_DIRS="admin ajax assets components cron ical includes lang en ru vendor"
DEPLOY_FILES=".htaccess"

chmod 755 "$DEPLOYPATH" 2>/dev/null || true

copy_htaccess() {
  src="$1"
  dest="$2"
  if [ -f "$src" ]; then
    mkdir -p "$(dirname "$dest")"
    /bin/cp -f "$src" "$dest"
    /bin/chmod 644 "$dest"
  fi
}

for rel in $HTACCESS_PATHS; do
  copy_htaccess "$rel" "$DEPLOYPATH$rel"
done

for dir in $DEPLOY_DIRS; do
  if [ -d "$dir" ]; then
    /bin/cp -R "$dir" "$DEPLOYPATH" 2>/dev/null || true
  fi
done

for f in ./*.php; do
  [ -f "$f" ] || continue
  /bin/cp -f "$f" "$DEPLOYPATH" 2>/dev/null || true
done

for f in robots.txt composer.json composer.lock $DEPLOY_FILES; do
  if [ -f "$f" ]; then
    /bin/cp -f "$f" "$DEPLOYPATH" 2>/dev/null || true
  fi
done

for rel in $HTACCESS_PATHS; do
  copy_htaccess "$rel" "$DEPLOYPATH$rel"
done

# Bootstrap empty upload dirs only on first deploy — never copy or overwrite images.
[ ! -d "$DEPLOYPATH/uploads/properties" ] && mkdir -p "$DEPLOYPATH/uploads/properties"
[ ! -d "$DEPLOYPATH/uploads/_logs" ] && mkdir -p "$DEPLOYPATH/uploads/_logs"

echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) deploy-v1-likehome" > "$DEPLOYPATH.deploy-marker"
chmod 644 "$DEPLOYPATH.deploy-marker"
