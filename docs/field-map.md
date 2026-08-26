# MTMT → plugin mezőtérkép (élő API-teszt alapján, 2026-08)

Forrás: `GET https://m2.mtmt.hu/api/publication?...&format=json` valós válasza (JournalArticle és BookChapter rekordok), kiegészítve `GET /api/institute` élő teszttel. A kulcsnevek a **tényleges** JSON-ból származnak, nem doksiból. Ahol „VERIFY" szerepel, azt Fázis 0-ban élesben kell zárni.

## Kérés-tudnivalók

- A válasz `paging` objektumot ad: `totalElements`, `totalPages`, `size`, `number`, `last`, `first`, `sort[]`. **Mindig ellenőrizd `totalElements`-t** — ha pontosan `5000`, az valószínűleg egy lapozási plafon, NEM a valós darabszám (nézd a `totalEstimatedElements`-et is, az a becsült valós összeg). Ha `totalElements`/`totalEstimatedElements` ~5000+ / becsült 11M-hez közeli, a `cond` nem szűrt (ismeretlen mezőt az MTMT csendben ignorál, hibaüzenet nélkül).
- A rekordok a `content[]` tömbben.
- **`depth` KRITIKUS a mapperhez** — VERIFY-lezárva: `depth=0` mellett a publication-objektumból **hiányzik** `authorships[]` (így `firstAuthor` is), `ratings[]` (SJR!), `directInstitutesForSort`, `abstractText`. Ezek csak `depth=1`-től jelennek meg. `depth=2` és `depth=3` között **nincs érdemi különbség** (ugyanazon rekordon bájtra azonos válasz jött — a szerver gyakorlatilag `depth=2`-nél levág). **→ a syncnek mindig `depth=1`-gyel (vagy `depth=2`-vel, ha esetleg egy jövőbeli mező mélyebbre kerül) kell hívnia a publication endpointot, `depth=0` NEM elég.**
- **Ékezet-csapda a `label;any;...` kereséseknél (institute ÉS várhatóan author endpoint is)** — VERIFY-lezárva: az index maga ékezetes adatot tárol, de az `any` operátor **csak ékezet nélküli (ASCII-re foldolt) query-tokent** fogad el. Pl. `cond=label;any;Győr` → 0 találat; `cond=label;any;Gyor` → helyes találatok ékezetes label-ekkel. **A CLAUDE.md 5.3 admin-autocomplete-nél (szerző-mtid feloldás névből) a beviteli szót ékezetlenítve kell a `cond`-ba tenni**, különben csendben (hibaüzenet nélkül) 0 találatot ad `totalEstimatedElements:1`-gyel (megtévesztő — úgy tűnik, mintha lenne 1 becsült találat, de a `content[]` üres).
- Megerősített `cond` mezők: `authors` (szerző-mtid; `eq`/`in`), `category.mtid` (1 = Tudományos), `mtid` (`gt` inkrementálishoz), `core` (bool), `citations.related` (idézők), `directInstitutes` / `directInstitutes.mtid` (intézményi szűrés — lásd lentebb).
- Natív csoportosítás: `groupBy=publishedYear`. Natív export: `export=1&exportFormat=BIBTEX|RIS_BIBL`.
- MIME a válaszban: `application/vnd.mtmt2-1.0+json` — az API-verzió itt kódolt.

## Publication-objektum → tábla-mezők

