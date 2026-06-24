# SZEducate - Képzésmenedzsment és Szinkronizációs Rendszer

A **SZEducate** egy professzionális, WordPress és Elementor alapú, elosztott architektúrájú (Hub-Client) bővítmény. Célja az egyetemi vagy intézményi képzések, szakok és kurzusok központosított kezelése, hálózati szinkronizálása, valamint modern, szemantikus keresőkkel és listázókkal történő megjelenítése a látogatók számára.

---

## Tartalomjegyzék

1. [Architektúra és Működési Elv](#1-architektúra-és-működési-elv)
2. [Főbb Funkciók Részletesen](#2-főbb-funkciók-részletesen)
3. [Rendszerkövetelmények](#3-rendszerkövetelmények)
4. [Telepítés és Beüzemelés](#4-telepítés-és-beüzemelés)
5. [Használati Útmutató (Szerkesztőknek)](#5-használati-útmutató-szerkesztőknek)
6. [Fejlesztői Dokumentáció](#6-fejlesztői-dokumentáció)

---

## 1. Architektúra és Működési Elv

A SZEducate rendszer nem egy hagyományos, egyedülálló bővítmény. Kétféle hálózati szerepkörben (módban) képes futni, melyet a beállításokban lehet kiválasztani:

- **Hub (Központi Adatszerver):** Ez a "karmester". Ezen a szerveren történik a képzési adatlapok logikai struktúrájának (a Sémának) a felépítése. Fogadja a kliensekről beérkező adatokat, és rendelkezik egy dedikált API végponttal a teljes rendszer napi biztonsági mentéséhez.
- **Client (Kliens Csomópont):** A publikus weboldal. Letölti és értelmezi a Hub-ról a Sémát. Itt történik a konkrét képzések (szakok) rögzítése, Excel alapú tömeges módosítása, valamint az Elementor widgeteken keresztüli megjelenítés. A Kliens a háttérben folyamatosan visszaszinkronizálja az adatokat a Hub-ra.

---

## 2. Főbb Funkciók Részletesen

### Intelligens Adatbevitel (React Editor)

A képzések rögzítése lecseréli a hagyományos WordPress felületet egy egyedi, villámgyors React alkalmazásra.

- **Dinamikus fülek:** A Séma alapján bizonyos beállítások csak akkor jelennek meg, ha relevánsak (pl. a BSc specifikus fülek eltűnnek, ha MSc képzést rögzítünk).
- **Auto-suggest címkék:** A kulcsszavak és kategóriák gépelésekor a rendszer felajánlja az adatbázisban már szereplő kifejezéseket, ezzel drasztikusan csökkentve az elgépelések (pl. _Agrár_ vs. _agrar_) számát.
- **Szigorú validáció:** A rendszer nem enged hiányos adatlapot menteni, és figyeli a kötelezően kitöltendő (piros csillagos) mezőket.

### Szemantikus AJAX Okos Kereső

Egy modern Elementor widget, amely oldalújratöltés nélkül, gépelés közben keres.

- **Súlyozott algoritmus:** A rendszer pontozza a találatokat. (Pl. 100 pont, ha a cím pontosan megegyezik, 50 pont, ha a képzési terület egyezik, és csak 5 pont, ha a szó egy hosszú leírás közepén van elrejtve). Így mindig a legrelevánsabb találat van legfelül.
- **Kategória felismerés:** Ha a keresőszó egyezik egy Telephely vagy Képzési Forma nevével (pl. _"Győr"_), a rendszer kiemelt mappaként (kategóriaként) felajánlja azt, és egyenesen a szűrt listázó oldalra irányítja a látogatót.

### Dinamikus Szaklista és Szűrés

Egy komplex listázó widget, amely képes URL paraméterek vagy tiszta SEO linkek (pl. `/kepzeseink/telephely/gyor/`) alapján automatikusan leszűrni a megjelenített képzéseket. Tartalmaz beépített Drag & Drop manuális sorrendezési lehetőséget, így a kategóriák vizuális sorrendje szabadon testreszabható.

### Tömeges Excel (XLSX) Import / Export

A WordPress admin felületébe integrált, professzionális tömeges adatkezelő eszköz.

- **Okos Sablonok:** A rendszer a Sémából generál színkódolt (a kötelező mezőket pirossal jelölő) Excel táblázatot.
- **Beépített Validáció:** A legördülő (egyválasztós) mezők az Excelben is natív legördülő listaként jelennek meg, megakadályozva a hibás adatbevitelt.
- **"Puska" munkalap:** A letöltött Excel fájl tartalmaz egy külön lapot az eddig használt összes kulcsszóval, megkönnyítve az adatrögzítők munkáját.

### Automatikus Frissítés (PUC)

A bővítmény a Plugin Update Checker (PUC) segítségével közvetlenül a GitHubról frissül. A WordPress Vezérlőpultján ugyanúgy jelzi az új verziókat, és egy kattintással frissíthető, mint a hivatalos tárolóból letöltött pluginok.

---

## 3. Rendszerkövetelmények

- **PHP:** 7.4 vagy újabb (Erősen ajánlott a PHP 8.0+)
- **WordPress:** 6.0 vagy újabb verzió
- **Elementor:** Ingyenes verzió is elegendő a widgetek használatához
- _(Fejlesztőknek: Node.js és Composer a forráskód fordításához)_

---

## 4. Telepítés és Beüzemelés

A bővítmény telepítése a standard WordPress módszerrel történik. **Ne a forráskódot töltsd le**, hanem a kiadásra kész ZIP fájlt!

### Telepítés lépései:

1.  Keresd meg a GitHub repó **Releases** (Kiadások) menüpontját, és töltsd le a legfrissebb `.zip` telepítőfájlt. _(Ez a fájl már tartalmazza a lefordított React kódokat és a szükséges PHP könyvtárakat)._
2.  Lépj be a WordPress adminisztrációs felületére.
3.  Navigálj a **Bővítmények -> Új hozzáadása** menüpontba.
4.  Kattints a **Bővítmény feltöltése** gombra az oldal tetején.
5.  Válaszd ki a letöltött `.zip` fájlt, majd kattints a **Telepítés most** gombra.
6.  A sikeres telepítés után kattints a **Bővítmény bekapcsolása** gombra.

### A Kliens beállítása és szinkronizációja:

1.  Navigálj a **Beállítások -> SZEducate Architektúra** menüpontba.
2.  Válaszd ki a **Kliens** módot.
3.  Add meg a központi **Hub URL-jét** (pl. `https://hub.sze.hu`) és a titkos **API Tokent**.
4.  Kattints a _Beállítások Mentése_ gombra.
5.  Ezután kattints a megjelenő kék színű **Séma Letöltése a Hub-ról** gombra. Ezzel a kliens megtanulja az adatlapok struktúráját.
6.  **Kritikus lépés:** Navigálj a _Beállítások -> Közvetlen hivatkozások_ (Permalinks) menübe, és kattints a **Módosítások mentése** gombra (anélkül, hogy bármit átállítanál). Ez aktiválja a SEO barát URL-eket a keresőhöz.

---

## 5. Használati Útmutató (Szerkesztőknek)

### Új képzés felvitele

1. A bal oldali menüben válaszd a **Képzések -> Új Képzés** lehetőséget.
2. Töltsd ki a Képzés Címét (ez a szak hivatalos megnevezése).
3. Válaszd ki a _Képzési Formát_. Ennek hatására a felület alján betöltődnek a szakhoz tartozó specifikus adatlap-fülek.
4. A kulcsszavak megadásánál kezdj el gépelni, és a rendszer felajánlja a korábbiakat. Több elem kiválasztásához használj Entet.
5. Kattints a **Véglegesítés és Mentés** gombra.

### Tömeges módosítás (Excel)

1. Menj a **Képzések -> Excel Import / Export** menüpontba.
2. Az _Üres Sablon Generálása_ vagy az _Adatok Exportálása_ dobozban válaszd ki a kívánt képzési formákat.
3. Töltsd le az Excel fájlt. A piros hátterű oszlopok kitöltése kötelező!
4. Módosítsd az adatokat (a legördülő listák segíteni fognak).
5. Töltsd fel a fájlt a képernyő alján lévő importálóval. A rendszer automatikusan frissíti a meglevőket, létrehozza az újakat, és felszinkronizálja a változásokat a Hub-ra.

### Elementor Widgetek

Az oldalak szerkesztésekor az Elementor panelen a _SZEducate_ kategóriában találod a widgeteket:

- **SZEducate Okos Kereső:** Bárhova elhelyezhető. Beállítható benne, hogy "Enter" ütése esetén melyik URL-re vigye a látogatót (Céloldal).
- **SZEducate Szaklista:** A fő archívum widget. Megadhatod benne az alapértelmezett csoportosítást (pl. Képzési Terület szerint), és egyedi sorrendet is felállíthatsz. Automatikusan reagál, ha a Keresőből vagy a Kulcsszavak widgetből érkezik a látogató.
- **SZEducate Kulcsszavak:** Kiteszi egy adott szak címkéit. Ha megadod a "Szaklista" oldalad URL-jét a beállításaiban, a címkék kattinthatóvá válnak.

---

## 6. Fejlesztői Dokumentáció

### Kódstruktúra

A bővítmény modern, szeparált architektúrára épül:

- `szeducate.php`: A fő belépési pont és a PUC frissítő inicializálása.
- `src/index.js`: A React (Gutenberg) alapú szerkesztőfelület teljes forráskódja.
- `includes/class-szeducate-client.php`: A Kliens háttérlogikája, API végpontok (AJAX Kereső) regisztrálása.
- `includes/class-szeducate-import-export.php`: A PhpSpreadsheet alapú, formázott XLSX generátor és importáló.
- `includes/widgets/`: Az Elementor widgetek objektumorientált osztályai.

### Fordítás és Csomagolás (Build folyamat)

Ha módosítod a React forráskódot (`src/index.js`) vagy frissíted a PHP könyvtárakat, új _Release_ fájlt kell generálnod.

1.  **React kód lefordítása:** A bővítmény mappájában futtasd a `npm run build` parancsot. Ez generálja le a minifikált fájlokat a `build/` mappába.
2.  **PHP könyvtárak letöltése:** Futtasd a `composer install` parancsot, ami létrehozza a `vendor/` mappát (benne a PhpSpreadsheet-tel).
3.  **Csomagolás:** Jelöld ki a fájlokat, és csomagold `.zip` formátumba. **Fontos:** A `node_modules` és a `src` mappákat _ne_ tedd bele a terjesztésre szánt ZIP fájlba, de a `vendor` és `build` mappák kötelezően legyenek benne!

### Verziókövetés és Automatikus Frissítés kiadása

Az élő weboldalak (Kliensek) a Plugin Update Checker segítségével figyelik a GitHub tárolót. Új frissítés kiadásához:

1.  Módosítsd a verziószámot a `szeducate.php` fejlécében (pl. `Version: 1.0.2`-re).
2.  Készíts egy commitot a frissített fájlokkal a `main` (vagy `master`) branch-re.
3.  Hozz létre egy új **Release**-t a GitHubon, és csatold hozzá az előző pontban elkészített, mindent tartalmazó `.zip` fájlt.
4.  A weboldalak WordPress rendszere ezt érzékelni fogja, és felajánlja az adminisztrátoroknak a frissítést.

## Changelog

### 0.9.6

- Version control and localization

### 0.9.5

- Backup Automation + editing fixup

### 0.9.4

- BackupManagement

### 0.9.3

- Vendor for export-import

### 0.9.2

- RBAC added, most már lehet rolenak capability-t adni hozzá

### 0.9.1

- Settings page átrakva kliens és hub oldalon is

### 0.9.0

- Beta verzió kiadása
