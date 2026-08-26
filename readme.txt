=== JKK MTMT Publications ===
Contributors: Szurofka Márton, MFÜI
Tags: mtmt, publications, elementor, publikaciok, tudomanyos
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.2.0
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
3. A "JKK MTMT" admin menüpontban hozz létre legalább egy query-profilt
   (intézmény-MTID, szerző-MTID-lista, vagy haladó cond JSON).
4. Futtasd a szinkront: `wp jkk-mtmt sync` (WP-CLI), vagy várd meg a heti
   automatikus futást (ha a cron-ütemezés már él).

== Changelog ==

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
* Élesben validálva: JKK profil (SZE Járműipari Kutatóközpont, mtid 19662),
  767/0/0 (új/frissített/hiányzó), pontosan egyezik az MTMT saját nyilvántartott
  publikációszámával.

= 0.1.0 =
* Kezdeti bootstrap: plugin-header, aktivátor (tábla-migráció dbDelta-val).

== Upgrade Notice ==

= 0.2.0 =
Az ingest mag első élesben validált verziója. A plugin továbbra is 0.x
verziószámon fejlődik — 1.0.0-t csak akkor kap, ha a megrendelő explicit
jóváhagyja, hogy a rendszer kész (lásd CLAUDE.md §10.2).