| JSON-kulcs (valós) | Példaérték | Tábla-oszlop | Megjegyzés |
|---|---|---|---|
| `mtid` | 37424671 | `mtid` | egyedi kulcs |
| `otype` | "JournalArticle" / "BookChapter" / "Book" | (típuslogika) | folyóiratcikk vs. könyvrészlet/konferencia |
| `status` | "VALIDATED" / "APPROVED" / "ADMIN_APPROVED" | `mtmt_state` | Egyeztetett / Nyilvános / Admin láttamozott |
| `published` | true | (szűrő) | publikus-e |
| `core` | true/false | (szűrő) | true = saját közlemény; false+`citation:true` = idéző rekord |
| `title` | "Scene-Specific State-Space…" | `title` | |
| `authorships[]` | lásd lentebb | `authors_text`, `authors_raw` | tiszta nevek innen — **csak `depth>=1`-nél jön** |
| `firstAuthor` | "Agg, Áron Dávid" | (rendezés) | első szerző stringként — **csak `depth>=1`-nél jön** |
| `type.label` | "Folyóiratcikk" / "Egyéb konferenciaközlemény" | `pub_type` | `type.mtid` is van |
| `subType.name` / `.nameEng` | "Szakcikk" / "Article" | `pub_category` | „Szakcikk (Folyóiratcikk)" a `.label` |
| `category.label` | "Tudományos" | `pub_character` | `category.mtid` 1=Tudományos |
| `languages[].label` | "Angol" (`nameEng` "English") | `language` | tömb |
| `journal.label` | "SAFETY SCIENCE 0925-7535 1879-1042" | `source_title` | csak JournalArticle-nél |
| `journal.pIssn` / `journal.eIssn` | "0925-7535" / "1879-1042" | `issn` | print/electronic ISSN |
| `book.label` / `book.title` | "Proceedings of the 5th…" | `source_title` | BookChapter „In:" gazdamű |
| `volume` | "201" | `volume` | Kötet |
| `issue` | "1" | `issue` | Füzet (nem mindig van) |
| `firstPage` / `lastPage` | "316" / "337" | `page_range` | `pageLength` is van |
| `internalId` | "107243" | `page_range` | cikkszám (Paper:) ha nincs oldal |
| `publishedYear` | 2027 | `published_year` | FIGYELEM: lehet jövőbeli év (2027/2029) — évekre bontásnál ezt használd |
| `sourceYear` | 2026 | (opcionális) | tényleges forrásév |
| `identifiers[]` | lásd lentebb | `doi`, `external_ids`, `other_url` | DOI/WoS/Scopus/Egyéb URL |
| `ratings[]` (SjrRating) | `ranking`:"Q1"/"D1" | `sjr_quartile` | Best Q = legjobb; lásd lentebb — **csak `depth>=1`-nél jön** |
| `ratingsForSort` | "Q1" | `sjr_quartile` | kényelmi mező, ha csak egy SJR van |
| `citationCount` | 0 | (opcionális) | MTMT idézésszám — a megjelenítéshez a doksi Dimensions-t kér (DOI-alapú), NE ezt |
| `abstractText` | "Polyethylene microplastics…" | (opcionális) | nem mindig jön; **csak `depth>=1`-nél** |
| `keywords[].label` | "Deep learning" | (opcionális) | tömb; nem minden rekordon van jelen (üres mezőt az API kihagy) |
| `subjects[].label` | "Egyéb műszaki tudományok…" | (opcionális) | tudományterületi besorolás |
| `oaType` / `oaFree` / `oaLink` | "GOLD" / false | (opcionális) | open access státusz |
| `directInstitutesForSort` | "Alkalmazott és Környezeti Kémiai Tanszék (SZTE / TTIK / KI); …; Széchenyi István Egyetem" | (szűrés-segéd / kutatócsoport-előbesorolás támpont) | affiliációk rövid, „;"-tal tagolt stringje — **csak `depth>=1`-nél jön**; a strukturált `directInstitutes[]` objektumtömb NEM jelenik meg a válaszban `depth=3`-ig sem, csak ez az összefűzött sortváltozat |
| `link` | "/api/publication/37424671" | (generálható) | MTMT API-link |
| `label` | teljes formázott hivatkozás | (opcionális) | kész idézet-string |
| `template` / `template2` | előrenderelt HTML | NE használd | saját megjelenítést építünk |
| **támogatás / grant** | — | `funding_text` | **VERIFY LEZÁRVA — NINCS a publikus API-ban.** `depth=0/1/2/3`-on végigmenve a `publication/<mtid>` teljes kulcslistájában (170+ egyedi kulcs) nincs `grant`/`funding`/`project`/`otka`/`nkfih`/`támogat` gyökű mező, és `depth=2` ⇔ `depth=3` bájtra azonos válasz (a mélység 2-nél levág). **Döntés: `funding_text` marad üresen a syncnél, kizárólag `funding_override` (kézi bevitel) tölti.** |
| `raw_json` | teljes objektum | `raw_json` | mindent tegyél el ide |

