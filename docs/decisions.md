## Fázis 0 — lezárt döntések (élő curl-teszttel igazolva, 2026-08)

Ezek VÉGLEGES specifikációk a mappernek, nem feltételezések.

1. SZE intézményi szűkítés — HELYES mező: `directInstitutes`.
   - `cond=directInstitutes;in;257` → totalElements=2943 (SZE saját állomány). EZT használd.
   - `cond=institutes;in;257` → ~32–46k (teljes kapcsolatháló) — NE.
   - Ellenőrzés: a `directInstitutesForSort` mező tartalmazza az SZE-t.
   - A profil alap-scope-ja `directInstitutes;in;257`, ezen belül szűkíts
     szerző-mtid / al-intézmény szerint + kézi szelekció.

2. Sync depth — KÖTELEZŐEN `depth=1` a publication endpointon.
   - depth=0-nál HIÁNYZIK: authorships[], ratings[] (SJR!), directInstitutesForSort
     → különben authors_text és sjr_quartile üresen maradna.
   - A szerver depth=2-nél levág: depth=2 és depth=3 válasza bájtra azonos.

3. Támogatás/projektazonosító — a publikus API NEM adja (depth 0–3, ~170 kulcs
   végignézve: nincs grant/funding/project/otka/nkfih gyökű mező).
   → `funding_text` a syncnél ÜRESEN marad; csak `funding_override` (kézi) tölti.

4. `any` operátor ékezet-intoleráns: `label;any;Győr` → 0 találat, `Gyor` → helyes.
   → Author-autocomplete: ékezettelenítsd a query-tokent (vagy próbáld mindkettőt);
   a MEGJELENÍTÉSHEZ a valós, ékezetes label-t használd.

5. Norvég szint — továbbra is nyitott, Fázis 2, nem blokkolja a mappert.

## Fázis 1 — tervezési döntések (2026-08)

6. Pontosított intézmény-scope: `mtid=19662` = „Járműipari Kutatóközpont SZE JKK"
   (al-intézmény a 257=SZE alatt). `directInstitutes;in;19662&core;eq;true` →
   totalElements=767, PONTOSAN egyezik az intézmény saját `publicationCount`
   mezőjével — ez a tényleges scope, NEM a teljes SZE (257). Lásd field-map.md.

7. Architektúra-elv: SEM intézmény-, SEM szerző-mtid nincs PHP-kódba hardcode-olva
   sehol (api-client, mapper, sync osztályokban sem). A scope kizárólag a
   `wp_mtmt_query_profiles.cond_json`-ban él, telepítésenként konfigurálva.
   Ez teszi a plugint "dobozosan" (több site-on, kódmódosítás nélkül) újrahasználhatóvá.

8. `core;eq;true` és `published;eq;true` mindig kemény-kódolt, profil-független
   feltétel a Sync-ben (idéző-rekord kizárás, korrektségi garancia — nem
   site-specifikus preferencia, ezért nem cond_json-ban van).

