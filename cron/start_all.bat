@echo off
echo === BA Tool Background Services ===
echo Starting Dev Sheet Poller...
start /B "Poller" "C:\xampp\php\php.exe" -f "C:\xampp\htdocs\BA.Tool\cron\poll_dev_sheet.php"
echo Starting Auto-Sync Daemon...
start /B "AutoSync" "C:\xampp\php\php.exe" -f "C:\xampp\htdocs\BA.Tool\cron\auto_sync_daemon.php"
echo.
echo Both services started!
echo - Dev Sheet Poller: reads dev status every 15s
echo - Auto-Sync Daemon: syncs BA Sheet at scheduled time
echo.
echo Press Ctrl+C to stop all.
pause >nul
