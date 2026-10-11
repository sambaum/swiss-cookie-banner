@echo off
rem Builds swiss-cookie-banner.zip, ready to upload via Plugins -> Add New -> Upload.
rem Uses tar (included in Windows 10+). Compress-Archive is avoided: Windows PowerShell 5.1
rem writes backslash paths into the zip, which breaks extraction on Linux servers.
cd /d "%~dp0"
if exist swiss-cookie-banner.zip del swiss-cookie-banner.zip
tar -a -c -f swiss-cookie-banner.zip swiss-cookie-banner
if errorlevel 1 (echo Build failed & exit /b 1)
echo Built swiss-cookie-banner.zip
