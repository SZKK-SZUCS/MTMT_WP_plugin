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

_Kész, ha:_ egy már `approved` teszt-rekord MTMT-oldali módosítás után
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

_Kész, ha:_ ütemezetten fut, napló látszik adminban, `DISABLE_WP_CRON`-nal
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
- _Előkészítve Fázis 4-hez, de még nem építve_: a "szakmai terület" választó
  helye a formban egyelőre nincs is jelen (nem csak elrejtve) — Fázis 4
  fogja ténylegesen hozzáadni a feltételes blokkot.

_Kész, ha:_ jóváhagyás/elutasítás/visszavonás megy (sor-műveletként ÉS a
szerkesztő űrlapon is), tömeges jóváhagyás/elutasítás a listából, kézi mezők
(thumbnail, `funding_override`, `project_*`, is_featured) syncnél túlélnek,
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

_Kész, ha:_ a terület-hozzárendelés menti/tölti magát a szerkesztő űrlapon;
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

_Kész, ha:_ mindkét widget csak approved (ill. "B" esetén approved+featured)
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

_Kész, ha:_ egy teszt-oldal (pl. a Local site) a Pluginok oldalon frissülést
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

## ✅ Email-értesítő újratervezése

**KÉSZ, mergelve (PR #7), 0.8.0-ban kiadva. A design-előnézetet a megrendelő
jóváhagyta ("fasza nagyon minden") — a tényleges cron-kézbesítés (Easy WP
SMTP-n át) élesben még nincs kipróbálva, ld. a teszt-lista 2. pontját.**
Megrendelői kérés (2026-08) a
Fázis 2-es sima szöveges email javítására: HTML-email, logóval. A logó a
PLUGINBA becsomagolt statikus fájl (`assets/img/mfui-logo.png`, `.jpg` vagy
`.jpeg`), NEM site-onkénti admin-beállítás — a megrendelő megerősítette:
"ez rendszer email, én égetem be központilag a pluginba mint kiadó"
(docs/decisions.md #74-78 az "Email-értesítő újratervezése" szakaszban).
A profil `#ID` helyett a profil neve jelenik meg a törzsben.

**A logó-fájl elhelyezése (kiadói feladat, nem admin-feladat):** tedd a képet
`assets/img/mfui-logo.png` névvel a plugin repóba (JPG is jó: `.jpg`/`.jpeg`
néven, ha az kényelmesebb) — ha egyik fájl sem létezik, az email egyszerűen
logó nélkül megy ki, nincs hiba. Ajánlott: átlátszó hátterű PNG, kb. 400px
széles, ésszerű fájlméret (email-kompatibilitás miatt ne legyen több száz KB).

15 új/frissített teszt-assertion (`test-notifier.php`) — a teljes suite zöld.

_Kész, ha:_ a cron-triggerelt email HTML-ként érkezik meg (nem nyers
`<div>`-ekkel a postaládában), a logó megjelenik (ha a fájl a helyén van),
a profil neve (nem csak száma) olvasható, és a "Jóváhagyás megnyitása" gomb
a Profilok oldalra visz.

**Éles ellenőrzéshez** (Local site, Site Shell + postaláda):

1. Tedd be a logó-fájlt `assets/img/mfui-logo.png` néven (ha még nincs ott).
2. `wp cron event run mtmt_weekly_sync` (ha van friss aktivitás egy profilnál,
   lásd a korábbi cron-teszt-instrukciót) — nézd meg a beérkező emailt: HTML,
   logóval, a profil nevével (nem "#1"), rendezett kártyás megjelenéssel.
3. Ha egy profilnál hiba volt a futás közben, azt egy kiemelt (piros) blokk
   jelezze, ne keveredjen a sikeres profilok közé.

## Fázis 7 (opcionális) — nice-to-have-ek

Megrendelői döntés (2026-08):

- **Haladó frontend-szűrők: ELVETVE.** A jelenlegi keresés + év-fül + terület-szűrő
  elég, nincs igény szerző/típus/folyóirat/SJR szerinti külön szűrőkre.
- **Norvég-szint: elhalasztva, nem kell az 1.0-hoz.** Az API-út felderítése
  (hol/hogyan érhető el, ha egyáltalán publikusan elérhető) nincs lezárva —
  a `norway_level` mező marad `NULL`, amíg ezt nem vesszük elő újra.
- ~~BibTeX lenyíló~~ — **ELVETVE** (2026-08). A megrendelő megkérdezte, mi ez
  (válasz: docs/decisions.md #79), majd döntött: nem kell, aki BibTeX-et akar,
  megoldja az MTMT saját oldalán (`export=1&exportFormat=BIBTEX`, CLAUDE.md §5.3).
- **Dimensions idézettség-badge — KÉSŐBBI OPCIONÁLIS FEATURE** (2026-08,
  megrendelői döntés). Mi lenne: egy DOI-alapú, külső (badge.dimensions.ai)
  beágyazott script-jelvény a widget-kártyán, ami mutatja, hányszor idézték a
  publikációt — a Dimensions.ai adatbázisából, ami a látogató böngészőjében
  élőben frissül (nem csak a heti MTMT-szinkronnal), és tipikusan szélesebb
  forrásból számol, mint az MTMT saját idézettség-száma. Ára: külső script
  fut le a látogató böngészőjében DOI-nkénti bontásban (apró adatvédelmi
  megfontolás, jelzés + lazy-load ajánlott). Részletes magyarázat:
  docs/decisions.md #79. Nincs ütemezve, csak ha a megrendelő külön kéri.

## ✅ Profil-előnézet ("Preview")

**KÉSZ, mergelve (PR #6), élesben validálva ("a preview fasza"). 0.7.0-ban kiadva.** A "Profilok" oldal "Új profil" űrlapja kapott egy "Előnézet"
gombot ("Profil létrehozása" mellett, attól függetlenül) — a beírt scope-ból
(intézmény/szerző/haladó + DOI-only) összeépített `cond_json`-nal kimegy az
MTMT API-hoz `size=5`, `depth=1` paraméterrel (NEM menti el a profilt, NEM
indít syncet — csak olvas), és megmutatja:

- `paging.totalElements` / `totalEstimatedElements` — ha ez gyanúsan nagy
  (küszöb: 2000, heurisztika, docs/decisions.md #71), figyelmeztet, hogy a
  cond valószínűleg nem érvényesült.
- 5 minta-cím + szerzők (`Mtmt_Mapper::map_publication()`-nel leképezve,
  ugyanazzal a kóddal, mint a valódi sync).
- A form-mezők (név, scope-típus, érték, DOI-only) megőrződnek előnézet
  UTÁN is, nem kell újra begépelni — hiba esetén (érvénytelen MTID, rossz
  JSON) is.

17 unit-teszt-assertion (a `render()` valós HTML-kimenetét vizsgálva,
stub `wp_remote_get()`-tel) — összesen 123/123 zöld a teljes suite-ban.

_Kész, ha:_ az Előnézet gomb nem menti el a profilt, mutatja a találatszámot

- mintacímeket, figyelmeztet gyanúsan nagy találatszámnál, és a "Profil
  létrehozása" gomb ezután is működik ugyanazokkal a mezőértékekkel.

**Éles ellenőrzéshez** (Local site, wp-admin → Profilok):

1. Tölts ki egy intézmény-MTID-et (pl. a JKK 19662-t), nyomj "Előnézet"-et —
   jelenjen meg a találatszám (kb. 767 körül) + 5 minta-cím/szerző, a profil
   NE kerüljön be a felső listába.
2. Írj be egy szándékosan rossz/túl tág cond-ot (pl. egy nem létező vagy
   rosszul megadott MTID-et) — jelenjen meg a "gyanúsan nagy találatszám"
   figyelmeztetés.
3. Adj meg egy érvénytelen értéket (pl. betűket az intézmény-MTID mezőbe) —
   hiba-üzenet jelenjen meg, NE menjen ki API-hívás.
4. Előnézet után nyomj "Profil létrehozása"-t (a mezők már ki vannak töltve)
   — jöjjön létre a profil a beírt értékekkel, ugyanúgy mint eddig.

## ✅ Fázis 8 — 0.9.0 előkészítés

**KÉSZ, mergelve, élesben validálva. 0.9.0-ban kiadva.** A megrendelővel
egyeztetett lista arra, mi kerüljön a 0.9-es kiadásba az "alpha" verzió előtt
(docs/decisions.md #83-85, #82, #81, #89-90). A menet közben, élő tesztelés
alatt talált kritikus szinkron-hiba (#89-90) is ebbe a kiadásba került, a
megrendelő megerősítette, hogy a javítás után a szinkron sikeres.

- **i18n pótlás** (CLAUDE.md §0/§2 eddig hiányzó előírása): `languages/`
  mappa + `.pot` sablon + becsomagolt ANGOL fordítás (`mtmt-sync-en_US.po`/`.mo`)
  — a JKK oldalán szükséges angol plugin-verzióhoz. Saját, kis kinyerő-eszköz
  (`bin/i18n/build.php`, `php bin/i18n/build.php`-vel futtatható, nincs
  Node/WP-CLI-függőség), 228 kinyert string, mind lefordítva
  (`bin/i18n/translations-en.php`). Ha angol WP-locale-lal fut a site, az
  admin-felület automatikusan angolul jelenik meg, semmilyen beállítás nélkül.
- **README kiegészítve** a `DISABLE_WP_CRON` + rendszer-cron ajánlással
  (CLAUDE.md §6 eddig hiányzó előírása).
- **Role→capability admin UI** ("Jogosultságok" almenü) — MINDEN létező
  WP-szerepkörre beállítható, melyik kapja meg a `mtmt_moderate`/`mtmt_classify`
  kapabilitást, mentéskor azonnal érvénybe lép. A megrendelő kérése:
  "role capability mindenképp kell".
- **Egyéb azonosítós SVG-ikonok** — a kód kész az inline-olásukra
  (`assets/img/icons/{slug}.svg`), a megrendelő maga szerzi be a fájlokat
  egyszínű SVG-ként, lásd `docs/external-id-icons.md` a pontos listáért/
  fájlnév-konvencióért. Fájlok hiányában szépen visszaesik a meglévő
  feliratos pill-badge-re.
- ~~BibTeX lenyíló~~ — elvetve (lásd Fázis 7 szakasz).
- ~~Dimensions idézettség-badge~~ — később opcionális feature, nincs
  ütemezve (lásd Fázis 7 szakasz, docs/decisions.md #79).
- **Kézi "Teljes szinkron most" gomb** (Beállítások oldal) — ugyanazt futtatja,
  mint a heti cron (minden profil + email, ha volt aktivitás), hogy ne kelljen
  konzolból `wp cron event run mtmt_weekly_sync`-ot futtatni egy email-teszthez
  vagy egy soron kívüli teljes szinkronhoz. Megrendelői kérés, docs/decisions.md #87.
- **"Adatok törlése" gomb a Pluginok listaoldalon** — a plugin
  sorában (Aktiválás/Deaktiválás mellett), az 5 saját táblát üríti
  (`TRUNCATE`), a beállításokat nem érinti. Megrendelői kérés, docs/decisions.md #88.
- **Kritikus javítás: hamis "sikeres" szinkron-jelentés üres tábla mellett**
  — élő tesztelés közben derült ki ("372 új elem", de a tábla üres maradt).
  Az `upsert()` mostantól ellenőrzi a `$wpdb->insert()`/`update()` valódi
  visszatérési értékét, és egy sikertelen írás a hívó felé is hibaként (nem
  "beszúrt új rekordként") jelentkezik, a valódi MySQL-hibaüzenettel együtt.
  Ugyanez a javítás a reset-gombban is (a `TRUNCATE TABLE` `DROP`-jogosultságot
  igényel, nem csak DELETE/INSERT/UPDATE-et — korábban ez is csendben
  meghiúsulhatott). A mögöttes DB-írási hiba TÉNYLEGES oka még nyitott — ez a
  javítás azt teszi lehetővé, hogy a következő éles teszt már a valódi
  hibaüzenetet mutassa. Részletek: docs/decisions.md #89.
- **A #89 valódi kiváltó oka megtalálva és javítva**: a `wp_mtmt_publications`
  tábla fizikailag nem létezett, miközben a `mtmt_db_version` opció már a
  legfrissebb verzióra mutatott, ezért az önjavító séma-ellenőrzés sosem
  próbálta újra létrehozni. `Mtmt_Activator::activate()` mostantól
  explicit ellenőrzi mind az 5 tábla tényleges létét (`SHOW TABLES LIKE`)
  a verzió-opció beállítása előtt; `mtmt_maybe_upgrade_db()` a verzió-
  egyezéstől függetlenül is újrafuttatja a migrációt, ha a fő tábla mégis
  hiányzik. Ez az egyszerű oldalbetöltéssel önmagát helyreállítja, nincs
  szükség manuális deaktiválás/reaktiválásra. Részletek: docs/decisions.md #90.

**Éles ellenőrzés — MEGERŐSÍTVE (2026-08-27, megrendelő):**

1. ✅ PUC — a telepített plugin megkapja/felkínálja az új verziót.
2. ✅ Cron email — sikeresen megérkezik.
3. ✅ Angol fordítás — működik.
4. ✅ Role→capability admin UI — működik.
5. ✅ "Adatok törlése" gomb — működik.
6. ✅ Kézi szinkron a #90-es javítás után — a tábla ténylegesen feltöltődik
   (a korábbi "372 új, üres tábla" tünet elhárult).

**Frissítve (2026-08-27):** az itt korábban listázott két pont lezárva —
az egyéb azonosítós SVG-ikonok élesben betöltve és a widget-kártyákon
ténylegesen megjelennek (lásd lent, "Egyéb azonosítós ikonok" szakasz +
docs/decisions.md #91-92); a központi cron-pinger job egyeztetése és
javítása folyamatban/lezárva, lásd a legalsó, "Kritikus javítás: sosem
beütemezett heti cron" szakaszt.

## ✅ Egyéb azonosítós ikonok — megjelenítési mód + stílus-vezérlők

**KÉSZ, még nincs élesben validálva.** A megrendelő betöltötte az 5 SVG-ikont
(WoS/Scopus/SZTAKI/PubMed/ResearchGate), majd kérte: "legyen beállítható:
Csak ikon; Csak szöveg; Mindkettő, ezen felül ikon szín, szöveg szín, pill
dizájn". A betöltött fájlok átvizsgálásakor egy komoly, a funkciót érdemben
blokkoló hiba derült ki és lett javítva — részletek: docs/decisions.md #91,
a később pótolt ResearchGate-hez #92.

- Fájlnevek kisbetűsre javítva (`WoS.svg` -> `wos.svg` stb. — Windows-on
  némán működött volna, élesben a case-sensitive fájlrendszer miatt nem).
- Színezhetőségi hiba javítva: a betöltött fájlok beágyazott `<style>`/
  class-alapú fill-mintája (Illustrator-export) felülírta volna az öröklött
  `currentColor`-t — a widget ikon-szín beállítása enélkül látszólag
  hatástalan maradt volna.
- Widget Tartalom fül: "Egyéb azonosítók megjelenítése" (Ikon és szöveg /
  Csak ikon / Csak szöveg), mindkét widgeten.
- Widget Stílus fül, új "Egyéb azonosítók" szekció: ikon méret, ikon szín,
  szöveg szín, pill háttérszín, pill szegély szín, pill lekerekítés.
- ResearchGate felvéve az ismert források közé — a raw `source.name` itt
  kivételesen NEM "ResearchGate", hanem "ResearchGate publ." (élő
  screenshotból megerősítve), a badge-en megjelenő felirat "ResearchGate".
- **Utólagos javítás**: a megrendelő jelezte, hogy az ikonok alapból nagyon
  kicsik — a korábbi fix `1em` a badge örökölt, kicsi `font-size`-lánca
  miatt alig látszott. Az ikon-méret is CSS-változóra (`--mtmt-ext-id-icon-size`)
  került, widget Stílus fülön reszponzív SLIDER-rel állítható (px/em,
  alapérték 20px — a megrendelő kérésére 16px-ről emelve) — MINDKÉT
  módban (ikon+szöveg / csak ikon) ugyanazt az egy beállítást követi
  (docs/decisions.md #93-94).
- **Kártya-kinézet felfrissítés + visszafogott animáció** (megrendelői
  visszajelzés: "nagyon összecsúsznak az adatok"): a badge-ek helytelen
  `vertical-align`-je és a szövegblokkok közti túl szoros margók javítva,
  kártya hover-emelés + kép-zoom + finom belépő fade-in animáció, minden
  `prefers-reduced-motion: reduce`-dal kikapcsolható. Részletek:
  docs/decisions.md #94.

12 új assertion (`test-ext-id-icons.php`, 6→18), teljes suite (194
assertion) zöld, lint tiszta.

**Éles ellenőrzéshez** (Local site, Elementor szerkesztő):

1. Egy publikáción, ahol van WoS/Scopus/SZTAKI/PubMed/ResearchGate
   azonosító, nézd meg a kártyát — az ikonok most már ténylegesen
   betöltve jelenjenek meg (nem feliratos pill), a szín kövesse a szöveg
   színét, a ResearchGate-badge felirata "ResearchGate" legyen (nem
   "ResearchGate publ.").
2. Widget Tartalom fülön váltogasd az "Egyéb azonosítók megjelenítése"
   beállítást Csak ikon / Csak szöveg / Mindkettő között — a kártyák
   frissüljenek megfelelően (kereséssel/lapozással AJAX-frissítés után is).
   2b. Widget Stílus fülön, "Egyéb azonosítók" szekcióban próbáld ki az "Ikon
   méret" csúszkát (pl. 24px vagy 30px) — az ikonok láthatóan nagyobbra
   nőjenek, "Csak ikon" módban is ugyanazt a méretet kövessék.
3. Widget Stílus fülön, "Egyéb azonosítók" szekcióban állíts be egy eltérő
   ikon- és szövegszínt, illetve pill háttér/szegély/lekerekítés értéket —
   látszódjon a különbség a badge-eken.
4. Csak ikon módban egy olyan forrásnál, aminek NINCS betöltött ikon-fájlja
   (pl. ha csak a 4 fentit töltötted be, egy ötödik, ismeretlen forrásnál),
   a felirat jelenjen meg helyette, ne maradjon üres/névtelen gomb.
5. Nézd meg egy kártyalistát — a szerzők/meta-sor/badge-ek között legyen
   érdemi levegő, a SJR-/egyéb-azonosító-badge-ek ne tűnjenek "csúszottnak"
   a szöveghez képest.
6. Vidd az egeret egy sorra — finom háttérszín-tónus jelenjen meg, az
   indexkép enyhén nagyítson; kattints egy év-fülre/keress rá valamire —
   az újonnan betöltött sorok finoman "beússzanak" (fade-in). Rendszer
   szinten kapcsold be a "csökkentett mozgás" (reduced motion)
   beállítást (OS/böngésző szinten) — ugyanezek az animációk maradjanak ki.
   (A 0.94-es hover-emelés+árnyék helyett most háttértónus van, lásd lent
   #95 — a lista-elrendezés flat lett, nem dobozolt kártya.)

## ✅ Widget vizuális referencia-igazítás (screenshot alapján)

**KÉSZ, még nincs élesben validálva.** A megrendelő egy élő screenshotot
küldött ("a dizájn hasonlítson jobban erre") egy BME/TUM-stílusú
publikációs listáról. **Megkérdezve megerősítette**: a képen látható
PDF/Kód/Videó gombsor NEM kell (azt korábban explicit kivették a tervből,
CLAUDE.md §14/3) — csak a vizuális stílus. Kiderült, hogy a
`docs/widget-design.md` eredeti terve már leírta ezt a stílust, csak a
0.5.0-ás implementáció eltért tőle — ez a kör visszaigazítja. Részletek:
docs/decisions.md #95.

- Márka-színek frissítve a plugin más részein (email-fejléc) már használt
  teál/navy párra (`--mtmt-accent: #16aebd`, új `--mtmt-heading: #16233f`).
- Opcionális "Alcím" mező az "A" widget fejlécében (üresen nem jelenik meg).
- Alulvonalas (nem kitöltött pill) év-fülek.
- Soronkénti lista dobozolt/árnyékos kártya-rács helyett, elválasztó
  vonalakkal; hover finom háttértónus (nem emelkedés).
- Forrás+év kiemelő-színű közös sor, DOI+SJR közös meta-sor, típus-badge a
  sor jobb szélén (nem a képen), új körkörös nyíl-CTA dekoráció.
- Számozott lapozás ellipszissel (korábban csak Előző/Következő).

11 új assertion (`test-fase5-widgets.php`, 35→46), teljes suite (205
assertion) zöld, lint tiszta, i18n újraépítve (256 string).

**Éles ellenőrzéshez** (Local site, Elementor szerkesztő):

1. Nyisd meg az "A" (összesítő) widgetet — az év-fülek alulvonalasak
   legyenek (nem kitöltött pill), a lista soronkénti (nem dobozolt kártya),
   vékony elválasztó vonalakkal.
2. Írj be egy szöveget a widget Tartalom fülén az "Alcím" mezőbe, mentsd —
   jelenjen meg a cím alatt egy leíró sor; töröld — tűnjön el.
3. Egy publikáció-sornál nézd meg: a forrás+év egy kiemelő-színű (teál)
   sorban van, a DOI+SJR-badge egy másik, halványabb sorban; a
   kiadványtípus-badge a sor JOBB SZÉLÉN van (nem a képen); egy körvonalas
   nyíl-gomb is legyen a sor jobb szélén, függőlegesen középen.
4. Ha a lista több oldalas: alul számozott lapozás legyen ("1 2 3 … N"
   minta nagy oldalszámnál), nem csak Előző/Következő; az aktuális oldal
   kitöltött, kiemelő-színű legyen.
5. Vidd az egeret egy sorra — finom háttérszín-tónus jelenjen meg, a
   nyíl-gomb kitöltődjön a kiemelő-színnel; kattints a nyíl-gomb köré nem
   eső részre — navigáljon (DOI/MTMT-link).

## ✅ Reszponzivitási hiba javítva + teljes körű Stílus-vezérlő lefedettség

**KÉSZ, még nincs élesben validálva.** Élesben talált hiba (Elementor-
szerkesztő screenshot): egy hosszú kiadványtípus-szöveg ("Folyóiratcikk")
belelógott a címbe keskenyebb szélességnél — a típus-badge/nyíl-CTA egy
találgatott fix hely fölé volt abszolút pozicionálva. Megrendelői kérés:
"a widgetek minden utolsó elemét lehessen stílusban módosítani... a
reszponzivitásra nagyon figyelj". Részletek: docs/decisions.md #96.

- **A hiba architekturálisan javítva**, nem csak egy nagyobb becsült
  értékkel: a típus-badge + nyíl-CTA mostantól valódi flex-gyermek
  (`.mtmt-pub-card-side`), a foglalt szélesség mindig a tényleges
  tartalomhoz igazodik — SEMMILYEN badge-szöveg-hossznál vagy
  konténer-szélességnél nem csúszhat rá a címre.
- **Minden eddig kódba vésett vizuális tulajdonság mostantól Elementor
  Stílus-vezérlővel állítható**: cím-szín, felső-felirat/forrás-sor
  tipográfia, kártya-árnyék (alapból kikapcsolva), előnézeti kép
  szélessége/magassága/lekerekítése, badge-ek lekerekítése/belső margója
  (egységesen típus/SJR/terület), nyíl-gomb mérete/lekerekítése (kör vagy
  négyzet), kereső/szűrő mezők lekerekítése, lapozás oldalszám-gombjainak
  lekerekítése + aktuális oldal háttér-/szövegszíne.

Regresszió: `test-fase5-widgets.php` 46→48 assertion, teljes suite (207
assertion) zöld, lint tiszta, i18n újraépítve (273 string).

**Éles ellenőrzéshez** (Local site, Elementor szerkesztő):

1. Szűkítsd le a böngésző/szerkesztő ablakot fokozatosan (asztali
   szélességtől egészen mobil-szélességig) egy olyan publikáción, aminek
   hosszú kiadványtípusa van (pl. "Folyóiratcikk", "Konferenciaközlemény")
   — a badge SOHA ne fedje/lógja bele a címbe, semmilyen szélességnél.
2. 600px alatt: a típus-badge kerüljön a kép alá, a cím fölé (nem a lista
   legvégén); a nyíl-gomb tűnjön el (a sor egésze marad kattintható).
3. Widget Stílus fülön menj végig minden szekción (Színek, Tipográfia,
   Kártya, Előnézeti kép, Badge-ek, Nyíl-gomb, Kereső/szűrő mezők,
   Lapozás, Egyéb azonosítók) — mindegyik kontroll ténylegesen hasson a
   megfelelő elemre a frontenden.
4. Próbáld ki a "Kártya-sor árnyéka" kontrollt (Kártya szekció) — alapból
   nincs árnyék (flat lista), de bekapcsolva jelenjen meg egy tényleges
   box-shadow minden soron.

## ✅ Kritikus javítás: sosem beütemezett heti cron a JKK site-on (0.10.1)

**KÉSZ, hibajavítás (0.x.PATCH), közös hibakereséssel derült ki.** A
megrendelő jelezte, hogy a futás-naplóban SOHA nem volt sikeres automata
("cron" trigger-típusú) futás. Közös lépésenkénti hibakereséssel kizártuk a
hálózatot (a pinger-domain HTTP 200-at ad) és a karbantartás-mód pluginjukat
(kód-szinten: `template_redirect`-re van kötve, ami `wp-cron.php`-nál sosem
fut le) — a `wp-cron.php` manuális, böngészős meglátogatása sem hozott
létre új naplósort. Kiváltó ok és javítás: docs/decisions.md #97.

- **Kiváltó ok**: `Mtmt_Cron::activate()` (a heti esemény beütemezése)
  kizárólag `register_activation_hook()`-on keresztül futott — ez a hook
  CSAK a WP saját "Aktiválás" mechanizmusán megy át. Ha egy site úgy jön
  létre, hogy a plugin már eleve "aktívként" van jelen egy adatbázis-
  pillanatképben/sablon Docker-image-ben, a hook sosem fut le, a heti
  cron örökre beütemezetlen marad — csendben, hiba nélkül.
- **Javítás**: `mtmt_maybe_reschedule_cron()` minden `plugins_loaded`-kor
  ellenőrzi `wp_next_scheduled()`-del, és pótolja, ha hiányzik —
  ugyanaz a minta, mint a séma-önjavításnál (#89-90).

10 új assertion (`test-cron-selfheal.php`), teljes suite (217 assertion)
zöld, lint tiszta.

**Éles ellenőrzéshez** (JKK site):

1. Egy sima wp-admin oldalbetöltés után (bármelyik admin-oldal) nézd meg
   `wp cron event list`-tel (ha van CLI-hozzáférésed), hogy a
   `mtmt_weekly_sync` szerepel-e a listán, VAGY egyszerűen látogasd meg
   megint a `https://jkk.sze.hu/wp-cron.php?doing_wp_cron` linket, és
   nézd meg, most már megjelenik-e egy új "cron" sor a futás-naplóban.
2. Várd meg a legközelebbi automata (pinger-triggerelt) futást, és
   ellenőrizd, hogy tényleg megjelenik-e "cron" trigger-típusú sor —
   ha igen, ÉS a pinger-konténer hálózati elérése is rendben van, a heti
   automatizmus végre ténylegesen működik.

**Frissítve**: a megrendelővel közös élő hibakereséssel mindkettő
megerősítve — a pinger-konténer HTTP 200-at kap mindkét domainre, a
wp-cron.php feldolgozás is működik. A heti esemény ezután a normál
"heti" ütemezés szerint fut tovább (a következő tényleges automata
lefutásig ~1 hét telik el, mivel egy manuális teszt-lefuttatás "elhasználja"
az adott heti előfordulást).

## ✅ Konfigurálható cron-ütemezés (nap + óra)

**KÉSZ, még nincs élesben validálva.** Megrendelői kérdés: "lehet-e
időzíteni a cron futását pl hétfőnként hajnalra". Korábban a heti esemény
mindig "most" (aktiváláskor/önjavításkor) induló időponttól számítva
futott, tehát véletlenszerű napra/órára eshetett. Részletek: docs/decisions.md #98.

- Új "Heti automatikus szinkron ütemezése" szekció a Beállítások oldalon
  — nap (hétfő-vasárnap) + óra (00-23) választó, alapértelmezés hétfő
  03:00. Mutatja a ténylegesen beütemezett következő futás időpontját is.
- A háttérben a korábbi fix-intervallumos WP "weekly" ismétlődés helyett
  önmagát újraütemező egyszeri eseményekre váltottunk, hogy a nyári/téli
  időszámítás-váltás ne csúsztassa el a beállított órát.
- A nap/óra módosítása csak akkor ütemez újra ténylegesen, ha az érték
  valóban változott — egy sima Beállítások-mentés más mezőkkel nem tolja
  ki feleslegesen a legközelebbi futást.

16 új assertion (`test-cron-selfheal.php`, 10→26), teljes suite (233
assertion) zöld, lint tiszta, i18n újraépítve (289 string).

**Éles ellenőrzéshez** (JKK site, wp-admin):

1. MTMT → Beállítások → "Heti automatikus szinkron ütemezése" — állíts be
   egy nap/óra kombinációt, mentsd — jelenjen meg a "Következő automatikus
   futás" dátum/időpont, ami valóban a beállított napra/órára esik.
2. Változtasd meg csak egy másik mezőt (pl. a címzett-listát) egy MÁSIK
   szekcióban — a cron-ütemezés időpontja NE változzon.
3. Állítsd a napot/órát a közeljövőre (pl. 10 perccel későbbre), várd
   meg — fusson le a szinkron pontosan akkor, egy "cron" sorral a
   futás-naplóban.

## ✅ Teljes szöveg- és kommentár-tisztogatás

**KÉSZ, még nincs élesben validálva.** Megrendelői kérés a demonstrálható
állapot előtt: minden admin-oldal szövege legyen egyszerű, köznyelvi (a
kezelők nem IT-szakemberek), a kód-kommentek pedig ne legyenek
túlmagyarázottak — csak a README maradhat szakmai. Részletek:
docs/decisions.md #99.

- Végigmentünk minden admin-oldalon (Beállítások, Profilok, Jogosultságok,
  Területek, moderációs lista+szerkesztő űrlap) és az Elementor
  widget-kontrollokon — rövidebb, zsargon-mentes szövegek.
- **Közben talált valódi hiba javítva**: az email "Jóváhagyás megnyitása"
  gombja a Profilok oldalra mutatott, nem a moderációs listára — most
  helyesen a jóváhagyásra váró listára visz.
- A kód hosszú, narratív kommentjei lerövidítve — a részletes indoklás a
  docs/decisions.md-ben marad, nem duplikálódik a forráskódban.

Teljes suite (234 assertion) zöld, lint tiszta, i18n újraépítve.

**Éles ellenőrzéshez**: menj végig minden admin-oldalon, nézd meg, hogy a
szövegek érthetőek-e egy nem-technikai kollégának is; próbáld ki az email
"Jóváhagyás megnyitása" gombját — a moderációs listára kell vinnie,
"Függőben" szűrővel.

## ✅ Widget-rendezés + admin-lista nézet-állapot

**KÉSZ, még nincs élesben validálva.** Megrendelői kérés három pontban
(docs/decisions.md #100):

- **Widget-rendezés** — mindkét Elementor widget Tartalom fülén "Rendezés"
  választó (Legújabb elöl / Legrégebbi elöl / Cím A–Z / SJR-negyed szerint),
  alapból "Legújabb elöl". Elementor-beállítás, nem látogatói legördülő
  (megrendelői döntés). A beállított sorrend keresés / év-váltás / lapozás
  után is érvényben marad (a JS minden AJAX-kérésnél visszaküldi).
- **Admin-lista lapozása az URL-ben** — a 10. oldalon megnyitott rekord
  szerkesztéséből kilépve újra a 10. oldalon (és ugyanazzal a szűréssel/
  rendezéssel) landolunk. A szűrő/rendezés/oldalszám átöröklődik a
  szerkesztő-linkbe, az űrlap rejtett mezőibe, a moderációs gombokba és a
  mentés/tömeges művelet utáni redirectbe.
- **Admin-lista időrendi + rendezhető** — alapból `published_year DESC`
  (változatlan), a "SJR" oszlop mostantól szintén rendezhető, a "Szűrés"
  gomb megtartja az aktív rendezést.
- **Determinisztikus lapozás** (mellékesen javított, korábbi hiba): a
  `get_list()` ORDER BY-a mostantól minden ágon `id`-tie-breakerrel zárul —
  azonos évű tételek nem cserélgetik a helyüket oldalak közt.
- **0.13.1: üres év-fülek elrejtése** — keresés vagy (frontend) terület-szűrés
  után csak azok az év-fülek látszanak, amikre van találat; ha a kiválasztott
  évre már nincs, a widget "Összes"-re vált. (docs/decisions.md #100 utókövetés.)
- **0.13.2: a terület-szűrő lenyíló is a kereséshez igazodik** — keresés után
  csak azok a szakmai területek maradnak az "A" widget lenyílójában, amikhez
  van a keresésnek megfelelő tétel; ha a kiválasztott kiesett, "minden
  terület"-re vált. (docs/decisions.md #100 utókövetés.)

Nincs DB-változás, nincs új plugin-beállítás. i18n +5 string (290).

**Éles ellenőrzéshez** (Local site + JKK site):

1. Elementor szerkesztő → "A" widget Tartalom fül → "Rendezés" → állítsd
   "Legrégebbi elöl"-re, mentsd — a frontenden az "Összes" év-fülön a
   legrégebbi publikációk legyenek elöl; válts "SJR-negyed szerint"-re —
   a D1/Q1-es tételek kerüljenek előre, a besorolás nélküliek a végére.
2. Keress rá valamire / válts év-fület / lapozz — a beállított sorrend
   maradjon érvényben (ne ugorjon vissza "legújabb elöl"-re).
2b. Keress rá egy olyan szóra, ami csak 1-2 évben ad találatot — az év-fülek
   szűküljenek le azokra az évekre; töröld a keresést — jöjjön vissza az
   összes év. Ugyanez a frontend terület-szűrővel ("A" widget).
2c. Keress rá valamire, majd nyisd le a szakmai terület szűrőt — csak azok a
   területek legyenek benne, amikhez van a keresésnek megfelelő tétel; töröld
   a keresést — jöjjön vissza az összes terület. Ha előbb területet választasz,
   utána olyanra keresel, aminél annak a területnek nincs találata — a szűrő
   álljon vissza "minden terület"-re és a lista mutassa az összes találatot.
3. wp-admin → MTMT lista → menj a 3. (vagy tovább) oldalra, nyiss meg egy
   rekordot "Szerkesztés/Gazdagítás"-sal, majd "Vissza a listához" — ugyanazon
   az oldalszámon legyél.
4. Ugyanez szűrővel: szűrj pl. "Függőben"-re + egy évre, lapozz a 2. oldalra,
   nyiss meg egy rekordot, hagyd jóvá a szerkesztő nézetből — a lista a 2.
   oldalon, a szűréssel együtt jöjjön vissza.
5. Jelölj ki pár sort a 2. oldalon, futtass egy tömeges jóváhagyást — a
   lista a 2. oldalon maradjon.
6. Kattints az "SJR" oszlopfejlécre — rendezzen SJR szerint; a "Szűrés"
   gomb megnyomása tartsa meg ezt a rendezést.

## Backlog — még nincs fázishoz kötve

Jelenleg üres.
