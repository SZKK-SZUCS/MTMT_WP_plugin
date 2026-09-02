=== MTMT Sync ===
Contributors: Szurofka Márton, MFÜI
Tags: mtmt, publications, elementor, publikaciok, tudomanyos
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.13.1
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

= Nyelvek =

A plugin admin-felülete magyar nyelvű forrásszöveggel készült, angol
fordítással együtt csomagolva (`languages/mtmt-sync-en_US.mo`) — ha a
WordPress-telepítés nyelve angol (`en_US`), az admin-felület automatikusan
angolul jelenik meg, külön beállítás nélkül. Új nyelvi fordítás a
`languages/mtmt-sync.pot` sablonból készíthető (pl. Poedit-tel), a kész
`.po`/`.mo` párt ugyanabba a `languages/` mappába kell tenni.

== Installation ==

1. Töltsd fel a plugint (vagy klónozd a repót) a `wp-content/plugins/` alá.
2. Aktiváld a wp-admin Pluginok oldalán — ez létrehozza a szükséges táblákat.
3. Az "MTMT" admin menüpontban hozz létre legalább egy query-profilt
   (intézmény-MTID, szerző-MTID-lista, vagy haladó cond JSON).
4. Futtasd a szinkront: `wp mtmt sync` (WP-CLI), vagy várd meg a heti
   automatikus futást (ha a cron-ütemezés már él).

= Éles (production) cron-ütemezés =

A WordPress alapértelmezett ("látogató-vezérelt") cron-je csak akkor fut le,
ha éppen érkezik egy oldalbetöltés — kis forgalmú vagy időszakosan látogatott
oldalon ez azt jelentheti, hogy a heti szinkron KÉSVE, vagy egyáltalán nem
indul el. Két bevett megoldás, bármelyik jó:

**A) Rendszeres külső "poke" a `wp-cron.php`-ra** (nem igényel
`DISABLE_WP_CRON`-t, egyszerűen kiváltja a látogató-vezérelt triggert egy
megbízható, rendszeres HTTP-hívással):

`curl -s -A 'valami-egyedi-user-agent' 'https://<a-site-domainje>/wp-cron.php?doing_wp_cron'`

Ha már van egy központi cron-pinger szolgáltatásod más WP-oldalakhoz
(pl. egy Docker-konténer, ami rendszeres időközönként meghívja a
domain(ok) `wp-cron.php`-ját), ide a plugint futtató oldal domainje simán
felvehető — nem kell hozzá semmilyen plugin-specifikus paraméter, a sima
`wp-cron.php?doing_wp_cron` hívás minden esedékes WP-cron eseményt
(a heti MTMT-szinkront is) elindít, amikor esedékes. 5 perces
gyakoriság bőven elég egy heti futáshoz.

Példa egy önálló, `docker-compose`-ba tehető pinger-szolgáltatásra (a plugint
futtató WordPress-szolgáltatással egy stack-ben, vagy attól teljesen
függetlenül, egy központi "minden site"-pingerben is elhelyezhető):

`
  mtmt-cron-pinger:
    image: alpine:latest
    restart: unless-stopped
    command: >
      /bin/sh -c "apk add --no-cache curl &&
      while true; do
        curl -s -A 'MTMT-Sync-Cron-Pinger' 'https://<a-site-sajat-domainje>/wp-cron.php?doing_wp_cron' > /dev/null 2>&1;
        sleep 300;
      done"
`

(Cseréld ki a `<a-site-saját-domainje>`-t a ténylegesen futtatott WordPress
domainjére. Ha egy meglévő, több site-ot is pingelő konténered van, elég a
`while` ciklusba egy újabb `curl`-sort felvenni ugyanezzel a mintával.)

**B) `DISABLE_WP_CRON` + közvetlen WP-CLI hívás** (determinisztikusabb
időpont, de rendszer-cron hozzáférést igényel a szerveren):

1. `wp-config.php`: `define( 'DISABLE_WP_CRON', true );`
2. Egy VALÓDI rendszer-cron (cPanel Cron Jobs, Plesk ütemezett feladat,
   szerver-szintű crontab) hetente hívja: `wp mtmt sync --path=...`
   (vagy: `wp cron event run mtmt_weekly_sync`).

Egyik módszer sincs a kódba kényszerítve — a plugin mindkettővel (vagy akár
egyikkel sem, csak a látogató-vezérelt alapbeállítással) működik, tisztán
üzemeltetési döntés, telepítésenként eltérhet.

