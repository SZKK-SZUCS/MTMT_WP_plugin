# CLAUDE.md — JKK MTMT Publications plugin (build brief)

> Ezt a fájlt a repo gyökerébe tedd `CLAUDE.md` néven. A Claude Code automatikusan beolvassa, és ez a mérvadó specifikáció. Ha bármi ütközik a kódban lévővel, ez a fájl nyer, amíg felül nem íratjuk.

---

## 0. TL;DR a Claude Code-nak

Építs egy **WordPress plugint**, ami az **MTMT publikus REST API-jából** hetente lekérdez publikációkat megadott szűrőprofilok alapján, wp-adminban **jóváhagyható/elutasítható és kézzel gazdagítható** (indexkép, kutatócsoport, projektazonosító-ellenőrzés), majd egy **Elementor widget** naptári évekre bontva, kereshetően/szűrhetően megjeleníti a jóváhagyott tételeket. A plugin **GitHubon él**, és a **Plugin Update Checker (PUC) v5**-tel frissül az éles oldalakon.

**FONTOS első lépés, mielőtt kódot írsz:** csinálj egy éles minta-lekérést egy konkrét `mtid`-re (lásd 5.6), és a tényleges JSON-válaszból térképezd fel a mezőneveket. Az MTMT mezőnevei verziófüggők — ne találj ki kulcsneveket, a valós válaszra építs. Ahol a spec „ellenőrizd élesben" jelölést tesz, ott tényleg kérdezz vissza vagy tesztelj, ne tippelj.

---

## 1. Kontextus és cél

A JKK (intézmény) publikációs listát akar a WordPress-oldalain, MTMT-ből automatikusan táplálva, de emberi jóváhagyással. A megjelenítés szakmai kutatócsoportok szerinti aloldalakon történik, évekre bontva. A lekérdezés hetente fut. Az adat forrása az MTMT; a jóváhagyási státusz és a kézi kiegészítések forrása a plugin saját adatbázisa.

**Alapelv:** *MTMT = adat forrása; WP = jóváhagyási státusz + kézi gazdagítás.* A kettőt az `mtid` (MTMT rekord-azonosító) köti össze.

---

## 2. Tech stack és megkötések

- **WordPress plugin**, PHP **8.1+**, WP **6.4+**.
- **Elementor** widget (Elementor aktív; a widgetnél ellenőrizd a függőséget, és degradálj szépen, ha nincs).
- **Nincs kötelező build lépés.** Vanilla PHP + vanilla JS + sima CSS. Ne hozz be Node/webpack build-pipeline-t, hacsak elkerülhetetlen. (A frontend szűrés mehet vanilla JS-ből vagy pici Alpine.js-ből CDN nélkül, bundle-özve.)
- **WordPress Coding Standards** (WPCS). Prefix mindenhol: `jkk_mtmt_` (függvények), `Jkk_Mtmt_` (osztályok), text domain: `jkk-mtmt-publications`.
- **i18n**: minden felhasználói szöveg `__()`/`esc_html__()` a `jkk-mtmt-publications` domainnel; `languages/` mappa + `.pot`.
- Adatbázis: saját táblák (`$wpdb`), **nem** CPT (nagy, strukturált, `mtid`-kulcsos upsert miatt). A kutatócsoport viszont **taxonómia** (lásd 7).
- Ne használj külső PHP-csomagkezelőt futásidőben; a PUC-ot **vendorold be** a repóba (lásd 10).

---

## 3. Architektúra

Három réteg:

1. **Ingest** — WP-Cron (heti) → MTMT API hívás profilonként → upsert a saját táblába `pending` státuszban.
2. **Moderáció** — wp-admin lista + szerkesztő űrlap: jóváhagyás/elutasítás **és** kézi gazdagítás.
3. **Megjelenítés** — Elementor widget, csak `approved` tételek, évekre bontva, szűrhetően. A widget **kizárólag a saját tábfrom olvas**, sosem hívja élesben az MTMT-t.

```
WP-Cron (heti) ─► Ingest ─► [MTMT REST API]
                    │  upsert (mtid kulcs, manuális mezők védve)
                    ▼
        wp_jkk_mtmt_publications  (pending/approved/rejected)
             │                                   │
             ▼                                   ▼
    Admin moderáció+gazdagítás          Elementor widget (approved)
```

