@echo off
clear
echo test_all.bat: Starting test_all.php ...
php test_all.php
echo test_all.bat: Detected errors: %ERRORLEVEL%
if %ERRORLEVEL% == 0 (
  echo test_all.bat: Test checking is successful! You may start any application more in BAT
)