## `authorships[]` (tiszta nevek)

Mezők rekordonként: `familyName`, `givenName`, `listPosition` (sorrend), `first` (bool), `last` (bool), `corresponding` (bool → ✉), `type.label` ("Szerző"/"Szerkesztő"). Néha van beágyazott `author.mtid` (ha az adott szerzőnek van MTMT-profilja).

**`authors_text` építése:** `listPosition` szerint rendezve `"<givenName> <familyName>"`, vesszővel elválasztva, az utolsó elé „and". A `familyName, givenName` sorrend is elérhető, ha úgy kell.

## `identifiers[]` (külső azonosítók)

Rekordonként: `idValue`, `realUrl` (kész link!), `source.name` ("DOI"/"WoS"/"Scopus"/"Egyéb URL"), `source.linkPattern`. Leképzés:
- `source.name == "DOI"` → `doi` = `idValue` (link: `realUrl`, mintája `https://doi.org/@@@`).
- `"WoS"`, `"Scopus"`, `"SZTAKI"` → `external_ids` JSON.
- `"Egyéb URL"` → `other_url` = `idValue`.

A doksi szerint **a DOI és az Egyéb URL a lényeges** — ezeket emeld ki, a többit tedd `external_ids`-be.

## `ratings[]` → SJR (Best Q)