---

## 4. Adatmodell

### 4.1 `wp_jkk_mtmt_publications`

```sql
CREATE TABLE {prefix}jkk_mtmt_publications (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mtid            BIGINT UNSIGNED NOT NULL,

  -- MTMT-forrású mezők (heti syncnél frissülnek)
  title           TEXT,
  authors_text    TEXT,               -- tiszta, formázott névsor (lásd 5.4)
  authors_raw     LONGTEXT,           -- JSON: szerző-objektumok
  pub_type        VARCHAR(120),       -- besorolás (Folyóiratcikk / Könyvrészlet…)
  pub_category    VARCHAR(120),       -- altípus (Szakcikk / Konferenciaközlemény…)
  pub_character   VARCHAR(60),        -- jelleg (Tudományos…)
  language        VARCHAR(40),
  source_title    TEXT,               -- folyóirat címe VAGY befogadó mű
  volume          VARCHAR(40),        -- Kötet
  issue           VARCHAR(40),        -- Füzet
  page_range      VARCHAR(60),
  published_year  SMALLINT,           -- évekre bontáshoz, indexelt
  doi             VARCHAR(255),
  issn            VARCHAR(20),
  sjr_quartile    VARCHAR(8),         -- Best Q: D1/Q1/Q2/Q3/Q4 (lásd 5.5)
  norway_level    VARCHAR(8),         -- opcionális, ha elérhető
  external_ids    LONGTEXT,           -- JSON: WoS/Scopus/SZTAKI stb.
  other_url       TEXT,               -- Egyéb URL
  funding_text    TEXT,               -- importált támogatás(ok)
  mtmt_state      VARCHAR(60),        -- MTMT belső státusz (Nyilvános/Egyeztetett…)
  raw_json        LONGTEXT,           -- teljes MTMT objektum (későbbi mezőkhöz)

  -- Kézi / plugin-oldali mezők (syncnél SOHA nem íródnak felül)
  status          ENUM('pending','approved','rejected') DEFAULT 'pending',
  thumbnail_id    BIGINT UNSIGNED NULL,   -- WP attachment ID (indexkép)
  funding_override TEXT NULL,             -- felülbírált támogatás
  project_ids     TEXT NULL,              -- projektazonosító(k)
  project_verified TINYINT(1) DEFAULT 0,  -- ellenőrizve?
  verified_by     BIGINT UNSIGNED NULL,
  verified_at     DATETIME NULL,
  moderated_by    BIGINT UNSIGNED NULL,
  moderated_at    DATETIME NULL,
  query_profile_id BIGINT UNSIGNED NULL,

  -- housekeeping
  first_seen_at   DATETIME,
  last_synced_at  DATETIME,
  missing_since   DATETIME NULL,          -- ha eltűnt a MTMT-listából

  UNIQUE KEY uniq_mtid (mtid),
  KEY idx_status_year (status, published_year),
  KEY idx_year (published_year),
  KEY idx_profile (query_profile_id)
) {charset_collate};
```

A kutatócsoport **nem** oszlop itt, hanem taxonómia-hozzárendelés (lásd 7), hogy aloldal + szűrő olcsó legyen.

### 4.2 `wp_jkk_mtmt_query_profiles`

```sql
CREATE TABLE {prefix}jkk_mtmt_query_profiles (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label         VARCHAR(255),       -- pl. "Autonóm járművek kutatócsoport"
  cond_json     LONGTEXT,           -- strukturált szűrőfeltételek (lásd 5.2)
  default_group_term_id BIGINT UNSIGNED NULL, -- opcionális előbesorolás
  enabled       TINYINT(1) DEFAULT 1,
  last_run_at   DATETIME NULL,
  last_max_mtid BIGINT UNSIGNED NULL
);
```

### 4.3 Sync-szabály (kritikus)

