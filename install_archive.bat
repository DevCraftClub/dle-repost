@echo off
mkdir temp
robocopy upload temp /E
cd temp
set PATH=%PATH%;%ProgramFiles%\7-Zip\
7z a -mx0 -r -tzip -aoa temp_archive.zip *
cd ..
copy /Y temp\temp_archive.zip install.zip
rd /s /q temp
exit;