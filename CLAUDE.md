# CLAUDE.md — szeducate

WordPress + Elementor plugin, Hub-Client architektúrájú
képzésmenedzsment/szinkronizációs rendszer a Széchenyi István Egyetem
számára. PHP + React admin-szerkesztő (`@wordpress/scripts`,
`react-hook-form`). Lásd a megosztott `wp-plugin-conventions` skillt, és a
`README.md`-t (36 KB) az architektúra részleteiért.

A `vendor/` (853 fájl, teljes `phpoffice/phpspreadsheet` fa) tudatosan
tracked — ha ez zavaró, a Composer-alapú CI-buildre váltás tudatos döntést
igényel, ne automatikusan törölj belőle.

Git-konvenció már most is `type(#szám): magyar összefoglaló` mintát követ
— ezt folytasd.

## Lint
`npm run lint-js` be van kötve (`wp-scripts lint-js`), de a meglévő kód
jelenleg ~3300 hibát dob rajta — túlnyomó részük CRLF sorvég / szóköz-vs-
tab / idézőjel prettier-eltérés, nem valódi bug. **Ne futtass rajta
tömeges `--fix`-et** kérdés nélkül, mert egy óriási, zajos diffet
generálna az egész kódbázison. Új/módosított fájloknál viszont érdemes
lint-tisztán dolgozni. (`lint-style` nincs bekötve — a projektben nincs
egyetlen CSS/SCSS fájl sem.)