Upsert `mtid` kulcson:
- **új `mtid`** → beszúrás, `status='pending'`.
- **létező `mtid`** → csak az MTMT-forrású mezők frissülnek; a kézi mezők (`status`, `thumbnail_id`, `funding_override`, `project_*`, taxonómia) **érintetlenek**.
- **eltűnt `mtid`** → ne törölj, állítsd `missing_since`-t; admin dönthet.
- `rejected` rekord `mtid`-je marad `rejected`; a sync nem húzza vissza `pending`-be. Legyen „Elutasítás visszavonása" művelet.

---

## 5. MTMT API integráció

### 5.1 Alap

- Base: `https://m2.mtmt.hu/api/<objektumtípus>`
- Publikus adatokra kulcs nem kell. `format=json`.
- HTTP-t **mindig** `wp_remote_get()`-tel, saját `User-Agent`-tel, `timeout` ~20s, retry/backoff 429/5xx-re, és **oldalak közti késleltetés** (~0.5–1 s), hogy ne terheld a közös infrastruktúrát.

### 5.2 `cond` szűrők

Formátum: `cond=mező;operátor;érték`. Több `cond` = ÉS. URL-kódolás kötelező (`;`→`%3B`, `,`→`%2C` a sortnál). Operátorok: `eq`, `ne`, `in` (vesszős lista), `any`, `gt`, `ge`, `lt`, `le`, `sw`.

A `cond_json` a profilban strukturáltan tárolja ezeket, a kliens onnan építi az URL-t (ne stringet tárolj, hanem `[{field, op, value}]` listát).

### 5.3 Tipikus hívások (élő teszttel megerősítve, 2026-08)

**FONTOS:** a szerző-szűrő mezőneve **`authors`**, NEM `authors.mtid`. Ezt egy éles teszt igazolta: az `authors.institutes.mtid;eq;…` **csendben figyelmen kívül maradt** és a teljes adatbázist adta vissza (`totalElements` ~5000, becsült 11M). Az MTMT az ismeretlen `cond`-ot nem hibázza el, hanem IGNORÁLJA — ezért mindig ellenőrizd a `paging.totalElements`-t, hogy tényleg szűkült-e a találat.

Szerző összes közleménye (megerősítve működik), csak tudományos, évre csoportosítva:
```
https://m2.mtmt.hu/api/publication?cond=authors%3Beq%3B<AUTHOR_MTID>&cond=category.mtid%3Beq%3B1&groupBy=publishedYear&sort=publishedYear%2Cdesc&sort=firstAuthor%2Casc&size=100&format=json&labelLang=hun
```
- `cond=authors;eq;<mtid>` vagy `cond=authors;in;<mtid1>,<mtid2>` — szerző(k) szerint.
- `cond=category.mtid;eq;1` — csak „Tudományos".
- `groupBy=publishedYear` — az MTMT natívan tud évre csoportosítani (jól jön a megjelenítéshez).
- Csak saját közlemény (nem idéző rekord): `cond=core;eq;true` (lásd lentebb).

Inkrementális (csak új, a tárolt max fölött):
```
...?cond=authors%3Beq%3B<AUTHOR_MTID>&cond=mtid%3Bgt%3B<LAST_MAX>&sort=mtid%2Casc&size=100&format=json
```
Szerző mtid feloldás névből (admin autocomplete):
```
https://m2.mtmt.hu/api/author?cond=label;any;<szó>&size=50&depth=0&sort=familyName,asc&labelLang=hun&format=json
```
BibTeX / RIS export **közvetlenül az MTMT-ből** (a 9.2 opcionális BibTeX-hez — nem kell magunknak generálni):
```
...?cond=authors%3Beq%3B<AUTHOR_MTID>&export=1&exportFormat=BIBTEX
...?cond=...&export=1&exportFormat=RIS_BIBL
```

**`core` vs `citation`:** a publication-objektumon `core:true` = az intézmény/szerző saját (forrás) közleménye; `citation:true, core:false` = idéző rekord (valaki más műve, ami hivatkozik). A publikus listához jellemzően a **`core:true`** kell. A gridben ez a „Forrás" vs „Idéző" jelölés.

**Sync-stratégia:** elsődleges a **teljes lekérés + `mtid`-diff**; az inkrementális `mtid;gt` csak optimalizáció. Lapozz `page`-dzsel; MINDIG ellenőrizd `paging.totalElements`-t és `paging.last`-ot.

