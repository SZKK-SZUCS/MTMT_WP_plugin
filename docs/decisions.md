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
   `wp_jkk_mtmt_query_profiles.cond_json`-ban él, telepítésenként konfigurálva.
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
    WP-CLI) a query profilokhoz, `admin/class-jkk-mtmt-profiles-page.php`,
    `manage_options` kapabilitáshoz kötve (külön a moderációs
    jkk_mtmt_moderate/classify capability-któl, mert ez site-config, nem
    napi moderáció). UX: mód-választó (Intézmény MTID / Szerző MTID-lista /
    Haladó nyers cond JSON) + egy érték-mező, a plugin építi belőle a
    cond_json-t. Ugyanazt a Jkk_Mtmt_Query_Profile_Repository-t használja,
    mint a `wp jkk-mtmt profile create` CLI parancs — nincs duplikált logika.

13. Fázis 1 alapkód elkészült (activator, api-client, mapper, két repository,
    sync, WP-CLI, admin profil-oldal, bootstrap). `php -l` mind tiszta.
    A mapper külön, WP-független harnessben lefuttatva a valós
    mtmt_pub_depth2.json mintán (37139647) — minden mező helyesen jött
    (authors_text sorrend+"and", SJR Best-Q=D1 a több rating közül,
    page_range fallback "Paper: 3975", DOI/Egyéb URL/external_ids szétválasztás).
    Végigmenő WP+MySQL integrációs teszt (`wp jkk-mtmt sync` élesben, JKK
    profillal, mtid=19662) MÉG NEM történt meg — nincs helyi WP-telepítés,
    csak XAMPP PHP/MySQL. Ha kell, felállítható egy helyi WP-instance a
    teljes Fázis 1 elfogadási kritérium (nem duplikál újrafuttatva, lapozás)
    éles ellenőrzéséhez.

14. Fázis 1 éles integrációs teszt SIKERES: Local WP-oldal (mtmt-wp-plugin),
    junction-nel bekötve, admin UI-n létrehozott JKK profil (mtid=19662),
    `wp jkk-mtmt sync` -> 767 új / 0 frissített / 0 hiányzó, PONTOSAN a JKK
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
