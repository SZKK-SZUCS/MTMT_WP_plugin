# Fejlesztési roadmap

Ez a CLAUDE.md §12 fázislistája + a §14-es megbeszélés-kiegészítések összefésülve,
egy sorrendbe rendezve. Ha egy fázis scope-ja bővült a megbeszélésen, azt itt jelzem;
maga a részletes indoklás a CLAUDE.md §14-ben és a docs/decisions.md-ben van.

Frissítsd ezt a fájlt, amikor egy fázis lezárul vagy a sorrend/scope változik —
ez az elsődleges hely, ahol "hol tartunk" kérdésre válaszolni lehet.

---

## ✅ Fázis 0 — Felderítés

**KÉSZ.** Éles mezőtérkép `docs/field-map.md`-ben, SZE/JKK intézményi szűkítés és a
támogatás-mező hiánya verifikálva.

## ✅ Fázis 1 — Ingest mag

**KÉSZ, élesben igazolva.** api-client + mapper + repository + upsert/diff + WP-CLI
`sync`. Local WP-site-on (mtmt-wp-plugin), JKK profillal (mtid=19662):
`767 új / 0 frissített / 0 hiányzó` — pontosan a JKK saját `publicationCount`-jával
egyezik. Admin "dobozos" profil-beállító oldal is elkészült és élesben tesztelve
(nem volt eredetileg a Fázis 1 elfogadási kritériumban, de a "több site-on
újrahasználható" elv miatt idekerült).

## ✅ Fázis 1.5 — Utólagos kiegészítés a meglévő ingest-kódhoz

**KÉSZ, mergelve (PR #1), élesben validálva.** Két pont a megbeszélésből közvetlenül a már megírt Fázis 1
fájlokat módosítja — érdemes ezeket lezárni, MIELŐTT a Fázis 2 cronja éles,
automatikus futtatásba kerül (különben az első automata re-sync már a régi,
"csendes auto-apply" logikával futna approved rekordokon).

- **MTMT-oldali tartalomváltozás → `pending`-be visszaállítás** (CLAUDE.md §14/7,
  §4.3). Érinti: `Mtmt_Publication_Repository::upsert()`. Kell egy
  tartalom-diff lépés (mapped-row MTMT-forrású oszlopait a tárolt értékekkel
  összevetni, NEM a `raw_json`-t, ld. decisions.md #19), és ha van érdemi eltérés
  és a rekord `approved`/`rejected` volt, essen vissza `pending`-re.
- **DOI-only query-opció** (CLAUDE.md §14/2). Érinti: `Mtmt_Profiles_Page`
  (checkbox az űrlapon) + `Mtmt_Profile_Command::create()` (CLI-oldali
  megfelelő). Cond: `identifiers.source.name;eq;DOI`, VERIFIKÁLVA. Profilonként
  kapcsolható, nem globális.

*Kész, ha:* egy már `approved` teszt-rekord MTMT-oldali módosítás után
újraszinkronizálva visszaesik `pending`-be; egy profil DOI-only kapcsolóval
létrehozva ténylegesen csak DOI-s rekordokat húz be.

**Éles ellenőrzéshez** (Local site, Site Shell):
1. `wp mtmt sync` — futtasd újra a JKK profilt, kézi teszthez jó, ha wp-adminban
   előtte kézzel `approved`-re állítasz egy rekordot ($wpdb-n vagy közvetlen SQL-lel,
   admin UI még nincs Fázis 3-ig) — utána a `content_changed`/`reverted_to_pending`
   logikának NEM szabad visszaesnie, ha maga a tartalom nem változott (ez a
   kockázatosabb irány: hamis pozitív visszaesés minden héten).
2. `wp mtmt profile create --label="JKK csak DOI" --cond='[{"field":"directInstitutes","op":"in","value":"19662"}]' --doi-only`
   majd `wp mtmt sync --profile=<uj-id>` — várhatóan ~372 rekordot hoz be, nem 767-et.
3. Ugyanez az admin "Új profil" űrlapon a "Csak DOI azonosítóval rendelkező rekordok"
   pipával — ellenőrizd, hogy a listában a cond JSON tartalmazza az
   `identifiers.source.name` feltételt.

## ✅ Fázis 2 — Cron + napló + email + kézi szinkron

**KÉSZ, mergelve (PR #1).** Élesben validálva a Local site-on: kézi "Szinkron
most" gombbal a DOI-only profil 372/767-et hozott be (egyezik az élő
API-verifikációval), a "frissített" számláló regressziója javítva és
ellenőrizve. A cron-triggerelt email tényleges kiküldése SMTP-plugin nélküli
helyi Local-oldalon nem volt tesztelhető — élesedéskor validálandó.
Eredeti scope (§6) + két új pont a megbeszélésből:

- Heti `mtmt_weekly` cron + `wp mtmt sync` ugyanarra a logikára.
- Kötegelt, folytatható sync; futás-napló (mikor, hány új/frissült/hiányzó/hibás).
- **ÚJ: email-értesítés** a napló alapján, ha volt új/frissült tétel a futásban
  (§14/5). Globális, site-szintű címzett-lista egyelőre (nem profilonkénti).
- **ÚJ: kézi "Szinkron most" gomb** az adminban (§14/6) — bármikor elindítható,
  nem csak cronból/CLI-ből. Figyelem: 767 rekord ~24s volt élesben; nagyobb
  intézménynél timeout-kockázatot itt kell kezelni (emelt `set_time_limit` vagy
  async/AJAX-progress).

*Kész, ha:* ütemezetten fut, napló látszik adminban, `DISABLE_WP_CRON`-nal
rendszer-cronból is hívható, email megérkezik új tételnél, a gomb megnyomható
és nem fut timeoutba a JKK-méretű profilon.

**Éles ellenőrzéshez** (Local site, Site Shell + wp-admin):
1. **Regresszió a "frissített" számlálóra** (a legfontosabb, saját teszt közben
   találtam hibát benne, most javítva): `wp mtmt sync --profile=<jkk-id>`
   kétszer egymás után, rövid időn belül. A MÁSODIK futásnak ~0 "frissítve"-t
   kell mutatnia (semmi nem változott MTMT-oldalon eközben) — ha 767-et (vagy
   közel ennyit) mutatna újra, az azt jelentené, hogy a fix nem működik és
   minden héten kimenne az email feleslegesen.
2. **Beállítások oldal** (JKK MTMT → Beállítások): adj meg egy valódi email-címet
   a "Címzettek" mezőben, mentsd el, majd wp-adminban "Szinkron most"-tal
   futtass egy syncet — MANUÁLIS triggernél NEM szabad emailnek mennie (csak
   cronnál megy). `wp cron event run mtmt_weekly_sync` egy CLI-ből
   szimulált cron-futás — ha van új/frissült tétel, ennél MENNIE kell az emailnek
   (ha a WP-oldal ki tud küldeni emailt — helyi fejlesztésnél ehhez kellhet
   pl. egy SMTP-plugin, ha a Local nem küld ki natívan leveleket).
3. **Futás-napló** (JKK MTMT → Beállítások, alsó tábla): minden fenti futásnak
   meg kell jelennie egy sorban, helyes trigger-típussal (cli/manual/cron).
4. **Cron-ütemezés**: `wp cron event list` — legyen benne `mtmt_weekly_sync`,
   "weekly" recurrence-szel.
5. **Kézi gomb timeoutja**: a JKK profilon (767 rekord, ~24s) a "Szinkron most"
   gombnak simán le kell futnia böngészőből is, admin-notice-ban a helyes
   számokkal.

## ✅ Fázis 3 — Moderáció + gazdagítás + jogosultságok

**KÉSZ, mergelve (PR #2), élesben validálva.** A megrendelői kérésre PR előtt
mezőmagyarázatok + bulk kiemelés is bekerült. 0.3.0-ban kiadva.
Eredeti scope (§8) + két új mező a megbeszélésből:

- `WP_List_Table` lista (indexkép, cím+szerzők egy oszlopban, forrás, év, típus,
  SJR-badge, MTMT-státusz, DOI/MTMT linkek, státusz — a kutatócsoport/terület
  oszlop Fázis 4-ig kimarad, addig nincs mihez kötni), sor- és tömeges műveletek.
- Szerkesztő/gazdagító űrlap: indexkép (WP média-feltöltő), támogatás-override,
  projektazonosító + ellenőrizve pipa (utóbbi `mtmt_classify`-hoz kötve).
- **ÚJ: "Kiemelt cikk" checkbox** (§14/9, `is_featured`) a szerkesztő űrlapon,
  **saját be/ki plugin-beállítással** (§14/11, Beállítások oldal "Funkciók"
  szakasza) — ha ki van kapcsolva, a checkbox nem jelenik meg.
- Két capability (`mtmt_moderate`, `mtmt_classify`) — **döntés a megrendelőtől**:
  mindkettő alapból Editor+Administrator szerepkörre kerül aktiváláskor, nincs
  még finomabb role→capability admin UI.
- **Menü-átrendezés**: a top-level "MTMT" mostantól a moderációs lista
  (`mtmt_moderate` capability — ezt látják a moderátorok), a Profilok/
  Beállítások `manage_options`-hoz kötött almenükké váltak (a moderátorok nem
  is látják őket). Menü-badge: függőben lévők száma piros buborékban.
- *Előkészítve Fázis 4-hez, de még nem építve*: a "szakmai terület" választó
  helye a formban egyelőre nincs is jelen (nem csak elrejtve) — Fázis 4
  fogja ténylegesen hozzáadni a feltételes blokkot.

*Kész, ha:* jóváhagyás/elutasítás/visszavonás megy (sor-műveletként ÉS a
szerkesztő űrlapon is), tömeges jóváhagyás/elutasítás a listából, kézi mezők
(thumbnail, funding_override, project_*, is_featured) syncnél túlélnek,
jogosultságok elkülönülnek (moderate-only user nem tudja a projekt-ellenőrzés
pipát állítani), nonce+capability mindenhol, a kiemelt-cikk toggle ki/bekapcsolva
tényleg elrejti/mutatja a checkboxot.

**Éles ellenőrzéshez** (Local site, wp-admin):
1. A bal oldali admin menüben most már **"MTMT"** a top-level tétel a
   moderációs listával landol, és ha van függőben lévő tétel, piros
   buborékban mutatja a darabszámot. "Profilok" és "Beállítások" almenüként
   jelenik meg alatta.
2. Nyisd meg egy tétel "Szerkesztés/Gazdagítás" linkjét — tölts fel egy
   indexképet (WP média-könyvtárból), adj meg egy projektazonosítót, pipáld
   be az "Ellenőrizve"-t, mentsd el — töltsön be újra a mentett értékekkel.
3. A lista tetején lévő sor-műveletekkel jóváhagyj/utasíts el egy-egy tételt
   közvetlenül a listából (nem kell megnyitni a szerkesztőt).
4. Pipálj be 2-3 sort, válaszd a "Jóváhagyás" tömeges műveletet a lenyílóból,
   "Alkalmaz" — mindegyik váltson `approved`-ra egyszerre.
5. Szűrd a listát státusz/év/profil szerint felül.
6. Beállítások oldalon kapcsold be a "Kiemelt cikk" funkciót — a szerkesztő
   űrlapon jelenjen meg a checkbox; kapcsold ki — tűnjön el.
7. Ha van egy másodlagos (nem admin) teszt-user Editor szerepkörrel: jelentkezz
   be azzal, nézd meg, hogy látja-e a moderációs listát, és NE tudja beállítani
   a projekt-ellenőrzés pipát, ha esetleg nincs rajta a `mtmt_classify` (ebben
   a körben Editor mindkettőt megkapja alapból, szóval ez inkább csak a
   capability-szétválasztás elvi ellenőrzése, nem éles korlátozás — a
   finomabb role-mapping később, ha kell).

## ✅ Fázis 4 — Taxonómia + aloldalak ("Szakmai terület")

**KÉSZ, mergelve (PR #3), élesben validálva. 0.4.0-ban kiadva.**
Eredeti scope (§7), a megbeszélésen **megerősítve átnevezve** "Szakmai terület"-re
(docs/decisions.md #18) — NEM külön, második kategória-rendszer a kutatócsoport
mellett, ugyanaz a mechanizmus.

- **NEM WP-taxonómia** (a publikáció nem post-típus, CLAUDE.md §7 explicit
  javaslata szerint) — sima saját tábla (`wp_mtmt_topic_areas`: label +
  `page_id`) és pivot tábla (`wp_mtmt_pub_topic_area`) a publikáció↔terület
  sokoldalú kapcsolathoz. `MTMT_DB_VERSION` 2->3.
- Terület↔aloldal párosítás: egy `wp_dropdown_pages()`-es oldal-választó a
  Területek admin oldalon (`manage_options`, saját almenü "Területek" néven).
- Terület-hozzárendelés egy publikációhoz a szerkesztő űrlapon, **`mtmt_classify`-hoz
  kötve** (CLAUDE.md §7: "A besorolás kézi, a moderáció része, és külön
  jogosultsághoz kötött") — moderate-only user csak olvashatja, mit lát,
  szerveroldalon is védve, nem csak a form UI-ban elrejtve (ugyanaz a minta,
  mint a project_verified-nél, lásd docs/decisions.md #39).
- **Teljes funkció opt-in plugin-beállítás** (§14/1, `mtmt_enable_topic_areas`,
  Beállítások oldal "Funkciók" szakasza) — kikapcsolva: a szerkesztő űrlapon
  nincs terület-választó, a listán nincs terület-oszlop/szűrő.
- A moderációs lista (Fázis 3) bővült: "Szakmai terület" oszlop + szűrő-lenyíló
  felül, mindkettő feltételes a toggle szerint (CLAUDE.md §8.1 eredetileg is
  előírta a "kutatócsoport" szűrőt, ez pótolja).

*Kész, ha:* a terület-hozzárendelés menti/tölti magát a szerkesztő űrlapon;
a lista szűrhető/oszloppal mutatja a területet; a funkció kikapcsolva a
moderációs form és a lista is korrektül eltünteti a terület-UI-t. **A "egy
terület aloldalán csak az adott terület tételei jönnek" kritérium csak
Fázis 5 (widget) megépülése után zárható le teljesen** — Fázis 4 önmagában
csak az adatmodellt + admin UI-t adja, a `get_publication_ids_for_area()`
repository-metódus már készen áll a Fázis 5-ös widget számára.

**Éles ellenőrzéshez** (Local site, wp-admin):
1. Beállítások → "Szakmai terület" funkció engedélyezése.
2. MTMT → Területek (új almenü) → hozz létre 1-2 területet, mindegyikhez
   válassz egy meglévő WP-oldalt.
3. Nyiss meg egy publikációt szerkesztésre — jelenjen meg a terület-választó
   checkbox-lista, pipálj be egyet, mentsd — töltsön vissza helyesen.
4. A moderációs listán jelenjen meg a "Szakmai terület" oszlop a hozzárendelt
   címkével, és szűrj a felső "Minden szakmai terület" lenyílóval.
5. Kapcsold ki a funkciót Beállításokban — a szerkesztő űrlapon és a listán is
   tűnjön el a terület-UI (a korábban elmentett hozzárendelés a DB-ben marad,
   csak nem látszik/szerkeszthető, amíg vissza nem kapcsolod).

## ✅ Fázis 5 — Elementor widget (A + B)

**KÉSZ, mergelve (PR #4), élesben validálva. 0.5.0-ban kiadva.**
Eredeti "Fázis 5 — alap" (§9.1) helyett **két widget** (§14/10), a
`docs/widget-design.md`-ben rögzített, megrendelő által megerősített
mezőkészlettel és linkviselkedéssel. A 3 nyitott döntés (szerzőnév-forma,
placeholder-kép módja, év-fülek) a build előtt eldőlt — lásd widget-design.md
és docs/decisions.md #49-57.

- **Közös** (`Mtmt_Card_Renderer` + `Mtmt_Widget_Data`, mindkét widget ezt
  hívja): csak `status='approved'`, kizárólag a saját táblából olvas, AJAX-fragment
  (nem "load more") kereséshez/lapozáshoz/év-váltáshoz. Kártya-mezők: cím,
  szerzők (teljes név, 5 fölött "…, and N more" levágással, `authors_raw`-ból),
  szakmai terület-badge (ha be van kapcsolva), forrás, DOI, kiadványtípus-badge,
  megjelenés éve, SJR-negyed-badge, egyéb azonosítós logó-gombok (jelenleg
  feliratos "pill" badge, valódi WoS/Scopus/SZTAKI logó-fájlok nélkül — lásd
  lent). Teljes kártya kattintható (új fülön nyílik), cél DOI-nál `doi.org/<doi>`,
  DOI hiányában (ha engedélyezve) a gui-link. Indexkép hiányában szerver-oldali
  (GD) placeholder-kép, cím beégetve, becsomagolt Open Sans Bond fonttal
  (magyar ékezetekkel — élőben, ténylegesen legenerált képpel verifikálva);
  GD/font hiányában szépen visszaesik CSS-overlay-re.
- **„A" — összesítő központi widget:** minden jóváhagyott tétel, év-fülekkel
  ("Összes" éves fül + konkrét évek), szakmai terület szerinti szűrő-lenyíló
  (ha a Fázis 4 toggle be van kapcsolva), kereső (cím/szerző/forrás).
- **„B" — terület-aloldal widget:** csak `is_featured=1` tételek, EGY konkrét
  szakmai TERÜLETRE VAGY LEKÉRDEZÉSI PROFILRA szűkítve (widget-beállításban
  mód-választóval) — a "profil" mód akkor is működik, ha a terület-funkció ki
  van kapcsolva. Csak akkor jelenik meg az Elementor widget-listában, ha a
  "kiemelt cikk" toggle be van kapcsolva (§14/11).

**Amit még be kell szerezni/pótolni** (nem blokkolja a kódot, de nyitott):
valódi WoS/Scopus/SZTAKI logó-fájlok az egyéb-azonosítós gombokhoz (jelenleg
feliratos pill-badge helyettesíti, lecserélhető bármikor).

*Kész, ha:* mindkét widget csak approved (ill. "B" esetén approved+featured)
tételt mutat, csak a saját táblát hívja, a linkviselkedés és a mezőkészlet a
widget-design.md szerint működik.

**Éles ellenőrzéshez** (Local site, Elementor szerkesztő + wp-admin):
1. Egy oldalon, Elementorral, húzd be az "MTMT publikációk – összesítő" ("A")
   widgetet — jelenjen meg év-fülekkel, kereső mezővel, kártyákkal.
2. Keress rá egy ismert címre/szerzőre a keresőben — a lista AJAX-szal
   szűküljön (nem teljes oldal-újratöltés), URL nem változik.
3. Válts év-fület — a lista frissüljön, csak az adott év tételei látszanak.
4. Ha a "Szakmai terület" funkció be van kapcsolva: jelenjen meg a terület-szűrő
   lenyíló, és szűrjön helyesen.
5. Kattints egy kártyára (nem a logó-gombra/linkre) — DOI-s tételnél
   `doi.org/...`-ra, DOI nélkülinél (ha a "DOI megjelenítése" widget-beállítás be
   van kapcsolva) az MTMT gui-oldalára navigáljon, új fülön.
6. Egy indexkép nélküli publikációnál nézd meg a kártya-képet — legyen rajta a
   cím, olvashatóan (ez a GD-generált placeholder; ha valamiért nem GD-s utat
   venné, akkor is legyen olvasható cím a CSS-overlay-verzión).
7. Húzz be egy "MTMT publikációk – szakmai terület" ("B") widgetet is — csak
   akkor legyen elérhető a widget-listában, ha Beállításokban a "Kiemelt cikk"
   funkció be van kapcsolva. Állítsd be "Szakmai terület" vagy "Lekérdezési
   profil" módra egy konkrét értékkel — csak az adott kör KIEMELT, jóváhagyott
   tételei jelenjenek meg.
8. Beállítások → Widget — placeholder-kép szekcióban tölts fel egy egyedi
   alapképet, mentsd — az ÚJONNAN generált placeholder-képek ezt használják
   (a korábban generáltak nem frissülnek vissza menet közben, ez így várt).
9. Mindkét widget Tartalom fülén nyisd meg a "Szövegek" szekciót — írj át pl.
   a widget címét vagy a kereső helyőrző szövegét, mentsd — a frontenden a
   megváltoztatott szöveg jelenjen meg (a fejléc/kereső/év-fül azonnal, az
   üres-lista üzenet és a lapozó "előző/következő" felirata AJAX-lapozás/
   -keresés UTÁN is maradjon a testre szabott szöveg, ne ugorjon vissza az
   eredeti magyar szövegre).
10. Mindkét widget Stílus fülén próbálj ki egy szín- és egy tipográfia-controlt
    (pl. "Kiemelő szín", "Kártya-cím" tipográfia) — látszódjon a változás a
    frontenden (kiemelő szín pl. az aktív év-fülön/hover-en, kártya-cím betűtípus/méret).

## ✅ Fázis 6 — GitHub + PUC

**KÉSZ, mergelve (PR #5), 0.6.0-ban kiadva.** PUC v5.7 bevendorolva
(`lib/plugin-update-checker/`, GitHub-ról letöltve, nem git-almodulként —
`docs/decisions.md` #61), inicializálva `mtmt-sync.php`-ban, `is_admin()`
mögé kötve (frontendnek nincs köze a frissítés-ellenőrzéshez). Repo publikus
(`SZKK-SZUCS/MTMT_WP_plugin`), nincs token. `setBranch('main')`, nincs
`enableReleaseAssets()` (nincs build-lépés, a GitHub forrás-zip elég).

*Kész, ha:* egy teszt-oldal (pl. a Local site) a Pluginok oldalon frissülést
lát, amikor egy ÚJABB verziójú GitHub Release kerül ki, mint ami a site-on
fut. A 0.6.0 kiadásakor a PUC-kód maga is 0.6.0-t futtat, tehát ekkor még nem
mutatna frissítést (ez nem hiba, csak a teszt sorrendje) — a tényleges
"látja-e" próba a KÖVETKEZŐ fázis/javítás lezárásakor, a következő
verzióbump+release-nél adódik magától, a rendes munkamenet részeként.

**Éles ellenőrzéshez** (Local site):
1. Aktiváld/frissítsd a plugint a Local site-on erre a verzióra.
2. wp-admin → Pluginok — NE legyen PHP hiba/fatal a plugin-listánál (a PUC
   betöltése ne törje el az oldalt Elementor/egyéb pluginok mellett sem).
3. A KÖVETKEZŐ verzióbump + GitHub Release után (a következő fázis/javítás
   lezárásakor): Pluginok oldalon jelenjen meg az "Új verzió érhető el" sáv,
   "View version X.Y.Z details" linkkel.

## Fázis 7 (opcionális) — nice-to-have-ek

Változatlan (§9.2), csak külön jóváhagyással: Dimensions idézettség-badge,
BibTeX lenyíló, haladó frontend-szűrők (szerző/típus/folyóirat/SJR/Norvég-szint),
Norvég-szint API-útjának lezárása (ha idáig még nem történt meg).

## Backlog — még nincs fázishoz kötve

- **Profil-előnézet ("Preview") létrehozás előtt** (megrendelői kérés, 2026-08) —
  az admin "Új profil" űrlapon (Profilok oldal) legyen egy "Előnézet" gomb,
  ami a beírt scope-ból (intézmény/szerző/haladó + DOI-only) összeépített
  `cond_json`-nal kimegy az MTMT API-hoz `size=5`-tel (NEM menti el a profilt,
  NEM indít syncet — csak olvas), és megmutatja:
  - `paging.totalElements` / `totalEstimatedElements` — ha ez gyanúsan nagy
    (a docs/field-map.md-ben már dokumentált minta: ~5000/több millió =
    valószínűleg NEM szűrt, az MTMT csendben ignorálta az ismeretlen cond-ot),
    erre kifejezetten figyelmeztessen.
  - Pár minta-cím/szerző a találatokból, hogy vizuálisan is ellenőrizhető
    legyen, hogy tényleg a várt publikációk jönnek-e.
  - Cél: ne lehessen véletlenül elgépelt intézmény-mtiddel vagy rosszul szűrő
    cond-dal létrehozni egy profilt, ami aztán szinkronnál felesleges/rossz
    tömeget importál be.
  - Implementáció-vázlat: a `build_conditions()` logika (már megvan
    `Mtmt_Profiles_Page`-ben) újrahasználható; egy új `mtmt_action=preview`
    a meglévő (nem-AJAX, sima form-POST) mintát követve — ugyanaz az oldal
    jelenítse meg az előnézetet a form fölött, a beírt mezőket megőrizve.
    Nem kell hozzá új tábla vagy repository-módszer, csak az `Mtmt_Api_Client`
    egyetlen `get_page()` hívása.
  - Nincs konkrét fázishoz kötve — a Profilok oldalt (Fázis 1) bővítené,
    bármikor felvehető, amikor ráérünk.
