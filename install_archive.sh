#!/bin/bash

mkdir temp
rsync -avz upload/ temp/
cd temp
zip -r temp_archive.zip *
cd ..
cp -f temp/temp_archive.zip install.zip
rm -rf temp
exit 0