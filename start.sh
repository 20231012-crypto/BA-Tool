#!/bin/bash
# Start web server + poller + auto-sync on Railway

# Start poller in background
php -f /app/cron/poll_dev_sheet.php &
echo "Poller started (PID: $!)"

# Start auto-sync daemon in background
php -f /app/cron/auto_sync_daemon.php &
echo "Auto-sync daemon started (PID: $!)"

# Start web server in foreground
php -d display_errors=1 \
    -d session.save_path=/tmp/sessions \
    -d session.cookie_secure=0 \
    -d session.cookie_samesite=Lax \
    -S 0.0.0.0:${PORT:-8080} \
    -t /app