Szűrd az `otype == "SjrRating"` elemeket. Mezők: `ranking` ("D1"/"Q1"/"Q2"/"Q3"/"Q4"), `label` (tartalmazza az évet és a Scopus-területet), `subject.label`, `calculation`. Több területnél több elem lehet → **Best Q = a legjobb** (D1 > Q1 > Q2 > Q3 > Q4). Ha csak gyors érték kell: `ratingsForSort`.
Az `otype == "MtaRating"` külön dolog (MTA doktori bizottsági „A nemzetközi" stb.) — NE keverd az SJR-be, de eltárolhatod.
Az `otype == "PredatorRating"` a Norvég listás besorolás (`ratingType.code == "norveg"`, `val` = a norvég folyóirat-azonosító) — lásd lent, Norvég szint.

## SZE intézményi szűrés (VERIFY LEZÁRVA)

**Széchenyi István Egyetem valódi institute-mtid-je: `257`** (label: „Széchenyi István Egyetem SZE [2002-]"), feloldva:
```
GET /api/institute?cond=label;any;Szechenyi&depth=0&size=10&labelLang=hun&format=json
```
(ékezet nélkül — lásd az ékezet-csapdát fent). Ez megerősíti, hogy a korábban próbált `257` valóban a helyes mtid volt; a hiba a **`cond` mezőnevében** volt, nem az id-ben.

**A CLAUDE.md-ben szereplő `cond=institutes;in;<id>` NEM szűr érdemben** — teszt (`institutes;in;257`, ill. `institutes.mtid;in;257`): `totalElements` a lapozási plafonon (5000), `totalEstimatedElements` **32 000–46 000** körül — gyakorlatilag szűretlen, tehát ez a mező az intézmény teljes közvetett/történeti kapcsolathálóját (pl. munkatársak összes korábbi/másodlagos affiliációja láncolt közléseken át) fogja meg, nem a cikk tényleges affiliációját.

**A helyes mező: `directInstitutes` (vagy egyenértékűen `directInstitutes.mtid`), `in` vagy `eq` operátorral:**
```
GET /api/publication?cond=directInstitutes;in;257&size=100&page=1&format=json
```
Eredmény: `totalElements` **2943**, `totalEstimatedElements` **2100** — reális nagyságrend egyetlen magyar egyetem teljes MTMT-publikációs állományára. Mintarekordokon ellenőrizve: a `directInstitutesForSort` mező (csak `depth>=1`-nél jön) ténylegesen tartalmazza a „Széchenyi István Egyetem" affiliációt legalább egy társszerzőnél minden visszaadott találatban — a szűrés valós.

**Megjegyzés:** `directInstitutes;in;257` azokat a cikkeket is hozza, ahol az SZE csak **egy** a több társintézmény közül (pl. egy SZTE-fókuszú cikk, amin egy SZE-s társszerző is szerepel). Ez a kívánt viselkedés egy intézményi profilnál (bármelyik szerző SZE-affiliációja elég), de tudni kell róla: a lista NEM kizárólag "SZE-saját" cikkeket ad, hanem "van benne SZE-affiliáció" cikkeket.

**Több tanszék/kar egyszerre:** `directInstitutes;in;<id1>,<id2>` (vesszős lista, `in` operátor natívan támogatja — konzisztens az 5.2-es specifikációval).

**PONTOSÍTVA: a tényleges scope nem a teljes SZE (257), hanem a JKK.** A megrendelő adott egy pontosabb intézmény-mtid-et: `19662` = „Járműipari Kutatóközpont SZE JKK [2011-]" (`GET /api/institute/19662` → `otype:Institute`, `type.label:"Kutatóközpont"`, `parent[0]` = SZE-n belüli `DivisionContainment`, saját `publicationCount: 767`). Teszt: `cond=directInstitutes;in;19662&cond=core;eq;true` → `totalElements=767` — **bájtra egyezik** az intézmény-objektum saját `publicationCount` mezőjével. Ez a legpontosabb, zajmentes scope; a `257` (teljes SZE) csak akkor kellene, ha a megrendelő explicit egyetem-szintű listát kérne.

**Fontos architektúra-elv (nem csak ennél a telepítésnél):** SEM a `257`, SEM a `19662` nem kerül semmilyen PHP-kódba hardcode-olva. Az intézmény-mtid (vagy szerző-mtid-lista) kizárólag a `wp_jkk_mtmt_query_profiles.cond_json`-ban él, telepítésenként/site-onként beállítva — lásd „Query profil = dobozos scope-konfiguráció" lentebb.

**Döntés a profilokhoz:** intézményi profil esetén `cond_json` mezője `directInstitutes`, operátor `in`, érték `[257]` (bővíthető alintézmény-mtid-ekkel, ha kar/tanszék-szintű szűkítés kell — azokat ugyanígy `/api/institute?cond=label;any;...` kereséssel kell feloldani, ékezet nélkül). Ha egy adott profilnál mégsem elég pontos (pl. túl sok "csak egy társszerző SZE-s" találat jön be), a fallback a CLAUDE.md-ben is javasolt **szerző-mtid-alapú** profil (`cond=authors;eq;<szerző-mtid>`).

## Nyitott pontok

Az alábbi két pont a Fázis 0 eredeti "Nyitott pontok" listájából **le van zárva** ebben a felderítésben:

1. ~~SZE intézményi szűkítés~~ → **LEZÁRVA**, lásd „SZE intézményi szűrés" fent: `directInstitutes;in;257`.
2. ~~Támogatás/projektazonosító mezője~~ → **LEZÁRVA**, lásd a táblázat „támogatás / grant" sorát: nincs a publikus API-ban semmilyen depth-en, marad kézi bevitel (`funding_override`).

Megmaradó, még nyitott pont (Fázis 2-re, opcionális, nem blokkolja Fázis 1-et):

3. **Norvég szint** (Norway Level) mezője a szűrőhöz — a `ratings[]` tömbben `otype == "PredatorRating"` és `ratingType.code == "norveg"` alatt **látszik** egy `val` mező (pl. `"441941"`, ez láthatóan egy norvég folyóirat-azonosító, nem közvetlenül az 1/2-es szint), de a tényleges **szint-számjegy** (1 vagy 2) feloldásának API-útja még nincs megerősítve — ezt csak akkor kell lezárni, ha a Fázis 2 opcionális szűrőhöz ténylegesen kell.