== Changelog ==

= 0.13.1 =
* Widget év-fülek: keresés vagy (frontend) szakmai terület-szűrés után
  már csak azok az évek jelennek meg, amikre ténylegesen van találat. Ha
  éppen egy olyan év-fülön állsz, amire a szűréssel nincs eredmény, a
  widget automatikusan az "Összes" nézetre vált.

= 0.13.0 =
* Új widget-beállítás: "Rendezés" (Legújabb elöl / Legrégebbi elöl / Cím
  szerint A–Z / SJR-negyed szerint) mindkét Elementor widgeten,
  alapértelmezés "Legújabb elöl". A beállított sorrend keresés, év-váltás
  és lapozás után is érvényben marad. A besorolás nélküli tételek
  SJR-rendezésnél mindig a lista végére kerülnek.
* A moderációs lista mostantól megjegyzi az URL-ben, hol jársz (oldalszám,
  szűrők, rendezés) — egy rekord szerkesztéséből kilépve ugyanarra az
  oldalra és ugyanazzal a szűréssel térsz vissza, nem az első oldalra.
* Az "SJR" oszlop a moderációs listán rendezhető lett; a "Szűrés" gomb
  megtartja az aktív rendezést.
* Javítva: a lapozás ritkán megismételhetett vagy kihagyhatott tételeket,
  ha több publikációnak azonos volt a megjelenési éve — a sorrend
  mostantól minden esetben egyértelmű.

= 0.12.0 =
* Admin-felület minden szövege átírva egyszerűbb, köznyelvi
  megfogalmazásra (Beállítások, Profilok, Jogosultságok, Területek,
  moderációs lista, Elementor widget-beállítások) — a belső fejlesztési
  zsargon és dokumentum-hivatkozások eltávolítva.
* Javítva: az email-értesítő "Jóváhagyás megnyitása" gombja a Profilok
  oldalra mutatott, nem a moderációs listára.

= 0.11.0 =
* Új: a heti automatikus szinkron ideje (nap + óra) mostantól
  konfigurálható a Beállítások oldalon (alapértelmezés: hétfő 03:00) —
  korábban mindig aktiváláskor/önjavításkor "most" induló időponttól
  számítva futott, tehát véletlenszerű napra/órára eshetett.
* A háttérben önmagát újraütemező egyszeri eseményekre váltottunk a
  korábbi fix-intervallumos ismétlődés helyett, hogy a nyári/téli
  időszámítás-váltás ne csúsztassa el a beállított órát.

= 0.10.1 =
* Kritikus javítás: a heti `mtmt_weekly_sync` cron-esemény bizonyos
  telepítéseken (pl. már eleve "aktívként" jelenlévő plugin egy sablon
  Docker-image-ben) sosem lett beütemezve, mert az aktiválási hook nem
  futott le. Mostantól minden oldalbetöltéskor önjavítóan ellenőrzi és
  pótolja a hiányzó ütemezést, kézi beavatkozás nélkül.

= 0.10.0 =
* Egyéb azonosítós (WoS/Scopus/SZTAKI/PubMed/ResearchGate) SVG-ikonok
  élesítve — színezhetőségi hiba javítva (beágyazott `<style>`-alapú
  fill felülírta volna a widget színbeállítását), fájlnevek kisbetűsre
  javítva.
* Új widget-beállítás: "Egyéb azonosítók megjelenítése" (Ikon és szöveg /
  Csak ikon / Csak szöveg), ikon-méret/-szín/pill-dizájn a Stílus fülön.
* Widget vizuális felfrissítés egy megrendelői referencia-kép alapján:
  soronkénti lista (dobozolt kártya-rács helyett), alulvonalas év-fülek,
  forrás+év kiemelő-színű sor, típus-badge a sor jobb szélén, körkörös
  nyíl-CTA, számozott lapozás ellipszissel.
* Kritikus javítás: hosszú kiadványtípus-szöveg (pl. "Folyóiratcikk")
  belelógott a címbe keskenyebb szélességnél — a típus-badge/nyíl-CTA
  mostantól flexbox-alapú, tartalom-szélesség-érzékeny elrendezésű,
  semmilyen szélességnél nem csúszhat rá a szövegre.
* Teljes körű Stílus-fül lefedettség: cím-szín, tipográfia, kártya-árnyék,
  előnézeti kép mérete/lekerekítése, badge-ek formája, nyíl-gomb
  méret/formája, kereső mezők lekerekítése, lapozás színei/formája —
  gyakorlatilag minden vizuális elem testreszabható lett.

