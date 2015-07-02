#!/bin/bash

# parse the CLDR database (http://cldr.unicode.org/)
# and extract language names for some languages, as a php include file
# (pinc/loc_lang_names.inc).

echoerr() {
    echo "$@" 1>&2
}

if [ "$1x" == "x" ]; then
    echoerr "First argument should be URL to most CLDR core.zip file"
    echoerr "eg: $0 http://unicode.org/Public/cldr/26/core.zip > loc_lang_names.inc"
    echoerr "See http://cldr.unicode.org/index/downloads"
    exit 1
fi

TEMP=/tmp/CLDR
mkdir -p $TEMP
echoerr "Downloading $1 to $TEMP/core.zip..."
wget -qq $1 -O $TEMP/core.zip
if [ $? -ne 0 ]; then
    echoerr "Error while downloading, bailing"
    exit 1
fi

echoerr "Unzip'ing $TEMP/core.zip to $TEMP..."
unzip -q $TEMP/core.zip -d $TEMP
if [ $? -ne 0 ]; then
    echoerr "Error unzip'ing, bailing"
    exit 1
fi

cldr_common=$TEMP/common/main

# edit the line below to generate data for more languages
LANGS="en de fr it nl pt es"
echoerr "Creating language names for $LANGS"

cat <<'EOT'
<?php
// Language name tables according to the Common Locale Data Repository (CLDR)
// at http://cldr.unicode.org/
//
// This file was automatically generated by SETUP/mk_loc_lang_names.sh. 
// Any manual edits will be lost at the next generation
// (e.g. when a new language is added).

// Structure:
// $loc_lang_names['de']['it'] gives the name of the "it" (Italian)
// language in the "de" (German) language, i.e. "Italienisch".
// Language names are in utf-8 encoding.

$loc_lang_names = array (
EOT

for i in $LANGS
do
    xml=$cldr_common/$i.xml

    sed '
        /<identity>/,/<\/identity>/ {
            s/^[[:space:]]*<generation date="\$\([^"]*\)\$"\/>/    \/\/ \1/p
            s/^[[:space:]]*<language type="\([^"]*\)"\/>/    "\1" => array(/p
        }

        /<languages>/,/<\/languages>/ {
            s/^[[:space:]]*<\/languages>/    ),/p
            /^[[:space:]]*<language type="\([^"]*\)"[^>]*>\([^<]*\)<\/.*/!d
            s//        "\1" => "\2",/
            
            # remove fake language entry
            /^ *"root" => /d

            # only keep the first entry for this tag
            # (i.e. delete this line if it contains the same entry as the
            # previous one.)
            G
            /^\( *"[^"]*" \).*\n\1/d
            s/\n.*//
            h
            p
        }
        d
    ' $xml | sed '
        # canonicalize the keys in lowercase with hyphens, e.g. 
        # convert "de_AT"  into  "de-at".
        /^ *"[^"]*" =>/!b
        h
        y/ABCDEFGHIJKLMNOPQRSTUVWXYZ_/abcdefghijklmnopqrstuvwxyz-/
        G
        s/\([^=]*=> \).*\n[^=]*=> /\1/
    '        
done

cat <<EOT
);
?>
EOT

rm -rf $TEMP
