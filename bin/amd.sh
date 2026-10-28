#!/usr/bin/env bash
# Makes amd/build/viewer.min.js from amd/src/viewer.js: the same code with the module name added, which is what
# Moodle loads. (Moodle's own "grunt amd" gives an equivalent, minified file.)
set -euo pipefail
cd "$(dirname "$0")/.."
sed "s#^define(\['core/str'\]#define('mod_pdfshield/viewer', ['core/str']#" amd/src/viewer.js > amd/build/viewer.min.js