= 0.9.0 =
* Angol fordítás becsomagolva (`languages/mtmt-sync-en_US.po`/`.mo`, 242 string) —
  angol WP-locale-lal futó telepítéseken az admin-felület automatikusan
  angolul jelenik meg.
* README kiegészítve az éles (production) cron-ütemezés két bevett
  megoldásával (rendszeres `wp-cron.php`-poke, ill. `DISABLE_WP_CRON` +
  rendszer-cron/WP-CLI), központi cron-pinger-példával.
* Új "Jogosultságok" admin almenü: minden WordPress-szerepkörhöz
  beállítható, kapja-e meg a `mtmt_moderate`/`mtmt_classify`
  kapabilitást — mentéskor azonnal érvénybe lép, korábbi
  szerepkör-testreszabás pluginfrissítéskor sem vész el.
* Egyéb azonosítós (WoS/Scopus/SZTAKI/PubMed) SVG-ikon támogatás a
  widget-kártyán — a fájl hiányában szépen visszaesik a meglévő
  feliratos pill-badge-re.
* Kézi "Teljes szinkron most" gomb a Beállítások oldalon — ugyanazt
  futtatja, mint a heti cron (minden profil + email, ha volt aktivitás).
* "Adatok törlése" gomb a Pluginok listaoldalon — az 5 saját
  táblát üríti, a beállításokat nem érinti.
* Kritikus javítás: a kézi/cron szinkron hamis "sikeres" beszúrást
  jelentett akkor is, ha a tényleges adatbázis-írás meghiúsult (pl. mert
  a tábla fizikailag hiányzott) — mostantól a valódi hiba jelenik meg, és
  egy hiányzó tábla a séma-ellenőrzés automatikus önjavításával, kézi
  deaktiválás/reaktiválás nélkül helyreáll.

= 0.8.0 =
* Email-értesítő újratervezve: HTML-email (a korábbi sima szöveg helyett),
  világos, designolt háttérrel és a pluginba becsomagolt kiadói logóval
  (`assets/img/mfui-logo.png`) — minden site-on ugyanaz a fejléc-kép megy
  ki, nem site-onkénti admin-beállítás.
* A profil `#ID` helyett a profil neve olvasható az email törzsében.
* Hibás futásnál kiemelt (piros) blokk az email-törzsben.

= 0.7.0 =
* Profil-előnézet ("Előnézet" gomb az "Új profil" űrlapon): a beírt
  szűrésből 5 mintarekordot kér le az MTMT-től mentés/szinkron-indítás
  nélkül, mutatja a találatszámot (figyelmeztetéssel, ha gyanúsan nagy —
  jel arra, hogy a szűrés valószínűleg nem érvényesült) és minta-
  címeket/szerzőket, hogy vizuálisan is ellenőrizhető legyen a profil
  helyessége mentés előtt.

= 0.6.0 =
* Automatikus frissítés bekötve: Plugin Update Checker (PUC) v5.7 bevendorolva
  (`lib/plugin-update-checker/`), a nyilvános GitHub repóra (`main` ág)
  figyel. Az élő oldalak innentől a WP frissítő felületén látják az új
  verziókat, ahogy egy GitHub Release kikerül.
* Nem terheli a nyilvános oldalbetöltéseket — a frissítés-ellenőrzés csak
  wp-adminban fut.