### 5.3b Intézményre szűkítés — SZE (NYITOTT, Fázis 0-ban lezárandó)

A megrendelő a **Széchenyi István Egyetem** egészét adta scope-nak (gui: `sel=institutes257`), de „nem kell minden, csak szelektálva". A teljes egyetem több tízezer rekord + zaj, ezért **NE** a teljes intézményt húzd. Két járható út, prioritási sorrendben:

1. **Ajánlott: profilok szerző-mtid-k (és/vagy al-intézmény/tanszék-mtid-k) szerint.** A `cond=authors;in;<mtid-lista>` megerősítetten szűr. Egy kurált SZE-szerzőlista pontosan a „szelektálva" munkafolyamatot adja, sane mennyiséggel. A kutatócsoportokhoz amúgy is kézzel sorolunk (§7), így a profil = egy csoport szerzői.
2. **Intézményi cond — helyes utat élesben kell megtalálni.** A `257` valószínűleg NEM a jó intézményi mtid (a valódi mtid-k nagyobbak), és a mezőút sem `authors.institutes.mtid`. Fázis 0-ban:
   - Oldd fel az SZE valódi intézményi mtid-jét: `GET /api/institute?cond=label;any;Széchenyi&depth=0&size=10&labelLang=hun&format=json`.
   - Próbáld ki a szűkítést, és **verifikáld, hogy `totalElements` reális számra esik** (nem 5000/11M): jelöltek `cond=institutes;in;<mtid>`, `cond=authors.institutes;in;<mtid>`. Amelyik szűkít, az a jó. Ha egyik sem, maradj az 1. útnál.

### 5.4 Szerzőnevek

A cél **tiszta** névsor: pl. „Gergő Ignéczi, Roland Tóth, Ernő Horváth, and Krisztián Nyilas". MEGERŐSÍTVE: a tiszta nevek az **`authorships[]`** tömbben vannak (`familyName`, `givenName`, `listPosition`, `first`, `last`, `corresponding`), affiliáció nélkül — ezekből építs, NE a bőbeszédű `label`-ből (az affiliációt is tartalmaz). Sorrend: `listPosition`. A megrendelői „…and X" formátumhoz az utolsó szerző elé tegyél „and"-et. A `corresponding:true` (levelező szerző) a ✉ jel forrása.

### 5.5 SJR-negyed (Best Q) — MEGERŐSÍTVE: a közlemény-objektumban van

Élő teszt szerint az SJR **közvetlenül a publication-objektumon** van, nincs szükség külön journal-lekérésre:
- `ratings[]` tömb, ahol `otype: "SjrRating"`, mezők: `ranking` ("Q1"/"Q2"/"D1"…), `label` ("sjr:Q1 (2027) Scopus - Medicine (miscellaneous) …"), `calculation`, `subject.label`, az évvel a labelben.
- Kényelmi mező: **`ratingsForSort`** = a rendezéshez használt kvartilis (pl. "Q1", "D1"). Ez jó gyors forrás a badge-hez.
- Több `SjrRating` is lehet (több tudományterület) → a **Best Q**-hoz vedd a legjobbat (D1 > Q1 > Q2 > Q3 > Q4). A `ratings[]`-ben lehet `MtaRating` is (MTA doktori bizottsági besorolás) — azt ne keverd az SJR-rel.
- Mapper: `sjr_quartile` = a legjobb `SjrRating.ranking` (vagy `ratingsForSort`, ha csak egy van).

### 5.6 Felderítés — MÁR ELVÉGEZVE, lásd `docs/field-map.md`

Egy éles próbalekérés megtörtént; a valós mezőtérkép a `docs/field-map.md`-ben van, a megerősített kulcsokkal. **Ezt tekintsd a mapper igazságforrásának.** Ami még nyitott és Fázis 0-ban lezárandó: (a) az SZE intézményi szűkítés helyes útja (§5.3b), (b) a **támogatás/projektazonosító** (NKFIH grant) API-mezője — az alap `depth`-en NEM jött vissza, magasabb `depth` vagy külön `grants/projects` reláció kell; ha nem elérhető publikusan, marad a kézi bevitel (§8.2).

### 5.7 MTMT link