9. `depth=1` mindig kemény-kódolt a Sync-ben (nem profil-paraméter) —
   depth=0 adatvesztést okozna (§Fázis 0 döntés #2).

10. `missing_since`-diff profilonként skálázott: egy futás csak a saját
    `query_profile_id`-jú sorokat nézi. `query_profile_id` csak INSERT-kor
    íródik, UPDATE-kor stabil marad (első profil "birtokolja" a rekordot).

11. Hiba lapozás közben → a diff/missing-fázis kimarad annál a futásnál
    (már felszívott rekordok megmaradnak; hiány-jelölés csak teljes,
    hibátlan lekérés után fut le) — megszakadt szinkron nem jelöl mindent hiányzónak.

12. "Dobozos" query-konfiguráció: Fázis 1-ben admin settings oldal (nem csak
    WP-CLI) a query profilokhoz, `admin/class-mtmt-profiles-page.php`,
    `manage_options` kapabilitáshoz kötve (külön a moderációs
    mtmt_moderate/classify capability-któl, mert ez site-config, nem
    napi moderáció). UX: mód-választó (Intézmény MTID / Szerző MTID-lista /
    Haladó nyers cond JSON) + egy érték-mező, a plugin építi belőle a
    cond_json-t. Ugyanazt a Mtmt_Query_Profile_Repository-t használja,
    mint a `wp mtmt profile create` CLI parancs — nincs duplikált logika.

13. Fázis 1 alapkód elkészült (activator, api-client, mapper, két repository,
    sync, WP-CLI, admin profil-oldal, bootstrap). `php -l` mind tiszta.
    A mapper külön, WP-független harnessben lefuttatva a valós
    mtmt_pub_depth2.json mintán (37139647) — minden mező helyesen jött
    (authors_text sorrend+"and", SJR Best-Q=D1 a több rating közül,
    page_range fallback "Paper: 3975", DOI/Egyéb URL/external_ids szétválasztás).
    Végigmenő WP+MySQL integrációs teszt (`wp mtmt sync` élesben, JKK
    profillal, mtid=19662) MÉG NEM történt meg — nincs helyi WP-telepítés,
    csak XAMPP PHP/MySQL. Ha kell, felállítható egy helyi WP-instance a
    teljes Fázis 1 elfogadási kritérium (nem duplikál újrafuttatva, lapozás)
    éles ellenőrzéséhez.

14. Fázis 1 éles integrációs teszt SIKERES: Local WP-oldal (mtmt-wp-plugin),
    junction-nel bekötve, admin UI-n létrehozott JKK profil (mtid=19662),
    `wp mtmt sync` -> 767 új / 0 frissített / 0 hiányzó, PONTOSAN a JKK
    intézmény saját publicationCount-jával egyezik. A Fázis 1 elfogadási
    kritériuma (kész, ha egy profilra feltölti a táblát, lapozás működik)
    élesben igazolva.

15. Fázis 5 widget vizuális referencia befogadva a megrendelőtől (kép),
    lásd `docs/widget-design.md`. Év-fülek (nem accordion), soronkénti
    kártya-lista, típus-badge, lapozás. Nyílt kérdések: kell-e Kód/Videó
    kézi link-mező, szerzőnév rövid vs. teljes forma. NEM Fázis 1 munka,
    csak dokumentálva a phase-ek közötti elvesztés ellen.

## Megbeszélés — 2026-08 (Fázis 1 után), lásd CLAUDE.md §14

A Fázis 1 éles tesztje (767/0/0) utáni megbeszélésen 12 pont rögzült
(`docs/megbeszélés infók.txt` a nyers jegyzet). Amit ÉLESBEN leellenőriztem
dokumentálás közben, nem csak leírtam:

16. DOI-only query-opció (§14/2) mezője **VERIFIKÁLVA**: `cond=identifiers.source.name;eq;DOI`.
    - JKK-n (directInstitutes;in;19662&core;eq;true): teljes=767, DOI-val=372, DOI nélkül=395.
      372+395=767 — pontos komplementer felosztás, tehát a szűrés valódi (nem csendben ignorált).
    - Alternatíva ami szintén működik: `cond=identifiers.source;eq;6` (a DOI forrás numerikus
      mtid-je), de a `.name;eq;DOI` olvashatóbb/karbantarthatóbb — ezt vegyük fel a cond_json-builderbe.
    - **Miért fontos üzletileg**: ~48%-os csökkenés a bekerülő rekordszámban — ezt a
      megrendelőnek látnia kell döntés előtt, nem csak a fejlesztőnek.

17. MTMT humán (gui) link mintája (§14/12, a widget-kártya kattintható célja DOI hiányában)
    **VERIFIKÁLVA** egy már korábban lementett nyers publication-objektum `template` mezőjéből:
    `https://m2.mtmt.hu/gui2/?mode=browse&params=publication;<mtid>`.
    Ez ELTÉR az eddig egyedül dokumentált API-linktől (`/api/publication/<mtid>`, ami JSON — nem
    linkelhető emberi oldal). §5.7 eddig csak az API-linket írta le explicit mintaként; a gui-link
    mostantól ez, ne az API-endpoint kerüljön a widget `<a href>`-jébe.

18. "Szakmai terület" (§14/1) = a §7 kutatócsoport-taxonómia újranevezése — **MEGERŐSÍTVE
    a megrendelőtől** (2026-08), NEM külön, azzal párhuzamos második koncepció. Egy
    kategória-rendszer marad (terület↔aloldal pár, dropdown-os többes hozzárendelés,
    widget "csoportra szűkítés"), csak "Szakmai terület" néven, plusz a §14/1-ben írt
    opt-in kapcsolóval. Nincs dupla admin-mező/tábla/widget-szűrő Fázis 4-ben.

19. §4.3 sync-szabály MÓDOSÍTVA (§14/7): tartalomváltozás → `approved`/`rejected` vissza
    `pending`-re, nincs csendes auto-apply. Nyitott implementációs kérdés: a "valóban
    változott-e" összehasonlítás a mapper-kimenet MTMT-forrású oszlopait kell nézze, NEM a
    `raw_json`-t (abban lévő `lastRefresh`/`lastModified` admin-időbélyegek MTMT-oldalon
    gyakran változnak tartalmi változás nélkül is — ha ezt figyelnénk, minden sync feleslegesen
    pending-be dobná az approved tételeket).

20. Kézi "Szinkron most" gomb (§14/6): élesben mért referencia — 767 rekord ~24s volt
    szinkron HTTP-requestben. Nagyobb intézménynél timeout-kockázat, Fázis 2-ben kell
    érdemben kezelni (nem Fázis 1/most).

21. Placeholder-kép + rágenerált cím (§14/8): nyitva hagyva CSS-overlay vs. szerver-oldali
    (GD/Imagick) képgenerálás között — ajánlás CSS-overlay felé (egyszerűbb, nincs új
    szerver-függőség), de a megrendelővel nem volt még erről szó, ne döntsük el egyedül
    kódolás előtt.

22. Widget-kártya mezőkészlet MEGERŐSÍTVE a megrendelőtől (2026-08): Cím, Szerzők,
    Szakmai terület, Forrás (folyóirat/kötet), DOI, Kiadványtípus, Megjelenés éve,
    SJR-negyed, egyéb azonosítós logó-gombok — CSAK ezek jelennek meg a widgeten.
    FONTOS: ez a widget-DISPLAY scope-ja, NEM a tárolt séma szűkítése — a `volume`,
    `issue`, `page_range`, `issn`, `norway_level`, `mtmt_state`, `other_url` stb.
    továbbra is tárolva marad (moderáció, export, jövőbeli widget-verzió miatt),
    csak nincs a mostani kártyán megjelenítve. Lásd docs/widget-design.md frissítve.

23. "Szakmai terület" = kutatócsoport-taxonómia átnevezése, MEGERŐSÍTVE a
    megrendelőtől (2026-08) — nincs dupla kategória-rendszer, ld. #18 frissítve.

## Munkafolyamat + verziózás — 2026-08

24. **Fejlesztési folyamat mostantól**: (1) ha kérdés van, megkérdezem; (2) megírom
    a módosítást; (3) elmondom, mit kell tesztelni és mit csináltam; (4) a
    megrendelő teszteli élesben (Local site, symlink); (5) ha jó, ő mondja, hogy
    "mehet a pull request" — CSAK akkor nyitok PR-t, nem automatikusan minden
    kész munka után; (6) a megrendelő maga mergeli, és szól, ha megtörtént.
    **Miért**: explicit review-gate minden érdemi kódváltozás előtt éles
    elfogadásba kerülés előtt — hogy ne fusson be nem tesztelt kód a fő ágba.
    **Hogyan alkalmazd**: ne push-oljak/nyissak PR-t megkérdezés nélkül; a
    jelen (0.2.0-ig tartó) állapotot még közvetlenül main-re commitoltuk, DE
    ez volt az utolsó ilyen — mostantól minden új munka ág+PR.

25. **Release-kiadás**: `gh` CLI-vel tudok GitHub Release-t létrehozni
    (`git tag` + `gh release create`), de EZT IS csak akkor teszem, ha a
    megrendelő kifejezetten kéri merge után — nem automatikus. A tag a bare
    verziószám legyen (pl. `0.2.0`, NEM `v0.2.0`), hogy pontosan egyezzen a
    plugin-header `Version` mezőjével, amit a PUC összevet.

26. **Verziószabály**: a plugin `0.x.y`-on marad, amíg a megrendelő explicit ki
    nem mondja, hogy a rendszer kész — `1.0.0`-t soha nem lépünk át magunktól.
    Rögzítve a CLAUDE.md §10.2-ben is, hogy jövőbeli session se felejtse el.
    A 0.2.0 az első érdemi verzió (Fázis 0+1+1.5 kész, élesben validálva).

## Fázis 2 — cron, napló, email, kézi gomb (2026-08)

27. **HIBA JAVÍTVA, saját teszt közben találtam meg, mielőtt élesedett volna**:
    a `Mtmt_Sync::run_profile()` eddig MINDEN meglévő (nem új) rekordot
    "frissítettként" számolt, függetlenül attól, hogy a tartalma tényleg
    változott-e (`else { ++$result['updated']; }` ág, `content_changed`
    figyelmen kívül hagyva). Ez azt jelentette volna, hogy egy stabil,
    767 rekordos profil MINDEN heti syncnál 767 "frissítést" jelentett volna
    — és mivel a §14/5 email-értesítés az "volt-e frissült tétel" jelre
    kapcsolódik, ez MINDEN héten kiküldte volna az emailt, akkor is, ha
    semmi nem történt. Javítva: `updated` csak akkor nő, ha
    `$upsert['content_changed']` igaz. Regressziós teszt: két egymást követő
    upsert ugyanazzal a mapped-row-val -> `updated` marad 0 a másodikon.

28. Új osztályok a futtatás egységesítésére, hogy cron/kézi gomb/CLI mind
    ugyanazon a logikán menjen át (naplózás + feltételes email):
    `Mtmt_Sync_Runner::run($trigger_type, $profile_id=null)` —
    ezt hívja mindhárom belépési pont, NEM közvetlenül a `Mtmt_Sync`-et.
    `$trigger_type`: 'cron'|'manual'|'cli' — csak 'cron'-nál megy email.

29. Email-cimzettek: `wp_options` (`mtmt_notification_recipients`), NEM
    külön tábla — egyetlen site-szintű, sorononkénti/vesszős lista, admin
    "Beállítások" oldalon szerkeszthető (`admin/class-mtmt-settings-page.php`).

30. Futás-napló: ÚJ tábla `wp_mtmt_sync_log` (profilonkénti sor minden
    futásból, trigger_type-tal), `MTMT_DB_VERSION` 1->2 emelve. Hozzá
    ÚJ auto-upgrade check `plugins_loaded`-en (`mtmt_maybe_upgrade_db()`):
    ha a tárolt db_version eltér, újrafuttatja a dbDelta-t reaktiválás
    nélkül is — enélkül egy sima fájl-frissítés (deaktiválás nélkül) nem
    hozta volna létre az új táblát.

31. `is_featured` oszlop pótolva a TÉNYLEGES `Mtmt_Activator` SQL-jében —
    korábban csak a CLAUDE.md §4.1 dokumentációba került be, az éles
    migrációs kódba nem (dokumentáció-kód drift, most zárva).

32. Kézi "Szinkron most" gomb: `set_time_limit(0)` a PHP-oldali korláthoz,
    DE ez nem old meg webszerver/proxy-szintű timeoutot — ha ez élesben
    nagyobb intézménynél gondot okoz, async/AJAX-progress kell (lásd
    docs/roadmap.md, §14/6 eredeti figyelmeztetése).

## Plugin átnevezés: JKK MTMT Publications -> MTMT Sync (2026-08)

33. **Teljes rebrand, mert a plugint több szervezet is használni fogja**
    (megrendelő explicit kérése) — a "JKK" korábban nemcsak adatban (már
    korábban is kerülve, ld. #7 "dobozos" elv), hanem a KÓD SAJÁT
    névterében is jelen volt (osztálynevek, függvények, táblanevek,
    szövegdomain, WP-CLI namespace, admin menü), ami más szervezet
    telepítésén zavaró/szakszerűtlen lett volna.
    - Admin menü (top-level label): **"MTMT"** (rövid).
    - Egyéb felhasználói szöveg (oldalcímek, readme leírás): **"MTMT
      Publikációk"**.
    - Plugin header/mappa/fő fájl neve: **"MTMT Sync"** / `mtmt-sync`.
    - Kódszintű prefix (osztályok/függvények/táblák/opciók/cron-hook/
      kapabilitások/WP-CLI namespace): rövidebb `Mtmt_`/`mtmt_` — NEM
      `mtmt_sync_`, mert az feleslegesen ismételné a "sync"-et minden
      azonosítóban (pl. `wp mtmt sync` olvashatóbb, mint `wp mtmt-sync sync`).
    - Régi -> új: `Jkk_Mtmt_*` -> `Mtmt_*` (osztályok), `jkk_mtmt_*` ->
      `mtmt_*` (függvények/táblák/opciók: pl. `wp_jkk_mtmt_publications` ->
      `wp_mtmt_publications`), `JKK_MTMT_*` -> `MTMT_*` (konstansok),
      `jkk-mtmt-publications` (text domain) -> `mtmt-sync`, `jkk-mtmt sync`/
      `jkk-mtmt profile` (WP-CLI) -> `mtmt sync`/`mtmt profile`,
      `jkk-mtmt-profiles`/`jkk-mtmt-settings` (admin slug) ->
      `mtmt-profiles`/`mtmt-settings`, `jkk_research_group` (Fázis 4-es
      taxonómia, még nem épült meg) -> `mtmt_research_group`.
    - `jkk-mtmt-publications.php` -> `mtmt-sync.php`, minden `includes/`
      és `admin/` fájl `class-jkk-mtmt-*` -> `class-mtmt-*`.
    - Admin UI-szövegekből ("wp mtmt profile create --help" is idetartozik,
      mert az minden telepítőnek megjelenik) kikerültek a konkrét "JKK"
      példák, generikus "Kutatóintézet" placeholderre cserélve.
    - **Amit SZÁNDÉKOSAN NEM cseréltem**: a CLAUDE.md és a docs/ fájlok
      PRÓZÁJÁBAN maradó "JKK" említések (pl. "A JKK publikációs listát
      akar...", changelog "élesben validálva JKK profillal") — ezek a
      MEGRENDELŐ tényleges azonosítása/a valós tesztelés dokumentálása,
      nem a plugin technikai brandingje, ezekben a kontextusokban helyénvaló
      a valódi név.
    - **KÖVETKEZMÉNY a Local teszt-site-on**: a régi `wp_jkk_mtmt_*` táblák
      érintetlenül megmaradnak az adatbázisban (nem törölve), de a kód
      mostantól `wp_mtmt_*`-re megy — a 767 teszt-rekordot újra be kell
      húzni szinkronnal. Ez nem adatvesztés (semmi nem volt még kézzel
      gazdagítva/jóváhagyva), de a WP plugin-mappát is át kellett linkelni
      (`wp-content/plugins/jkk-mtmt-publications` -> `.../mtmt-sync`),
      ami éles oldalon egy deaktiválás+újraaktiválást igényelne.

## Fázis 3 — moderáció, gazdagítás, jogosultságok (2026-08)

34. Capability→role mapping **döntés a megrendelőtől**: `mtmt_moderate` ÉS
    `mtmt_classify` is alapból az Editor + Administrator szerepkörre kerül
    aktiváláskor (`Mtmt_Capabilities::activate()`). Nincs még finomabb
    role→capability admin UI — ha kell (pl. csak bizonyos személyek kapják a
    classify-t), az külön kör.

35. **Auto-upgrade minta kiterjesztve a capability-kre is** — ugyanaz a hiba
    fenyegetett, mint a #30-as DB-tábla esetén: ha valaki a plugint fájlszinten
    frissíti (nincs deaktiválás/reaktiválás), egy ÚJ capability sosem kerülne
    fel a szerepkörökre, mert `register_activation_hook` csak aktiváláskor fut.
    Megoldás: `mtmt_caps_version` opció + `plugins_loaded`-es ellenőrzés,
    ugyanaz a minta, mint `mtmt_db_version`-nél.

36. **Admin menü átrendezve**: a top-level "MTMT" mostantól a moderációs lista
    (`Mtmt_Publications_Page`, `mtmt_moderate` capability), NEM a Profilok
    oldal. Ok: a moderátorok (Editor-ok) ezt használják nap mint nap, a
    Profilok/Beállítások site-config, ritkán nyúlnak hozzá. A top-level menü
    capabilityjét a LEGALACSONYABB alatta lévő submenü-capabilityre kell
    állítani, hogy a moderate-only user egyáltalán lássa a menüt — a
    Profilok/Beállítások almenü `manage_options`-maradt, ezért azokat a
    moderate-only user nem is látja, csak a listát.

37. **Kritikus timing-szabály minden mutáló admin-műveletnél**: a
    jóváhagyás/elutasítás/tömeges-művelet/mentés `admin_init`-en fut
    (`Mtmt_Publications_Page::maybe_handle_request()`), NEM a page-callbackban
    (`render()`). Ok: mire a `add_menu_page()`-hez regisztrált callback lefut,
    a WP admin fejlécek (benne a HTML `<head>`) már elmentek — egy
    `wp_safe_redirect()` ott már "headers already sent" hibát adna. Az
    `admin_init` a fejlécek előtt fut, ott még működik a Post/Redirect/Get minta.

38. **Lista-form `method="get"`, NEM `post`**, annak ellenére, hogy WP core
    listák (pl. edit.php) POST-ot használnak. Ok: a WP_List_Table saját
    lapozó-linkjei (`pagination()`) a JELENLEGI URL query-argjaiból épülnek —
    ha a szűrők (státusz/év/profil) csak POST-ban mennének át, a 2. oldalra
    lapozáskor elveszne a szűrés. GET-tel a szűrők a URL-ben maradnak, a
    lapozás megőrzi őket. A tömeges műveletek (jóváhagyás/elutasítás) emiatt
    technikailag GET-kérésként mennek — ez ELFOGADHATÓ, mert a mutációt a
    nonce (`check_admin_referer`) védi CSRF ellen, nem a HTTP-ige; a
    single-row/bulk-action megkülönböztetés `$_REQUEST['id']` array-e vagy
    scalar (nem a metóduson múlik).

39. **`project_verified` pipa `mtmt_classify`-hoz kötve**, NEM `mtmt_moderate`-hoz
    (CLAUDE.md §8.3 szó szerint: "projektazonosító-ellenőrzés" = classify).
    A `project_ids` SZÖVEG bármelyik moderate-jogú usernek menthető, csak az
    "Ellenőrizve" pipa van elzárva — szerveroldalon védve (a mezőt egyszerűen
    nem veszi át a repository, ha a beküldő nem classify-jogú), NEM csak a
    form UI-ban elrejtve.

## Fázis 3 PR előtti kiegészítés — mezőmagyarázatok + bulk kiemelés (2026-08)

40. **Minden szerkeszthető mezőnél leíró szöveg** (megrendelői kérés) — a
    gazdagító űrlap (indexkép, támogatás felülbírálás, projektazonosító+
    ellenőrizve, kiemelt cikk) és a profil-létrehozó űrlap (profil neve,
    scope típusa) mind kaptak egy `<p class="description">` magyarázatot,
    plain-language stílusban (nem technikai zsargon — pl. nem "Fázis 5-ös
    widget", hanem "külön widgettel... csak ezeket lehet majd kiemelten
    megjeleníteni").

41. **Bulk kiemelés/kiemelés-visszavonás** a moderációs listán — a meglévő
    tömeges jóváhagyás/elutasítás mintájára. `Mtmt_Publication_Repository::
bulk_set_featured()`, a `Mtmt_List_Table` bulk-action listája és a
    státusz-oszlop csak akkor mutatja/ajánlja fel, ha a "kiemelt cikk"
    funkció be van kapcsolva (`mtmt_enable_featured`) — ugyanaz a
    feltétel-mintázat, mint az egyes-tételes checkboxnál. A státusz oszlopban
    egy ★ jelzi a már kiemelt tételeket, hogy lásd, mit pipálsz be
    kiemelés-visszavonáshoz (enélkül a bulk unfeature-nek nem sok értelme
    lenne — nem kérted külön, de e nélkül a funkció gyakorlatilag
    használhatatlan lett volna).

## Fázis 4 — szakmai terület (2026-08)

42. **NEM WP-taxonómia**, ahogy a CLAUDE.md §7 már eredetileg is javasolta —
    a publikáció nem post-típus, `register_taxonomy()`/`wp_set_object_terms()`
    proxy-objektummal ráerőltetve csak felesleges komplexitás lenne. Sima
    saját tábla (`wp_mtmt_topic_areas`) + pivot tábla (`wp_mtmt_pub_topic_area`).

43. **Névváltoztatás a CLAUDE.md §7 pivot-tábla-vázlatához képest**: ott
    `wp_mtmt_pub_group (pub_id, term_id)` szerepelt — a `term_id` nevet
    szándékosan `topic_area_id`-ra cseréltem, mert "term" a WP natív
    taxonómia-rendszerére utalna (`wp_terms`), amit pont NEM használunk (#42).
    A táblanevet is `mtmt_pub_topic_area`-ra neveztem át ez okból (konzisztens
    az "topic_area" elnevezéssel mindenhol a kódban).

44. **Terület↔aloldal párosítás**: `page_id` oszlop, `wp_dropdown_pages()`-szel
    választva ki admin oldalon — NEM szabad URL-mező. Indoklás: a §7 explicit
    "WP-aloldal" (nem tetszőleges link) párosítást ír elő, egy page-picker
    validálja is, hogy a hivatkozott oldal tényleg létezik, és később
    (Fázis 5) egyszerűen visszakereshető a link/permalink belőle.

45. **Terület-hozzárendelés (melyik publikáció melyik területhez tartozik)
    `mtmt_classify`-hoz kötve**, a terület-DEFINIÁLÁS (mi létezik, melyik
    oldalhoz tartozik) `manage_options`-höz — ugyanaz a réteg-elválasztás,
    mint a query-profiloknál (ki határozza meg a kereteket) vs. a
    projektazonosító-ellenőrzésnél (ki dönt egy konkrét tételről).

46. **`get_list()` bővítve egy `ids` szűrővel** (nem topic-area-specifikus a
    repository szintjén — bármilyen előre kiválogatott id-halmazra szűkít).
    Ha `ids` explicit ÜRES tömb (pl. egy területhez még nincs egyetlen
    publikáció sem rendelve), a metódus AZONNAL 0 találatot ad, DB-hívás
    nélkül — ez szándékosan más, mint az "ids nincs megadva" eset (nincs
    szűrés). Unit-teszttel külön ellenőrizve, hogy ez a rövidzár tényleg
    lefut és nem esik vissza "nincs szűrés"-re.

47. **`page_id=0` normalizálva `NULL`-ra tároláskor** (`Mtmt_Topic_Area_
Repository::create()`), mert a `wp_dropdown_pages()` "— nincs kiválasztva —"
    opciója `0`-t küld, de `0` nem érvényes post ID — a `NULL` egyértelműbben
    jelzi "nincs hozzárendelt oldal", mint egy hamis `0` post ID.

48. A "Kész, ha: egy terület aloldalán csak az adott terület tételei jönnek"
    kritérium (eredeti roadmap-szöveg) **csak Fázis 5 után zárható le
    ténylegesen** — widget nélkül nincs mit "aloldalon" megjeleníteni. Fázis 4
    az adatmodellt + admin UI-t adja, és előkészíti a `get_publication_ids_
for_area()` metódust, amit a Fázis 5-ös "B" widget közvetlenül hívhat majd.

## Fázis 5 — Elementor widgetek (2026-08)

Előzetesen egyeztetett 3 döntés (AskUserQuestion): teljes szerzőnév
listakorlátozással (nem monogram), szerver-oldali (GD) placeholder-kép-
generálás (nem CSS-overlay), és év-fülek (nem accordion) — ezek CLAUDE.md
§14/8-at és a docs/widget-design.md "Nyitott kérdések" szakaszát véglegesítik,
lásd ott. Az alábbiak az eziutáni implementációs döntések.

49. **Elementor widget-osztályok NEM kapnak dependency injectiont a
    konstruktorban.** Az Elementor több kódúton (szerkesztő-AJAX, dokumentum-
    betöltés) saját maga hozza létre a widget-példányt `new static($data, $args)`
    alakban — egy egyedi, kötelező konstruktor-paraméter ezeken az útvonalakon
    eldobná a widgetet. A repository-kat/`Mtmt_Widget_Data`-t ezért a `render()`-en
    belül, lazy módon építik fel (`global $wpdb`), ugyanaz a minta, mint a
    mtmt-sync.php admin-hookjaiban.

50. **A "B" widget scope-ja terület VAGY lekérdezési profil lehet**, nem csak
    terület — a CLAUDE.md §14/10 eredeti "területre/profilra szűkítve"
    megfogalmazását szó szerint vettem: ha a "szakmai terület" funkció ki van
    kapcsolva egy site-on, a "B" widget így is használható marad (profil-módban),
    a regisztráció egyedüli feltétele a "kiemelt cikk" toggle (widget-design.md,
    FÜGGETLEN a terület-togletől).

51. **Font-választás a placeholder-képhez: becsomagolt Open Sans Bold**
    (SIL OFL 1.1, `assets/fonts/OpenSans-Bold.ttf` + `OFL.txt`), NEM a szerver
    esetleges rendszer-betűtípusa. Indoklás: (a) rendszer-font elérhetősége/útja
    nem garantálható különböző hosting-környezetek között, (b) a magyar ékezetes
    karakterek (különösen ő/ű, a duplavesszős variánsok) nem minden fontban
    szerepelnek — élőben, ténylegesen legenerált teszt-képpel verifikálva, hogy
    az Open Sans helyesen rendereli az "ŐŰ" karaktereket is. Felülírható
    (`mtmt_placeholder_font_path` option), de nincs hozzá admin UI — ez csak
    programozott/advanced use-case, a becsomagolt font az elvárt alapeset.

52. **Placeholder-kép cache: nincs automatikus törlés/GC.** A generált fájl neve
    `md5(mtid|cím|alapkép-útvonal|alapkép-mtime)` — cím- vagy alapkép-csere
    automatikusan új fájlt generál, de a régi (immár hivatkozatlan) fájl a
    lemezen marad. Tudatos egyszerűsítés ebben a körben (nincs cron-alapú
    cache-tisztító job) — ha a lemezhasználat gondot okozna élesben, ez egy
    külön, kis backlog-tétel lehet.

53. **`Mtmt_Widget_Data` cache: 5 perces tranziens, nincs explicit invalidálás**
    admin-mentéskor. A CLAUDE.md §9.1 "object cache/tranziens" javaslatát a
    legegyszerűbb formában valósítja meg — egy moderációs jóváhagyás legfeljebb
    5 percen belül látszik meg a frontenden. Ha ez élesben túl lassúnak
    bizonyul, a következő lépés egy verzió-számláló opció lenne, amit minden
    repository-írás (upsert/set_status/save_enrichment) növelne, és ami a
    tranziens-kulcs része lenne — nem épült be most, hogy ne nőjön feleslegesen
    a kód, amíg nincs bizonyíték rá, hogy kell.

54. **Whole-card link mindig új fülön nyílik** (`target="_blank"`), a cím-linkre
    ÉS a JS-es "bárhol a kártyán" kattintásra is egyformán — konzisztencia
    kedvéért (kezdetben csak a JS-utat terveztem `window.open`-nel, a valódi
    `<a>` címlinket alapból ugyanígy kellett módosítani, különben a két
    navigációs útvonal eltérően viselkedett volna).

55. **"Hivatkozás-stílus: kompakt/teljes" (CLAUDE.md §9.1) értelmezése**: mivel
    a spec nem részletezi, mit takar pontosan — kompakt módban a forrás-sor
    (`source_title`) és az egyéb-azonosítós logó-gombok elmaradnak a kártyáról,
    minden más (cím, szerzők, terület-badge, év, SJR, DOI-szöveg) megmarad.
    Élő megtekintés után finomítható, ha a megrendelő mást vár ez alatt.

56. **Szerzőnév-levágás szövege angol ("…, and N more")**, nem magyar
    ("és N további szerző") — konzisztens az `authors_text` mögötti
    `join_with_and()` meglévő, már élesben validált angol "and"-konvenciójával
    (CLAUDE.md §5.4, a megrendelő saját mintadokumentuma). Ha a megrendelő
    inkább magyar utótagot szeretne, ez egy lokalizált string-csere,
    `Mtmt_Author_Formatter::format_for_card()`-ban.

57. **Év-fülekhez hozzáadva egy "Összes" fül** (0 = nincs év-szűrés) — a
    widget-design.md konkrét évszámokat sorol fel (2026|2025|...), egy
    "minden év" opciót nem említ explicit, de hasznos kiegészítésnek tűnt (pl.
    keresésnél ne kelljen évenként külön kattintani). Élő visszajelzés után
    törölhető, ha a megrendelő nem akarja.

58. **JAVÍTVA élő teszt után: a widgetek nem jelentek meg az Elementor
    widget-panelen.** Az eredeti `Mtmt_Elementor_Loader` az `elementor/widgets/
register` és `elementor/elements/categories_registered` felakasztását egy
    `elementor/loaded`-re kötött `boot()` mögé rejtette. Ez hook-sorrendi hiba:
    az Elementor a Widgets_Manager/Elements_Manager saját inicializálása
    során, MÉG az `elementor/loaded` tényleges kitüzelése ELŐTT elsüti ezeket
    az akciókat — mire a mi `boot()`-unk lefutott volna, az Elementor már
    végzett a widget-regisztrációs körrel, a mi `add_action()`-jeink elkéstek,
    soha nem futottak le. A javítás: `elementor/widgets/register` (és a másik
    két akció) feltétel nélkül, közvetlenül a plugin-betöltéskor felakasztva —
    ez az Elementor saját fejlesztői dokumentációjának mintája is. A védelem
    (ne töltődjön be `\Elementor\Widget_Base`-t kiterjesztő fájl Elementor
    nélkül) továbbra is megvan: a `require_once`-ok a callback BELSEJÉBEN
    maradtak, ami Elementor nélkül sosem fut le, mert maga az akció sosem tüzel.

59. **JAVÍTVA élő teszt után: jóváhagyás után akár 5 percig a régi (cache-elt)
    listát mutatta a widget**, csak egy admin-mentés (bármilyen, nem feltétlen
    a kapcsolódó rekordé) után frissült — ez a `Mtmt_Widget_Data` sima,
    5 perces TTL-ű tranziens-cache-éből fakadt (a #53-ban már előre jelzett
    kockázat, most ténylegesen élesben is jelentkezett). Megoldás:
    `Mtmt_Widget_Cache` — egy `wp_options`-beli verziószámláló, amit minden
    widget-láthatóságot érintő írás NÖVEL (`set_status`, `bulk_set_status`,
    `bulk_set_featured`, `save_enrichment`, `Mtmt_Topic_Area_Repository::
set_areas_for_publication()`/`delete()`, és EGYSZER egy teljes
    `Mtmt_Sync_Runner::run()` végén — nem rekordonként, hogy egy nagy
    intézménynél ne legyen száz+ felesleges `update_option()`-hívás egy
    szinkron alatt). A `Mtmt_Widget_Data` cache-kulcsai tartalmazzák ezt a
    verziót — így egy admin-írás után az ELSŐ frontend-lekérdezés azonnal
    "cache-miss" lesz. A TTL-t emiatt fel lehetett emelni 5 percről 1 órára
    (csak biztonsági háló, ha egy írási útvonal mégis kimaradna a bump()-ból),
    ami ritkább DB-írást is jelent a gyakori (változatlan) lekérdezéseknél.

60. **Élő visszajelzés után: widget-szintű szöveg- és stílus-testreszabás.**
    Két kérés: (a) a korábban kőbe vésett (`__()`-be ágyazott) feliratok
    (fejléc-cím, kereső helyőrző, üres-lista üzenet, lapozó "előző/következő")
    legyenek szerkeszthetők widget-példányonként; (b) legyen Elementor
    Stílus-fül (színek/tipográfia/kártya-megjelenés). Megoldás: egy közös
    `Mtmt_Widget_Common_Controls` TRAIT (nem külön osztály — mindkét widget
    `\Elementor\Widget_Base`-t terjeszt ki, a trait csak a control-regisztrációt
    DRY-osítja), amit mindkét widget használ:
    - `register_mtmt_text_controls(array $defaults)` — csak azokhoz a
      kulcsokhoz ad TEXT controlt, amik szerepelnek a hívó widget saját
      `$defaults` tömbjében, így az "A" és "B" widget természetesen kapja meg
      a saját releváns mezőkészletét (pl. a terület-szűrő felirata csak
      "A"-nál értelmes, a "nincs terület/profil kiválasztva" üzenet csak "B"-nél).
    - `register_mtmt_style_controls()` — a színek NEM közvetlen CSS-tulajdonságot
      írnak felül, hanem a widget.css-ben már meglévő CSS-változókat
      (`--mtmt-accent`, `--mtmt-border`, `--mtmt-muted`, `--mtmt-bg-soft`) a
      `{{WRAPPER}} .mtmt-widget` szinten — mivel a teljes stíluslap ezekre a
      változókra épül, ez egyetlen ponton ad valódi, széleskörű testreszabást
      négy Color controllal, nem kellett minden CSS-szabályt egyenként
      exponálni. Typography group-controlok a címre/kártya-címre/törzsszövegre,
      plusz kártya-háttérszín/lekerekítés/belső margó.
    - A "Tartalom fülön szerkeszthető, de a swappelt AJAX-fragmentben (kártya-lista,
      lapozó) is megjelenő" szövegek (`empty_state_text`, `pagination_prev_label`,
      `pagination_next_label`) `data-*` attribútumokon keresztül a JS-be, onnan
      minden AJAX-kérés POST body-jában a szerverre jutnak — a header/kereső
      helyőrző/év-fül-felirat/terület-szűrő-felirat NEM része az AJAX-cserének
      (a `.mtmt-widget-controls`/`.mtmt-year-tabs` blokk sosem cserélődik,
      csak a `.mtmt-widget-list`/`.mtmt-widget-pagination`), ezért azokhoz elég
      a kezdeti szerver-oldali render().

## Fázis 6 — GitHub + PUC (2026-08)

61. **PUC v5.7 bevendorolva egyszerű másolással, NEM git-almodulként** —
    a GitHub-repó release-zip-jét (`YahnisElsts/plugin-update-checker`
    v5.7 tag) töltöttem le és csomagoltam ki `lib/plugin-update-checker/`-be,
    a `.git`-independens forrás-fájlokkal (nincs benne `.git`/`.github`,
    teszt- vagy composer-lock fájl). Indoklás: CLAUDE.md §10.4 mindkét utat
    megengedi ("verziózva marad, kivéve ha git-almodulként húzod be") — a
    sima másolás egyszerűbb, nem igényel almodul-kezelést a klónozóktól
    (`git clone` + `git submodule update` extra lépés lenne, könnyen elfelejthető).
62. **`use YahnisElsts\PluginUpdateChecker\v5\PucFactory;` a fájl TETEJÉN,
    NEM az `is_admin()` if-ág belsejében** — élesben derült ki tesztelés
    közben (nem a végfelhasználónál, saját ellenőrzés során): PHP parse
    errort ad, ha egy `use`-importáló deklaráció feltételes blokk belsejében
    szerepel ("syntax error, unexpected token 'use'"). Az import maga
    ártalmatlan feltétel nélkül is (nem tölt be semmit, csak aliast rendel
    egy osztálynévhez) — a tényleges `require`/`PucFactory::buildUpdateChecker()`
    hívás marad az `is_admin()` mögött.
63. **`is_admin()` mögé kötve a teljes PUC-init.** A frissítés-ellenőrzésnek
    nincs frontend-relevanciája, ezért nem éri meg minden nyilvános
    oldalbetöltésnél betölteni a ~120 fájlos, 700+ KB-os könyvtárat.
64. **Nincs `setAuthentication()`/token** — a repo publikus
    (`SZKK-SZUCS/MTMT_WP_plugin`), a CLAUDE.md §10.3 explicit tiltja a token
    commitolását; ha a repo valaha privátra váltana, ez wp-config.php
    konstansként vagy szűrőn keresztül adandó át, nem a kódba írva.
65. **Nincs `enableReleaseAssets()`** — a plugin nem használ build-lépést
    (CLAUDE.md §2, §10.3), a GitHub által automatikusan generált forrás-zip
    elég a PUC-nak, a mappa-wrappelést maga a PUC kezeli.

## Fázis 7 — döntések a megrendelőtől (2026-08)

66. **BibTeX — mi ez, elmagyarázva a megrendelőnek.** A BibTeX egy egyszerű,
    szöveges hivatkozás-formátum (LaTeX-bibliográfiákhoz született, de ma
    minden nagyobb referenciakezelő — Zotero, Mendeley, EndNote — is beolvassa).
    Egy rekord kb. így néz ki:
    ```
    @article{igneczi2026autonom,
      author  = {Ignéczi, Gergő and Tóth, Roland},
      title   = {...},
      journal = {...},
      year    = {2026},
      doi     = {10.xxxx/...}
    }
    ```
    Az ötlet (CLAUDE.md §9.2) egy "BibTeX" lenyíló gomb lett volna minden
    widget-kártyán, amiből a látogató (jellemzően egy másik kutató, aki
    hivatkozni akar a cikkre) egy kattintással kimásolhatja ezt. **Döntés
    függőben** — a megrendelő megkérdezte, mi ez, válasz elküldve, még nincs
    igen/nem. Ha mégis kell: az MTMT API maga is exportál BibTeX-et
    (`export=1&exportFormat=BIBTEX`, CLAUDE.md §5.3) — nem feltétlenül
    kellene nekünk a strukturált mezőkből újraépíteni, elég lehet egy
    linkgomb az MTMT saját export-végpontjára.
67. **Haladó frontend-szűrők (szerző/típus/folyóirat/SJR/Norvég-szint) ELVETVE**
    — a megrendelő szerint a jelenlegi keresés + év-fül + terület-szűrő elég.
68. **Norvég-szint elhalasztva, nem blokkolja az 1.0-t** — a megrendelő szerint
    egyelőre nem kell, az API-út felderítése (docs/decisions.md #25, docs/
    field-map.md) nyitva marad, amíg újra elő nem vesszük.

## Profil-előnézet ("Preview") — aktiválva a backlogból (2026-08)

69. **A "Profil neve" mező hidden helyett két NÉV SZERINTI submit-gombra
    váltott** (`name="mtmt_action" value="preview"` / `value="create"`) az
    eddigi `<input type="hidden" name="mtmt_action" value="create">` helyett —
    ez a szokásos HTML-minta több submit-célra egy formban, nem kellett
    külön JS. Mellékhatás (elfogadott, sőt inkább jó): Enter lenyomása a
    "Profil neve" mezőben most az "Előnézet" gombot süti el elsőként (a DOM-ban
    ez van előrébb), nem a mentést — ez biztonságosabb alapértelmezés, mint
    a korábbi (ahol Enter egyből létrehozta volna a profilt).
70. **`Mtmt_Profiles_Page` konstruktora kapott egy `Mtmt_Api_Client`
    paramétert, PHP 8.1 "new in initializers"-szel alapértelmezve**
    (`Mtmt_Api_Client $api_client = new Mtmt_Api_Client()`) — így a
    mtmt-sync.php-beli meglévő hívási hely (`new Mtmt_Profiles_Page($profile_repo)`)
    nem tört el, de a metódus mégis DI-vel, tesztelhetően kapja a klienst
    (konzisztens a repository-k eddigi minta injektálásával).
71. **A találatszám-figyelmeztetés küszöbe (2000) heurisztika, NEM biztos
    jel** — a docs/field-map.md dokumentált mintája (~5000/becsült 11M = a
    teljes adatbázis, ha a cond csendben nem érvényesült) alapján, de mivel
    egy legitim, nagy intézmény is adhat ennél több valós találatot, ez csak
    figyelmeztet, nem blokkol — a mentés gomb a figyelmeztetés mellett is
    elérhető marad. Az elsődleges ellenőrzési eszköz az 5 mintacím/szerző
    vizuális átnézése (a backlog eredeti célja szerint).
72. **A `depth=1` az előnézet-hívásban is kötelező** (nem `depth=0`) —
    ugyanaz az ok, mint a syncnél (docs/field-map.md): `depth=0` mellett
    hiányzik az `authorships[]`, tehát a mintasorokban nem lenne szerzőnév,
    csak cím. Az előnézet ugyanazt a `Mtmt_Mapper::map_publication()`-t
    hívja, mint a valódi sync — nem kellett külön leképző logika.
73. **Az előnézet-eredmény NEM tárolódik sehol** (sem DB-ben, sem
    tranziensben/session-ben) — egyetlen kérés-válasz ciklus élettartama,
    a `Mtmt_Profiles_Page` egy privát property-jében (`$preview_result`),
    ami minden oldalbetöltéskor újraépül vagy üres. Ez szándékos: az
    előnézet definíció szerint egyszeri, nem kell hozzá state.

## Email-értesítő újratervezése (2026-08)

74. **HTML email, NEM sima szöveges** — `wp_mail()` negyedik paramétereként
    explicit `Content-Type: text/html; charset=UTF-8` header nélkül a WP
    alapból text/plain-ként küldi, és minden HTML-jelölés nyersen látszana
    a postafiókban. Csak inline CSS-stílusok (a legtöbb emailkliens nem
    futtat megbízhatóan `<style>` blokkot), nincs multipart/alternative
    szöveges verzió — tudatos egyszerűsítés, nem minden emailkliens/
    -olvasó éri el vele ugyanazt az élményt, de a cél ("jobb legyen, legyen
    benne logó") ezzel teljesül build-lépés/extra könyvtár nélkül.
75. **A logó a PLUGINBA becsomagolt statikus fájl, NEM site-onkénti admin-
    beállítás.** Eredetileg egy Beállítások-oldali média-uploadert építettem
    (ugyanaz a minta, mint a placeholder-kép alapképnél) — a megrendelő
    menet közben pontosított: "ez rendszer email, én égetem be központilag
    a pluginba mint kiadó". Ez logikus is a "dobozos, több szervezetnek"
    tervezési elvhez (CLAUDE.md §1, §7 stb.) illesztve: ez egy RENDSZER-email
    a "MTMT Sync" terméktől/kiadótól, nem az adott kliens szervezet saját
    brandje — minden site-on ugyanaz a fejléc-logó megy ki, verzióval együtt
    terjesztve, nem admin-konfigurációként. A médiatáras UI-t és a hozzá
    tartozó `mtmt_email_logo_id` optiont törölve lett (nem maradt kód-lom).
76. **A logó-fájl helye és keresési sorrendje**: `Mtmt_Notifier::get_logo_url()`
    sorban megnézi `assets/img/mfui-logo.png`, `.jpg`, `.jpeg` — az első
    létező nyer, `file_exists()`-szel ellenőrizve (nem feltételezi, hogy a
    fájl ott van). Ha egyik sem létezik, `null`-t ad, az email egyszerűen
    logó nélkül megy ki — nem hiba, nem blokkol. SVG szándékosan NINCS a
    listában: sok emailkliens (Outlook, több mobil Gmail-app) nem renderel
    megbízhatóan SVG-t `<img>`-ben.
77. **A profil `#ID` helyett a profil NEVE jelenik meg az emailben** — a
    `Mtmt_Notifier::notify_if_needed()` kapott egy opcionális
    `array $profile_labels` paramétert (`profile_id => label`), amit a
    `Mtmt_Sync_Runner::run()` épít fel (ott már úgyis elérhető a
    `Mtmt_Query_Profile_Repository`) — ugyanaz a minta, mint a Beállítások
    oldal futás-naplójánál (`$profile_labels[$id] ?? ('#' . $id)` fallback).
78. **Világos, designolt háttér a sötét feliratú logóhoz** — a becsomagolt
    logó (`assets/img/mfui-logo.png`, MFÜI/Széchenyi István Egyetem) sötét
    navy feliratú, átlátszó alapra tervezve. A `build_html_body()` ezért
    teljes HTML-dokumentumot ad vissza (nem csak egy `<div>`-fragmentet),
    explicit világos `<body>`-háttérrel (`#eef2f6`) egy fehér, lekerekített
    "kártya" körül, a logó saját navy/teal színvilágához igazított
    paletta — enélkül egy sötét emailkliens-háttéren (dark mode) a sötét
    felirat olvashatatlanná válna.

## 0.9 előkészítés — megrendelői döntések (2026-08)

79. **Dimensions idézettség-badge — mi ez, elmagyarázva a megrendelőnek.**
    A [Dimensions.ai](https://www.dimensions.ai) egy ingyenes, DOI-alapú
    idézettség-adatbázis; a `badge.dimensions.ai/badge.js` script egy kis
    beágyazott jelvényt rajzol ki (`<span class="__dimensions_badge_embed__"
    data-doi="10.xxxx/...">`), ami mutatja, hányszor idézték az adott
    publikációt, és rákattintva a Dimensions saját oldalára visz (ott
    listázva az idéző műveket is). Előnye az MTMT saját idézettség-számával
    szemben: a Dimensions adatbázisa folyamatosan, automatikusan frissül a
    látogatóban (nem csak a heti MTMT-szinkronnal), és szélesebb/gyakran
    naprakészebb forrásból számol. Hátránya: külső script fut le a látogató
    böngészőjében (a `badge.dimensions.ai`-hoz megy ki egy kérés DOI-nkénti
    bontásban) — ez egy apró adatvédelmi megfontolás, amit érdemes jelezni
    a látogatónak, és lazy-load-dal érdemes betölteni, hogy ne lassítsa az
    oldalt. **Döntés még nem született** — a megrendelő megkérdezte, mi ez,
    a BibTeX-szel ellentétben itt nem érkezett "nem kell" válasz.
80. **BibTeX lenyíló — ELVETVE**, lásd docs/roadmap.md Fázis 7 szakasz.
81. **Egyéb azonosítós SVG-ikonok: becsomagolt, inline-olt, fill-mentesített.**
    A megrendelő maga szerzi be a logókat, egyszínű SVG-ként — a kód oldala
    a `docs/external-id-icons.md`-ben rögzített fájlnév-konvenció szerint
    (`assets/img/icons/{slug}.svg`) automatikusan felismeri és inline-olja
    őket (`Mtmt_External_Id_Icons::get_icon_svg()`), ikon hiányában szépen
    visszaesik a meglévő feliratos pill-badge-re. Az SVG-t NEM `<img
    src="...">`-ként, hanem közvetlenül a HTML-markupba ágyazva adjuk ki,
    mert csak így tud a CSS `fill: currentColor`-ral színt váltani rajta —
    egy `<img>`-be ágyazott SVG-t a böngésző nem engedi CSS-ből színezni. Az
    esetleges explicit `fill="..."` attribútumokat betöltéskor eltávolítjuk
    (reguláris kifejezéssel), hogy ne kelljen "CSS-re felkészített", speciális
    exportot kérni a megrendelőtől — bármilyen szokásos monokróm SVG-export
    működik. A fájl tartalma a fejlesztő/kiadó által elhelyezett, megbízható
    erőforrás (nem látogatói input), ezért nincs futásidejű `wp_kses`-szerű
    sanitizálás — ugyanaz a bizalmi szint, mint a placeholder-kép/email-logó
    fájloknál.
82. **Role→capability admin UI, tetszőleges WP-szerepkörre** (a megrendelő
    kérése: "role capability mindenképp kell"). Új "Jogosultságok" almenü
    (`Mtmt_Capabilities_Page`, `manage_options`), táblázat MINDEN létező
    WP-szerepkörrel (nem csak Editor/Administrator), két pipa oszloppal
    (Moderálás = `mtmt_moderate`, Besorolás = `mtmt_classify`). A tárolt
    leképzés (`mtmt_moderate_roles`/`mtmt_classify_roles` option) mentéskor
    AZONNAL rá is vetül a WP_Role objektumokra, `add_cap()`-pel ÉS
    `remove_cap()`-pel is (nem csak hozzáadás — egy kivett pipa ténylegesen
    el is veszi a jogot). `Mtmt_Capabilities::activate()` `add_option()`-t
    használ `update_option()` helyett, hogy egy már testreszabott
    telepítésen egy újraaktiválás/frissítés NE írja felül az admin saját
    beállítását az eredeti Editor+Administrator alapértelmezésre — unit-
    teszttel külön ellenőrizve (lásd test-capabilities.php #4).
83. **`languages/` + `.pot`/angol `.po`/`.mo` pótolva** (CLAUDE.md §0/§2 eddig
    hiányzó előírása). Saját, kis PHP-tokenizeres kinyerő eszköz
    (`bin/i18n/build.php`) — WP-CLI i18n parancs vagy Node-alapú i18n-tooling
    NEM kellett hozzá (CLAUDE.md §2 "nincs build-lépés" elve), sima `php
    bin/i18n/build.php` a teljes folyamat. A 228 kinyert string mindegyikéhez
    kézzel felvett angol fordítás van (`bin/i18n/translations-en.php`, a
    kézzel karbantartott igazságforrás) — ha egy stringhez nincs fordítás,
    a szkript listázza és HIBÁVAL kilép, nem generál hiányos fájlokat.
84. **A `.mo` bináris fájlt saját PHP-writer állítja elő**, nem külső
    `msgfmt` binárist hív (ami nem biztos, hogy elérhető minden fejlesztői
    gépen) — a GNU MO formátum-specifikációt követi (fejléc, rendezett
    eredeti-string tábla binális kereséshez, offset-táblák). Kétféleképp
    ellenőrizve: (a) egy teljesen független, saját bájt-szintű MO-olvasóval
    (nem a writerrel megosztott kóddal) visszaolvasva néhány ismert
    kulcs→fordítás párt, (b) PHP natív `gettext` kiterjesztésével is
    próbálva — utóbbi Windows-on NEM adta vissza a fordítást (ismert,
    dokumentált Windows-specifikus `LC_MESSAGES` korlátozás a PHP gettext
    kiterjesztésben), DE ez NEM releváns éles WordPress-környezetre: a WP
    a saját, tiszta PHP-s MO-parserét használja (`wp-includes/pomo/`), nem
    az OS gettext-jét/`setlocale()`-t — pont ezért nem is bízhat az OS
    gettext-ben WP soha. A saját bájt-szintű olvasós teszt pontosan azt az
    utat validálja, amit WP is használ.
85. **`Domain Path: /languages` felvéve a plugin fejlécbe** — a
    `load_plugin_textdomain()`-t már eddig is explicit útvonallal hívtuk
    (nem a fejlécre támaszkodva), de a `Domain Path` fejléc-mező a WPCS/
    wp.org-i18n-eszközök konvenciója, hiánya esetén egyes scannerek
    hibásan jeleznék, hogy a plugin nem i18n-kompatibilis.
86. **README cron-szakasz bővítve két elfogadott megoldással**, nem csak a
    `DISABLE_WP_CRON`+WP-CLI úttal. A megrendelőnek már van egy központi,
    Docker-alapú cron-pinger szolgáltatása (más site-okhoz), ami
    rendszeresen (5 percenként) meghívja a `wp-cron.php?doing_wp_cron`
    végpontot — ez ÖNMAGÁBAN elég megbízható triggerelést ad a heti
    MTMT-szinkronhoz is, `DISABLE_WP_CRON` NÉLKÜL: a sima `wp-cron.php`
    hívás minden esedékes WP-cron eseményt elindít (nem csak egy
    plugin-specifikusat), a plugint futtató site domainje egyszerűen
    felvehető a meglévő pinger-listára. A `DISABLE_WP_CRON`+WP-CLI út
    megmaradt B) opcióként (determinisztikusabb időzítés, de rendszer-cron
    hozzáférést igényel) — egyik módszer sincs kikényszerítve a kódban.