= 0.5.0 =
* Elementor widgetek: "A" — összesítő publikációs lista (év-fülek, kereső,
  opcionális szakmai terület-szűrő), "B" — egy szakmai területre vagy
  lekérdezési profilra szűkített kiemelt-publikációs lista (csak a "Kiemelt
  cikk" funkció bekapcsolva jelenik meg Elementorban).
* Kártya-mezők: cím, szerzők (5 fölött rövidítve), szakmai terület-badge,
  forrás, DOI, kiadványtípus-badge, megjelenés éve, SJR-negyed-badge, egyéb
  azonosítós gombok. Teljes kártya kattintható (DOI, vagy DOI hiányában az
  MTMT nyilvános oldala).
* Szerver-oldali (GD) placeholder-kép indexkép hiányában, a publikáció
  címével beégetve (magyar ékezetekkel is), becsomagolt Open Sans Bold
  fonttal; GD/font hiányában automatikusan CSS-overlay-re esik vissza.
* Widget-szinten szerkeszthető feliratok (Tartalom fül) + Elementor Stílus
  fül (színek, tipográfia, kártya-megjelenés).
* Keresés/év-váltás/lapozás/terület-szűrés AJAX-fragmenttel, teljes
  oldal-újratöltés nélkül; a widgetek kizárólag a saját táblát olvassák.
* Cache-verziószámláló: egy jóváhagyás/gazdagítás/szinkron után a widget
  azonnal (nem csak a korábbi 5 perces cache-lejárat után) friss adatot mutat.

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

= 0.13.1 =
Widget-finomítás: keresés/terület-szűrés után eltűnnek az üres év-fülek.
Csak a widget működését érinti, adatot nem.

= 0.13.0 =
Widget-rendezés választó (alapból "legújabb elöl"), a moderációs lista
megjegyzi az oldalszámot/szűrőt szerkesztés közben, és egy lapozási hiba
javítva. A plugin továbbra is 0.x verziószámon fejlődik — 1.0.0-t csak
akkor kap, ha a megrendelő explicit jóváhagyja, hogy a rendszer kész
(lásd CLAUDE.md §10.2).

= 0.12.0 =
Admin-felület szövegei egyszerűsítve, egy hibás email-link javítva. A
plugin továbbra is 0.x verziószámon fejlődik — 1.0.0-t csak akkor kap, ha
a megrendelő explicit jóváhagyja, hogy a rendszer kész (lásd CLAUDE.md
§10.2).

= 0.11.0 =
Új: a heti automatikus szinkron ideje (nap + óra) mostantól konfigurálható
a Beállítások oldalon. A plugin továbbra is 0.x verziószámon fejlődik —
1.0.0-t csak akkor kap, ha a megrendelő explicit jóváhagyja, hogy a
rendszer kész (lásd CLAUDE.md §10.2).

= 0.10.1 =
Kritikus javítás: bizonyos telepítéseken a heti automata szinkron sosem
futott le, mert a cron-esemény nem lett beütemezve — mostantól önjavítóan
helyreáll. Frissítés ajánlott minden site-on.

= 0.10.0 =
Widget vizuális felfrissítés (soronkénti lista, alulvonalas év-fülek,
számozott lapozás), teljes körű Stílus-fül lefedettség, egyéb azonosítós
ikonok élesítve, kritikus reszponzivitási javítás. A plugin továbbra is
0.x verziószámon fejlődik — 1.0.0-t csak akkor kap, ha a megrendelő
explicit jóváhagyja, hogy a rendszer kész (lásd CLAUDE.md §10.2).

= 0.9.0 =
Angol fordítás, jogosultság-admin UI, egyéb azonosítós ikonok, kézi
teljes-szinkron és adat-reset gomb, valamint egy kritikus javítás (a
szinkron mostantól nem jelent hamis sikert, ha a mögöttes DB-írás
meghiúsul). A plugin továbbra is 0.x verziószámon fejlődik — 1.0.0-t csak
akkor kap, ha a megrendelő explicit jóváhagyja, hogy a rendszer kész
(lásd CLAUDE.md §10.2).

= 0.8.0 =
Email-értesítő újratervezve: HTML + kiadói logó. A plugin továbbra is 0.x
verziószámon fejlődik — 1.0.0-t csak akkor kap, ha a megrendelő explicit
jóváhagyja, hogy a rendszer kész (lásd CLAUDE.md §10.2).

= 0.7.0 =
Profil-előnézet ("Előnézet" gomb az "Új profil" űrlapon), mentés előtti
ellenőrzéshez. A plugin továbbra is 0.x verziószámon fejlődik — 1.0.0-t
csak akkor kap, ha a megrendelő explicit jóváhagyja, hogy a rendszer kész
(lásd CLAUDE.md §10.2).

= 0.6.0 =
Automatikus frissítés a GitHub Release-ekből (PUC v5). A plugin továbbra is
0.x verziószámon fejlődik — 1.0.0-t csak akkor kap, ha a megrendelő explicit
jóváhagyja, hogy a rendszer kész (lásd CLAUDE.md §10.2).

= 0.5.0 =
Elementor widgetek (összesítő + terület/profil-szűkített), placeholder-kép,
widget-szintű szöveg/stílus-testreszabás. A plugin továbbra is 0.x
verziószámon fejlődik — 1.0.0-t csak akkor kap, ha a megrendelő explicit
jóváhagyja, hogy a rendszer kész (lásd CLAUDE.md §10.2).

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