Az azonosítóból előállítható: `https://m2.mtmt.hu/api/publication/<mtid>` (adat), illetve a humán nézethez a gui-URL. Ne tárold külön, generáld.

---

## 6. Ütemezés

- Regisztrálj `jkk_mtmt_weekly` cron-eseményt (egyedi `weekly` intervallum) és egy `wp jkk-mtmt sync` **WP-CLI** parancsot ugyanarra a logikára.
- **Kapcsold ki a látogató-vezérelt WP-cront a productionben** (`DISABLE_WP_CRON`), és valódi rendszer-cron/Swarm cron-service hívja a WP-CLI parancsot — determinisztikus heti futásért. Ezt dokumentáld a README-ben, ne a kódban kényszerítsd.
- A sync legyen **kötegelt és folytatható**: profilonként + oldalanként, állapotmentéssel, timeout-biztosan.
- Minden futásról napló (mikor, hány új/frissült/hiányzó/hibás), adminban megtekinthető.

---

## 7. Kutatócsoport-taxonómia + szakmai aloldalak

- Regisztrálj egy custom taxonómiát: `jkk_research_group` (pl. Autonóm, Robotika, Elektronika…). Mivel a publikáció nem post, a taxonómiát a saját táblához kötöd (pivot tábla `wp_jkk_mtmt_pub_group (pub_id, term_id)` VAGY `wp_set_object_terms` egy proxy objektumra — válaszd a pivot táblát az egyszerűségért és teljesítményért).
- A besorolás **kézi**, a moderáció része, és **külön jogosultsághoz** kötött (lásd 8).
- Minden csoporthoz tartozhat egy „szakmai aloldal": az Elementor widget kap egy **„csoportra szűkítés"** beállítást (term kiválasztása), így az aloldalon csak az adott csoport `approved` tételei jelennek meg.

---

## 8. Admin: moderáció + gazdagítás + jogosultságok

### 8.1 Lista (`WP_List_Table`)

- Oszlopok: indexkép (thumbnail), cím, szerzők, forrás, év, típus, SJR (badge), MTMT-státusz, DOI-link, „MTMT" link, kutatócsoport, státusz.
- Sor-műveletek: **Szerkesztés/Gazdagítás**, Jóváhagyás, Elutasítás, Elutasítás visszavonása.
- Tömeges: jóváhagyás/elutasítás.
- Szűrők felül: státusz, év, profil, kutatócsoport.
- Menü-badge: `pending` darabszám piros buborékban.

### 8.2 Szerkesztő/gazdagító űrlap

A jóváhagyás nem csak státuszváltás — nyisson egy űrlapot, ahol beállítható:
- **Indexkép** (Media Library; kiadó-logó vagy egyedi kép),
- **Kutatócsoport** (taxonómia; több is lehet),
- **Támogatás felülbírálás** (`funding_override`),
- **Projektazonosító(k) + „Ellenőrizve" pipa** (`project_verified`), ami rögzíti `verified_by/at`-et.

A kézi mezők mentése **nem** indít MTMT-hívást, és a sync sosem írja felül őket.

### 8.3 Jogosultságok (kétszintű)

- `jkk_mtmt_moderate` — jóváhagyás/elutasítás, alap szerkesztés, indexkép. (Rita, Bogi, Csikor Dani, Koteczki Réka, Horváth Ernő.)
- `jkk_mtmt_classify` — kutatócsoport-besorolás **és** projektazonosító-ellenőrzés. (Külön kör; náluk még egyeztetés alatt → tedd konfigurálhatóvá role→capability mappinggel.)

Minden admin-műveletnél: **capability check + nonce**. Semmilyen művelet ne fusson ezek nélkül.

---

## 9. Elementor widget (frontend)

### 9.1 Alap (Fázis 1)

