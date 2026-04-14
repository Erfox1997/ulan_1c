@echo off
cd /d "%~dp0"
echo.
echo  Laravel: http://127.0.0.1:8000
echo  Остановка: Ctrl+C
echo.
php artisan serve
