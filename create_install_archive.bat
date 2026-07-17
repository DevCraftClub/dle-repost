@echo off
setlocal EnableExtensions

set "SCRIPT_DIR=%~dp0"
set "MANIFEST=%SCRIPT_DIR%manifest.json"

if not exist "%MANIFEST%" (
	echo manifest.json not found in %SCRIPT_DIR% >&2
	exit /b 1
)

for /f "usebackq delims=" %%A in (`powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT_DIR%create_install_archive.ps1" -ManifestPath "%MANIFEST%"`) do set "ARCHIVE_NAME=%%A"

if not defined ARCHIVE_NAME (
	echo Failed to resolve archive name from manifest.json >&2
	exit /b 1
)

mkdir temp 2>nul
robocopy upload temp /E /NFL /NDL /NJH /NJS /nc /ns /np >nul
if errorlevel 8 exit /b 1

powershell -NoProfile -Command "Get-ChildItem -Path 'temp' -Filter '.gitkeep' -Recurse -File | Remove-Item -Force"
powershell -NoProfile -Command "if (-not (Get-ChildItem -Path 'temp' -Recurse -File)) { exit 1 }"
if errorlevel 1 (
	echo No files to package in upload/ after removing .gitkeep placeholders >&2
	rd /s /q temp 2>nul
	exit /b 1
)

cd temp
set "PATH=%PATH%;%ProgramFiles%\7-Zip\"
7z a -mx0 -r -tzip -aoa temp_archive.zip . >nul
if errorlevel 1 exit /b 1
cd ..

copy /Y temp\temp_archive.zip "%SCRIPT_DIR%%ARCHIVE_NAME%" >nul
rd /s /q temp

echo %ARCHIVE_NAME%
exit /b 0