- Csak `status='approved'`, a saját táblából.
- **Évenkénti csoportosítás**, csökkenő sorrend; év-szekciók (összecsukható vagy fejléces).
- **Kártya-elrendezés** a megrendelői vizuál szerint: indexkép | cím | szerzők | forrás, `[Qx]`, év | támogatás-sor (a `funding_override` elsőbbséggel).
- **Keresés** (cím/szerző/forrás) — kliensoldali azonnali szűrés; nagy listánál AJAX + lapozás.
- **Widget-beállítások (Elementor Controls):** megjelenítendő profil(ok) és/vagy **kutatócsoportra szűkítés**, alap rendezés/csoportosítás, keresőmező be/ki, év-szekciók alapállapota, tételszám/oldal, hivatkozás-stílus (kompakt/teljes), DOI-link és SJR-badge megjelenítése.
- Teljesítmény: object cache / transient a lekérdezett listára; escape-elj minden MTMT-eredetű szöveget renderkor.

### 9.2 Opcionális (Fázis 2 — külön, csak jóváhagyás után)

- **SJR-szűrő** (D1/Q1–Q4) a frontenden.
- **Haladó szűrők** a BME-példa mintájára: szerző / típus / folyóirat / SJR / (Norvég-szint), „reverse order", találatszám.
- **Idézettség-badge: Dimensions** (DOI-alapú, `badge.dimensions.ai` beágyazott script). A megrendelő ezt preferálja az MTMT idézésszámmal szemben (önfrissülő, pontosabb). Lazy-load, és rövid adatvédelmi jelzés (külső script a látogató böngészőjében).
- **BibTeX lenyíló**: kimásolható BibTeX a strukturált mezőkből generálva (kulcs pl. `vezetéknévévcím`).

Ezeket **ne** kezdd, amíg a Fázis 1 el nem készült és jóvá nem hagyták.

---

## 10. GitHub + PUC frissítés

### 10.1 Repo-elrendezés

```
jkk-mtmt-publications/
├─ jkk-mtmt-publications.php     # főfájl (plugin header + bootstrap + PUC init)
├─ readme.txt                    # WP.org formátum (PUC "View details" + verzió)
├─ CLAUDE.md                     # ez a fájl
├─ .gitignore
├─ languages/
├─ includes/                     # api-client, sync, repository, mapper, cron, cli
├─ admin/                        # list-table, edit-form, profiles, capabilities
├─ elementor/                    # widget
├─ assets/                       # js, css
├─ lib/
│  └─ plugin-update-checker/     # PUC v5 bevendorolva (giten fent → almodul VAGY bemásolva)
└─ docs/
   └─ field-map.md               # az 5.6 élből felderített mezőtérkép
```

### 10.2 Plugin header (a főfájlban)

```php
/**
 * Plugin Name: JKK MTMT Publications
 * Description: MTMT-alapú publikációs lista jóváhagyással és Elementor megjelenítéssel.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Text Domain: jkk-mtmt-publications
 */
```

A **`Version` fejlécet minden kiadásnál növeld** (SemVer), és egyezzen a GitHub release taggel — a PUC ebből tudja, van-e frissítés.

### 10.3 PUC v5 inicializálás

```php
require __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$jkk_mtmt_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/<user>/jkk-mtmt-publications/',
    __FILE__,
    'jkk-mtmt-publications'
);
// A stabil kiadást tartalmazó ág:
$jkk_mtmt_update_checker->setBranch('main');
// Ha privát a repo:
// $jkk_mtmt_update_checker->setAuthentication('<github-token>'); // NE kerüljön a repóba!
// GitHub Releases használata esetén, ha buildelt zip-et csatolsz:
// $jkk_mtmt_update_checker->getVcsApi()->enableReleaseAssets();
```

