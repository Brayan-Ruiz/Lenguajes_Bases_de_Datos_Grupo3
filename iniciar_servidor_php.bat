@echo off
title Servidor Local PHP 8.3 - Bike Store (SC-504)
echo ==============================================================
echo   Iniciando Bike Store en http://localhost:8000/login.php
echo   (Presiona Ctrl + C para detener el servidor)
echo ==============================================================
start http://localhost:8000/login.php
php -S localhost:8000 -t "%~dp0public"
pause
