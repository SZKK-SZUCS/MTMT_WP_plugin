# Egyéb azonosítós ikonok — mit kell beszerezni

A widget-kártyán minden `external_ids`-beli forráshoz (CLAUDE.md §14/4) egy
logó-gombot mutatunk. Jelenleg feliratos "pill" badge-et adunk minden
forráshoz — ha ide teszel egy egyszínű SVG-t a lenti fájlnévvel, a kód
automatikusan inline-olja (nem `<img>`-ként, hanem az SVG-markup közvetlenül
a HTML-be ágyazva) és CSS-sel színezhetővé válik (`fill: currentColor`).
Ha egy fájl hiányzik, az adott forrás egyszerűen a feliratos pill-t kapja
tovább — semmi nem törik el, nincs kötelező elem ezen a listán.

**Hova kerül:** `assets/img/icons/{slug}.svg`, pontosan ezekkel a nevekkel:

| MTMT `source.name` | Fájlnév | Élőben megerősítve? |
|---|---|---|
| `WoS` | `assets/img/icons/wos.svg` | ✅ igen (docs/field-map.md) |
| `Scopus` | `assets/img/icons/scopus.svg` | ✅ igen (docs/field-map.md) |
| `SZTAKI` | `assets/img/icons/sztaki.svg` | ✅ igen (docs/field-map.md) |
| `PubMed` | `assets/img/icons/pubmed.svg` | ⚠ csak elvárt (CLAUDE.md §14/4), élőben még nem futott össze ilyen rekord |

**Formátum-elvárás:**
- **SVG**, lehetőleg `viewBox`-szal (nem fix `width`/`height`-tal), hogy tetszőleges méretben élesen skálázódjon.
- **Egyszínű** ("monokróm") — a kód automatikusan eltávolítja az esetleges explicit `fill="..."` attribútumokat ÉS a beágyazott `<style>`/class-alapú fill-szabályokat (pl. Illustrator "Export as SVG" tipikus `.cls-1 { fill: #010101; }` mintája) betöltéskor, hogy a CSS mindig felül tudja írni a színt (`fill: currentColor`, ill. a widget "Ikon szín" beállítása) — tehát akkor is működik, ha az eredeti export egy fix fekete/szürke fill-lel jön, nem kell külön "clean" exportot csinálni.
- Nem kell base64/adatURI, sima nyers SVG-fájl elég.
- **A fájlnév PONTOSAN kisbetűs legyen** (`wos.svg`, nem `WoS.svg`) — Windows-fejlesztői gépen a case-insensitive fájlrendszer miatt egy hibásan nagybetűs fájlnév is működni LÁTSZIK, de az éles (Linux) szerveren a `file_exists()` case-sensitive, és a fájl csendben nem töltődik be (visszaesik a feliratos pill-re, hiba nélkül — ezért nem esik le azonnal, csak élesben derül ki).

**Ha új forrás bukkanna fel** (amit az MTMT ezután ad vissza, és most nincs a
fenti listában): a kód akkor is működik — a nyers `source.name`-et feliratos
pill-ként mutatja, amíg nincs hozzá ikon és/vagy rövid felirat-alias felvéve
a `Mtmt_External_Id_Icons::SOURCES` tömbbe (`includes/class-mtmt-external-id-icons.php`).

Kapcsolódó döntés: docs/decisions.md #81.
