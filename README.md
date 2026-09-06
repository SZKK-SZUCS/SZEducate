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

- **SZEducate Okos Kereső:** Bárhova elhelyezhető. Beállítható benne, hogy "Enter" ütése esetén melyik URL-re vigye a látogatót (Céloldal). A **„Szűrés (aloldalakhoz)"** szekcióban egy vagy több séma-mezőre (pl. Képzési Forma = BSc) korlátozható, hogy egy szűrt aloldalon csak az oda illő képzésekben keressen.
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

### 0.9.41

- Okos Kereső widget: új **„Szűrés (aloldalakhoz)"** tartalmi szekció. A sémában lévő minden egyválasztós / jelölőnégyzetes mezőhöz megjelenik egy legördülő (pl. „Szűrés: Képzési Forma"). Ha beállítasz egyet vagy többet, a kereső csak az azoknak megfelelő képzésekben keres – így egy BSc-aloldalra tett kereső csak a BSc-képzéseket találja meg. Több szűrő ÉS kapcsolatban van. Szűrt módban a legördülő nem ajánl fel „mappa" (kategória) találatokat, mert azok kivezetnének a szűrt nézetből. Üresen hagyva a működés változatlan; a meglévő kereső-példányok nem változnak. (A „Szűrés (aloldalakhoz)" szekció ugyanazt a séma-vezérelt logikát használja, mint a Szaklista widget dinamikus szűrői – érdemes a kereső „Archívum URL"-jét is a szűrt aloldalra állítani.)

### 0.9.40

- Hotfix a 0.9.39-hez: a „Képzés Adat" tag link-módú vezérlőiről (`Megjelenítés`, `Bevezető szöveg`, `Hivatkozás szövege`) lekerültek a hosszú leírások – a Dynamic Tag beállító-panel túl magas lett, egyes képernyőkön kilógott. A tippek mostantól rövid helyőrző-szövegben (`placeholder`) jelennek meg, nem növelik a panel magasságát. Működés változatlan.

### 0.9.39

