PDF.js 6.4.299 (pdfjs-dist from npm, legacy build), https://github.com/mozilla/pdf.js
pdf.min.js and pdf.worker.min.js are legacy/build/pdf.min.mjs and pdf.worker.min.mjs, renamed .js so every web
server sends them as JavaScript (some do not know .mjs). cmaps, standard_fonts, wasm and iccs are unchanged.
Checksum of the npm package: sha512-AVl138zALtfaAPvADulE0PZThbYzCBS79nL4pOSL/6Sm/4AH5A21BD9VHt97OlCuzJuCpmeZtAtkinisF4Vb1g==
loader.js is PDF Shield's own three-line loader (it imports pdf.min.js and hands it to the viewer); keep it.
Update: download the new pdfjs-dist tarball, check its integrity, copy the same files, update thirdpartylibs.xml.
