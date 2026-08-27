=== MTMT Sync ===
Contributors: Szurofka Márton, MFÜI
Tags: mtmt, publications, elementor, publikaciok, tudomanyos
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MTMT-alapú publikációs lista jóváhagyással és Elementor megjelenítéssel.

== Description ==

A plugin hetente lekérdez publikációkat az MTMT (Magyar Tudományos Művek Tára)
publikus REST API-jából, megadott szűrőprofilok (intézmény- vagy szerző-alapú)
szerint. A behúzott tételek wp-adminban jóváhagyhatók/elutasíthatók és kézzel
gazdagíthatók (indexkép, kutatócsoport/szakmai terület, projektazonosító-ellenőrzés),
majd egy Elementor widget jeleníti meg őket a jóváhagyott listából, évekre bontva,
kereshetően/szűrhetően.

Alapelv: az MTMT a publikációs adat forrása; a jóváhagyási státusz és a kézi
kiegészítések forrása a plugin saját adatbázisa. A kettőt az MTMT rekord-azonosító
(`mtid`) köti össze.

A query-profilok (mit kérdezzen le a rendszer az MTMT-ből) telepítésenként,
adminból konfigurálhatók — nincs semmilyen intézmény- vagy szerző-azonosító
kódba írva, így a plugin más kutatócsoportok/intézmények oldalain is bevethető
kódmódosítás nélkül.

A teljes fejlesztési terv és a fázisonkénti elfogadási kritériumok a repóban,
a `CLAUDE.md` és a `docs/roadmap.md` fájlokban vannak dokumentálva.

== Installation ==

1. Töltsd fel a plugint (vagy klónozd a repót) a `wp-content/plugins/` alá.
2. Aktiváld a wp-admin Pluginok oldalán — ez létrehozza a szükséges táblákat.
3. Az "MTMT" admin menüpontban hozz létre legalább egy query-profilt
   (intézmény-MTID, szerző-MTID-lista, vagy haladó cond JSON).
4. Futtasd a szinkront: `wp mtmt sync` (WP-CLI), vagy várd meg a heti
   automatikus futást (ha a cron-ütemezés már él).

== Changelog ==

= 0.4.0 =
* "Szakmai terület" taxonómia (a korábbi "kutatócsoport" fogalom átnevezve,
  megrendelővel egyeztetve ugyanaz a mechanizmus): saját tábla + pivot tábla
  (nem WP-taxonómia, mivel a publikáció nem post-típus), publikációnként több
  terület is rendelhető.
* Új "Területek" admin almenü: terület létrehozása/törlése, mindegyikhez
  opcionálisan egy WP-oldal párosítható (a jövőbeli szakmai aloldalakhoz).
* Terület-hozzárendelés a szerkesztő/gazdagító űrlapon, `mtmt_classify`
  jogosultsághoz kötve (szerveroldalon is védve, nem csak a UI-ban elrejtve).
* A moderációs listán "Szakmai terület" oszlop + szűrő-lenyíló.
* Teljes funkció opt-in plugin-beállítás (Beállítások → Funkciók) — kikapcsolva
  a terület-UI sehol nem jelenik meg, a korábban elmentett hozzárendelések
  megmaradnak a DB-ben.

= 0.3.0 =
* Moderációs lista (jóváhagyás/elutasítás, tömeges műveletek, szűrés
  státusz/év/profil szerint), szerkesztő/gazdagító űrlap (indexkép WP
  média-feltöltővel, támogatás felülbírálás, projektazonosító + ellenőrizve
  pipa, kiemelt cikk jelölő).
* Kétszintű jogosultság: `mtmt_moderate` (jóváhagyás/elutasítás, alap
  szerkesztés) és `mtmt_classify` (kutatócsoport-besorolás, projekt-
  ellenőrzés), alapból Editor és Administrator szerepkörre.
* Admin menü átrendezve: a moderációs lista lett a top-level "MTMT" oldal
  (pending-szám piros buborékban), a Profilok/Beállítások almenükké váltak.
* Bulk kiemelés/kiemelés-visszavonás a listán, feltételesen (csak ha a
  "Kiemelt cikk" funkció be van kapcsolva Beállításokban).
* Minden szerkeszthető mezőnél közérthető magyarázó szöveg az adminban.

= 0.2.0 =
* Ingest mag: MTMT API-kliens (cond-építés, lapozás, retry/backoff), mapper
  (docs/field-map.md szerint), repository upsert/diff, WP-CLI `sync` parancs.
* "Dobozos" admin felület a query-profilokhoz (intézmény / szerző-lista /
  haladó cond JSON scope-választóval) — nincs hardcode-olt azonosító a kódban.
* Tartalomváltozás-detektálás: ha egy már jóváhagyott/elutasított rekord
  MTMT-oldali tartalma ténylegesen megváltozik, a szinkron visszaállítja
  `pending` státuszba — nincs csendes automatikus felülírás.
* DOI-only profil-szűrési opció (`--doi-only` CLI flag, illetve checkbox az
  admin felületen).
* Élesben validálva: teszt-profil (SZE Járműipari Kutatóközpont, mtid 19662),
  767/0/0 (új/frissített/hiányzó), pontosan egyezik az MTMT saját nyilvántartott
  publikációszámával.
* Heti WP-Cron ütemezés + futás-napló + email-értesítés (csak akkor, ha volt
  valódi új/frissült tétel) + kézi "Szinkron most" gomb az adminban.
* Plugin átnevezve "MTMT Sync"-re — a korábbi belső elnevezés az első
  megrendelő szervezetre (JKK) utalt, de a plugint több szervezet is
  használni fogja, ezért semmilyen szervezet-specifikus név nem maradhatott
  a technikai névtérben (osztályok, táblák, szövegdomain, admin menü).

= 0.1.0 =
* Kezdeti bootstrap: plugin-header, aktivátor (tábla-migráció dbDelta-val).

== Upgrade Notice ==

= 0.4.0 =
"Szakmai terület" taxonómia + aloldal-előkészítés, opt-in beállítással. A plugin
továbbra is 0.x verziószámon fejlődik — 1.0.0-t csak akkor kap, ha a megrendelő
explicit jóváhagyja, hogy a rendszer kész (lásd CLAUDE.md §10.2).

= 0.3.0 =
Moderáció + jogosultságok. A plugin továbbra is 0.x verziószámon fejlődik —
1.0.0-t csak akkor kap, ha a megrendelő explicit jóváhagyja, hogy a rendszer
kész (lásd CLAUDE.md §10.2).

= 0.2.0 =
Az ingest mag első élesben validált verziója.
