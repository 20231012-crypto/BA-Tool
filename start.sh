#!/bin/bash
# Start both web server and dev sheet poller

# Start poller in background
php -f /app/cron/poll_dev_sheet.php &
POLLER_PID=$!
echo "Poller started (PID: $POLLER_PID)"

# Start web server in foreground
php -d display_errors=1 \
    -d session.save_path=/tmp/sessions \
    -d session.cookie_secure=0 \
    -d session.cookie_samesite=Lax \
    -S 0.0.0.0:${PORT:-8080} \
    -t /app