- **Kiadási workflow:** verzió bump a headerben + `readme.txt` → commit → GitHub **Release** (tag = verzió). Build lépés nincs, így a GitHub által generált forrás-zip elég; a PUC kezeli a mappa-wrappert. Ha később lesz build, csatolt asset + `enableReleaseAssets()`.
- **Privát repo / token:** a tokent **soha** ne commitold. Konstansként (`wp-config.php`) vagy szűrőn keresztül add át. Publikus repónál nem kell token.
- Az éles oldalak innen kapják a frissítést (te „giten fent lévő PUC-cal frissíted a site-okat"): a WP frissítő felületén megjelenik az új verzió, ahogy a GitHub release kikerül.

### 10.4 `.gitignore`

Node/IDE/OS szemét, `*.log`, lokális env; a `lib/plugin-update-checker/` **maradjon** verziózva (kell a futáshoz), kivéve ha git-almodulként húzod be.

---

## 11. Biztonság, minőség, teljesítmény (kötelező)

- **Minden** admin/AJAX művelet: `current_user_can()` + `wp_verify_nonce()`.
- **Minden** DB-hívás `$wpdb->prepare()`-rel. Kézzel összefűzött SQL tilos.
- Input sanitizálás (`sanitize_text_field`, `absint`, `esc_url_raw`), output escape (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`). Az MTMT-adatot renderkor mindig escape-eld.
- REST/AJAX végpontokon `permission_callback`.
- MTMT-hívások: hibatűrés (üres válasz, 4xx/5xx, timeout), backoff, rate limit, cache.
- Aktiváláskor tábla-migráció (`dbDelta`), deaktiváláskor cron-unschedule; **ne** dobj adatot deaktiváláskor (csak explicit uninstall.php-ban, megerősítéssel).
- Kód WPCS szerint; PHPDoc a publikus metódusokon; osztályok kis, tesztelhető egységekre bontva (api-client / mapper / repository / sync szétválasztva).

---

## 12. Fázisok és elfogadási kritériumok

**Fázis 0 — Felderítés.** Élő minta-fetch (5.6), `docs/field-map.md` kész. *Kész, ha:* a valós JSON-ból dokumentált mezőtérkép van, benne a tiszta szerzőnév és az SJR feloldási útja.

**Fázis 1 — Ingest mag.** API-kliens + mapper + repository + upsert/diff, WP-CLI `sync`. *Kész, ha:* egy profilra `wp jkk-mtmt sync` feltölti a táblát `pending`-be, újrafuttatva nem duplikál és nem írja felül a (még nem létező) kézi mezőket, lapozás működik.

**Fázis 2 — Cron.** Heti ütemezés + napló. *Kész, ha:* ütemezetten fut, a napló látszik adminban, `DISABLE_WP_CRON`-os rendszer-cronból is hívható.

**Fázis 3 — Moderáció + gazdagítás + jogosultságok.** List table, szerkesztő űrlap (indexkép, kutatócsoport, támogatás-override, projektellenőrzés), két capability. *Kész, ha:* jóváhagyás/elutasítás/visszavonás megy, a kézi mezők syncnél túlélnek, a jogosultságok elkülönülnek, nonce+capability mindenhol.

**Fázis 4 — Taxonómia + aloldalak.** `jkk_research_group` + pivot + widget csoport-szűkítés. *Kész, ha:* egy csoport aloldalán csak az adott csoport tételei jönnek.

**Fázis 5 — Elementor widget (alap).** Évekre bontás, keresés, kártya-elrendezés, SJR-badge, DOI-link, beállítások. *Kész, ha:* csak `approved` jelenik meg, évekre bontva, keresés működik, csak a saját táblát hívja.

**Fázis 6 — GitHub + PUC.** Header/verzió, readme.txt, PUC init, első release. *Kész, ha:* egy teszt-oldal a GitHub release-ből frissülést lát a PUC-on át.

**Fázis 7 (opcionális) — Fázis 9.2 nice-to-have-ek**, csak külön jóváhagyással: Dimensions badge, BibTeX lenyíló, haladó szűrők, Norvég-szint.

---

## 13. Amit a Claude Code kérdezzen meg / ne tippeljen

- A pontos `mtid`(-ek) / intézmény-id(-k) az első profilhoz (a felderítő fetch-hez).
- A GitHub repo `<user>/<repo>` és hogy publikus vagy privát (token kell-e).
- Az SJR és Norvég-szint pontos API-útja — élesből, ne találd ki.
- Role→capability leképzés (kik kapják a `moderate`-et és a `classify`-t).
- Ha egy mező nem jön az API-ból, azt **jelezd** (mi hiányzik), ne pótold kitalált adattal.

---

*Ez a brief a v0.1 rendszertervből + a megrendelői követelménydoksiból + a megbeszélt deltákból állt össze. Ha a felderítő fetch (Fázis 0) ellentmond bárminek itt, a valós API nyer — frissítsd ezt a fájlt és a `docs/field-map.md`-t.*