- „Képzés Adat" Dynamic Tag: új **„Megjelenítés"** választó – _Szövegként (alap)_ vagy _Kattintható hivatkozásként_. Link-módban a mező értékét (egy URL-t, pl. a „Nyelvi követelmények" mezőét) `<a>` elemként írja ki a megadott felirattal (alap: „Kattints ide"; üresen maga az URL), opcionális **bevezető szöveggel** (pl. „Teljesítendő kimeneti nyelvi követelmény részletek: ") és **„Új lapon nyíljon"** kapcsolóval (`rel="noopener noreferrer"`). Így egy Címsor / Szövegszerkesztő widgetben is elhelyezhető egy dinamikus URL-re mutató inline hivatkozás, nem csak nyers URL-ként. Érvénytelen / üres URL esetén nincs kimenet (a tag Fallback mezője él). Meglévő tag-példányok változatlanul szövegként jelennek meg.

### 0.9.38

- Linkek widget:
  - Új **méret-vezérlők**: Szélesség (px/%/vw), Minimális szélesség, Minimális magasság – mindkét megjelenéshez. Eddig csak a belső margó volt állítható.
  - Új **„Megjelenés"** választó (Tartalom fül): **Gomb** (eddigi) vagy **Csak szöveg (link)**. „Csak szöveg" módban nincs háttér / keret / árnyék / belső margó – a linkek sima szövegként jelennek meg, saját szövegszínnel és opcionális hover-aláhúzással. A gomb-specifikus vezérlők (háttér, keret, padding, lekerekítés…) csak „Gomb" módban jelennek meg.

### 0.9.37

- Hotfix: a „SZEducate: Feltételes fülek" vezérlő-szekció mostantól **csak a klasszikus** Harmonika (`accordion`) widgetbe kerül be. A **nested** Harmonikába való beszúrása a szerkesztőben megbontotta a widget belső `items` ↔ gyerek-konténer szinkronját (fantom, üres konténerek). Nested harmonikán a fül tartalmi **konténerére** tett SZEducate Láthatóság a megoldás – a frontend fejléc-eltávolítás (0.9.36) ehhez változatlanul működik.

### 0.9.36

- Harmonika feltételes fülek (0.9.35) kiterjesztve az **újabb, „nested" Harmonika** widgetre is (a mostani Elementorban ez az alapértelmezett). A „SZEducate: Feltételes fülek" szekció generikus hookon jelenik meg mindkét változatnál. Nested harmonikán ráadásul elég a fül **tartalmi konténerére** rátenni a szokásos SZEducate Láthatóságot – ilyenkor a hozzá tartozó fül-fejléc is eltűnik (nem marad árva cím). A kimenet-szűrő a `.e-n-accordion-item` (`<details>`) elemeket is kezeli.

### 0.9.35

- Új: az **alap Elementor „Harmonika" (Accordion) widget** fülei **egyenként** SZEducate-láthatósághoz köthetők. A widget Tartalom fülén megjelenik a **„SZEducate: Feltételes fülek"** szekció – soronként megadható: `fül sorszáma` + `vizsgált mező` + `feltétel` (ÜRES / NEM ÜRES / EGYENLŐ / NEM EGYENLŐ / TARTALMAZZA). Ha a feltétel teljesül, a fül fejléce **és** tartalma is kimarad a kimenetből (szerver oldalon, csak `sz_course` oldalon). A rejtés a rendezett fülsorrend szerinti pozíción alapul.
- Belső: a láthatóság-feltétel kiértékelése közös helyre került (`sz_rule_matches()`); a strukturált mezők üresség-vizsgálata (0.9.24) itt is érvényes.

### 0.9.34

- „Képzés Adat" Dynamic Tag: az **„Elválasztó (több elem esetén)"** vezérlő mostantól a jelölőnégyzet (több opciós) mezőkre akkor is hat, ha az érték `;`-vel elválasztott szövegként van tárolva (eddig ilyenkor nyersen, a tárolt `;`-kkel íródott ki, pl. `Győr; Budapest; …`). Az elemek külön-külön escape-elődnek; `\n` elválasztóval minden elem külön sorba (`<br>`) kerül.

### 0.9.33

- Státusz widget – elrendezés újragondolva. A markup mostantól fix szerkezetű: a wrapper mindig függőleges (oszlop), fent a badge-ek sora, alul a „Később" sor. Így:
  - a **„Vízszintes igazítás"** a badge-eket ÉS a „Később" sort is igazítja (eddig a kiegészítő szöveg mindig balra maradt);
  - a **„Függőleges igazítás"** és a **„Vízszintes igazítás"** iránytól függetlenül ugyanazt jelenti (nem cserélik a jelentésüket a régi row/column kapcsoló miatt);
  - a badge-ek nem nyúlnak/torzulnak, előreláthatóan stackelnek;
  - a régi „Irány" → **„A badge-ek elrendezése"** (csak a két badge-re hat), új **„Térköz a 'Később' sor előtt"** vezérlő. A „Függőleges igazítás" csak akkor jelenik meg, ha a magasság-kitöltés be van kapcsolva.

### 0.9.32

- Státusz widget: új **Függőleges igazítás** vezérlő (Fent / Középen / Lent / Nyújtott – `align-items` + `align-content`) és **„Töltse ki a rendelkezésre álló magasságot"** kapcsoló. Így egy magasabb konténerben (pl. hero kép fölött) a badge-blokk függőlegesen is pozicionálható. A meglévő „Igazítás" átnevezve „Vízszintes igazítás"-ra.

### 0.9.31

- Új **SZEducate Képzés Videó** widget: egy „Link" típusú séma-mezőben (alapból `video`) tárolt hivatkozást beágyazott lejátszóként jeleníti meg. YouTube / Vimeo / stb. a WordPress beépített oEmbed-en át (nincs API-kulcs), közvetlen `.mp4`/`.webm` fájl natív `<video>`-ként, minden más nyers hivatkozásként. Vezérlők: képarány (16:9 / 4:3 / 21:9 / 1:1 / 9:16), max. szélesség, igazítás, lekerekítés, árnyék; a lejátszó reszponzívan kitölti az arány-dobozt. Ingyenes Elementorral is működik.
- Új **Képzés Videó URL (SZEducate)** URL-kategóriás Dynamic Tag (Elementor PRO): az Elementor beépített Videó widgetjének „Link" mezőjébe dinamikus forrásként beköthető, így a natív widget teljes felszerelése (előnézeti kép, lightbox stb.) használható.

### 0.9.30

- Okos Kereső widget – teljes stílus-átdolgozás. Eddig szinte minden a JS által generált HTML-be volt inline-olva, ezért az Elementor Stílus-vezérlők nem hatottak. Mostantól:
  - **Helyőrző szöveg** színe és tipográfiája állítható (`::placeholder`).
  - **Legördülő találati lista**: konténer háttér / keret / lekerekítés / árnyék / max. magasság; találati elem tipográfia, szín, hover-háttér, hover-szövegszín, belső margó, elválasztó vonal.
  - **Pöttyök**: méret, térköz, lekerekítés, szín (aktív / inaktív) – vagy teljesen kikapcsolhatók.
  - **Aktív / inaktív megkülönböztetés** kapcsoló – kikapcsolva minden találat egyformán jelenik meg.
  - Kategória-találat és a „Nincs találat" / „Összes találat" sorok színei is állíthatók.
  - Mellékesen javítva: a beviteli mező háttere / tipográfiája (font-méret) korábban az inline stílus miatt nem volt felülírható.

### 0.9.29

- Szaklista widget: a listaelemek **Hover** stílusa mostantól az **inaktív** szakokra is érvényes (eddig csak az aktívakra vonatkozott, mert a hover-szelektor a `.sz-course-active:hover`-t célozta – most a közös `.sz-course-link:hover`-t).

### 0.9.28

- Státusz widget: a meghirdetési időszakok (szeptemberi általános / pótfelvételi / februári keresztféléves) újragondolt megjelenítése. A widget a mai dátum alapján kiemeli a **soron következő indulást**, a többit halványan alálistázza („Később: …"). Új „Meghirdetési időszakok" szekció: időszakonként rövid címke + tájékoztató jelentkezési határidő (hónap/nap) a sorrendhez, előre kitöltött alapértékekkel. Ki is kapcsolható (akkor egyszerű felsorolás).

### 0.9.27

- Repeater widget: **Oszloponkénti igazítás** Stílus-szekció – a kiválasztott Repeater mező minden oszlopához külön állítható a szöveg vízszintes (balra/középre/jobbra) és függőleges (felülre/középre/alulra) igazítása, reszponzívan is. Az összevont (rowspan) cellák igazítása inline `!important`-tal középre-középre kényszerítve marad.

### 0.9.26

- Repeater widget: az összevont (több sort átfogó) celláknál a tartalom vízszintesen és függőlegesen is középre igazodik; a többi cella marad felül.

### 0.9.25

Hotfix a 0.9.24-hez

- Támogatási Táblázat: a sorok a frontenden mostantól automatikusan rendeződnek – szak / szakosodás ABC-sorrend (magyar ábécé, ékezet-független), azon belül a magyar nyelvű változat előre, majd a séma szerinti nyelvsorrend, legbelül az állami finanszírozás az önköltséges előtt. A Kliens szerkesztő a Munkarend csoportok mezőnél tájékoztat erről.
- A „Link beszúrása" gombról lekerült az emoji.

### 0.9.24

Megbeszélés 2026-09-02 – támogatási táblázat, intézményi pontok, indulás időszaka

- **Séma Tervező:** repeater al-mezőkhöz új „Hosszúszöveg" és „Hosszúszöveg (linkelhető)" (`richtext`) típus. A `richtext` a Kliens szerkesztőben szövegközi hiperhivatkozást enged, a frontenden `wp_kses` szűri.
- **Támogatási Táblázat widget:**
  - Szakosodás: új „Beágyazott al-mező: Szakosodás" (alap: `szakosodas`). Ha a variáns-soron ki van töltve, a név `Szak neve - Szakosodás (nyelv nyelven)` formában jelenik meg.
  - Lapozás munkarend-fülönként: állítható küszöb (alap 8), fölötte `oldalak = ⌈n/küszöb⌉`, a sorok kiegyenlítve. Jobb alsó sarokban szám-gombok + nyilak, saját „Lapozó" Stílus-szekcióval.
  - Jelmagyarázat: bekarikázott „?" a Fin. forma fejlécben, hover/fókusz buborékkal (A = …, K = …), ki-be kapcsolható, állítható szöveggel.
  - A finanszírozási forma / ár típusa al-mező alapértelmezett kulcsa a valós sémához igazítva (`finanszirozasi-forma`, `ar-tipusa`).
- **Repeater widget:** `richtext` (wp_kses) és `textarea` (nl2br) cellák; új „Azonos cellák függőleges egyesítése" kapcsoló (oszloponként, `rowspan`).
- **React szerkesztő:** repeater rekord/kártya nézetre vált sok oszlopnál vagy hosszúszöveg-oszlopnál; a `richtext` cella textarea + „Link beszúrása" gombbal és élő előnézettel.
- **Láthatóság szűrő:** a strukturált (repeater/links) mezők „üres/nem üres" vizsgálata JSON-alapú – nincs több „Array to string" warning.

### 0.9.23

- Disable duplicate course button

### 0.9.22

- Urgent fix for course duping on server callback for hub ID

### 0.9.21

- Hot fix widget design

### 0.9.20

- Added new widget to solve problem of different "munkarendek"

### 0.9.19

- Fixed boolean error on frontend

### 0.9.18

feat: Widget audit - fix broken Style controls, add caching, new Repeater widget

- Fixed several Elementor Style controls that silently did nothing because the widgets baked the same CSS properties into inline `style` attributes (which always win over stylesheet rules): Státusz widget typography section, Szaklista widget category-title border/typography and item typography, Linkek widget button typography.
- Added a per-request course-data cache (`SZEducate_Client::get_course_data_for_post()`) and wired it into the Linkek/Státusz/Kulcsszavak widgets, the Dynamic Tag, and the visibility ("hide if empty") filter - a single course page could previously fire the same `course_data` query 4-6+ times per load.
- Fixed the "Képzés Adat" Dynamic Tag producing literal "Array, Array" output when a repeater/links-type field was selected.
- Added `rel="noopener noreferrer"` to the Linkek widget's `target="_blank"` links.
- Added a new **SZEducate Ismétlődő Lista (Repeater)** widget to display repeater-type schema fields (table or card layout, fully styleable).
- Added a new **Képzés Kép** Image Data Dynamic Tag so image-type schema fields can be used as the source for Elementor's native Image widget.

### 0.9.17

- Fixing the realtime editing lock on cross-client mode.

### 0.9.16

feat: Implement custom table plugin for TinyMCE editor

- Added a custom table plugin `szeducate_table` to the TinyMCE editor, providing functionalities for table creation, row/column manipulation, cell merging, and properties editing.
- Enhanced user experience with drag-selection for multiple cells and visual feedback for selected cells.
- Integrated the new table plugin into the editor's toolbar and ensured compatibility with existing features.
- Updated the plugin version to 0.9.16 in the main plugin file.

### 0.9.15

- Refactor code structure for improved readability and maintainability

### 0.9.13

Enhance SZEducate plugin functionality and performance

- Added 'enabled' column to clients table to manage client activation status.
- Updated client queries to filter out disabled clients.
- Introduced AJAX endpoints for pinging clients and hub for connection testing.
- Implemented size limit checks for course data to prevent oversized payloads.
- Enhanced admin interface for managing clients, including token regeneration and status toggling.
- Added uninstall script to clean up plugin data based on admin settings.
- Improved user permissions by allowing Editors to manage courses.
- Added localization support for plugin strings.

### 0.9.12

Enhance database schema and caching mechanisms

- Added `owner_client_id` column to the `szeducate_courses_data` table and updated related indexes.
- Implemented caching for table columns to reduce database queries during course operations.
- Refactored backup manager to utilize cached column data and improved client data retrieval with API token authentication.
- Updated client API to verify webhook signatures using existing API tokens for enhanced security.
- Introduced parallel request handling for client notifications to improve performance during sync operations.
- Added methods for caching course data and invalidating the cache upon updates or deletions.
- Improved error handling and logging for webhook dispatches and client notifications.
- Ensured database migrations run automatically on plugin version changes to maintain schema consistency.

### 0.9.11

-Add new features for course synchronization and management, including full resync and orphaned course cleanup

### 0.9.10

- Enhance SZEducate Client API with batch sync and dynamic column handling
- Added a new endpoint for batch course synchronization in SZEducate_Client_API.
- Implemented flatten_dynamic_columns method to handle dynamic fields in course data.
- Updated webhook_sync_course_batch method to process multiple courses and deletions in a single request.
- Improved delete_local_course_by_hub_id method for better handling of course deletions.
- Introduced SZEducate_Sync_Log class for logging sync operations and errors.
- Enhanced the Hub API to dispatch webhooks for course creation and deletion asynchronously via WP-Cron.
- Updated client-side JavaScript to dynamically build input controls based on field types.
- Fixed input placeholder for options to use semicolons for separation.
- Included sync log functionality for better tracking of sync operations.

### 0.9.9

- Localization fixage

### 0.9.8

- HUB fix

### 0.9.7

- RBAC

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
