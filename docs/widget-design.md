# Widget design-referencia (Fázis 5-höz)

Képes referencia a megrendelőtől (2026-08), TUM-stílusú kutatócsoport-publikációs
oldal mintájára, + egy megbeszélés (2026-08, lásd CLAUDE.md §14 és docs/decisions.md
#16-21) ami pontosította. **NEM implementálandó Fázis 1/2/3/4-ben** — ez a Fázis 5
(Elementor widget) célja, itt rögzítve, hogy ne vesszen el a phase-ek között.

Fontos: a séma (§4.1) TOVÁBBRA IS tárolja mind a ~21 MTMT-forrású mezőt (lásd az
Artifact-ban publikált "MTMT Adatkatalógus"-t) — a lenti lista a **widget-kártyán
ténylegesen megjelenő** mezőket rögzíti, ami ennek egy szűkebb, megbeszélésen
véglegesített részhalmaza. A nem listázott mezők (volume/issue/page_range, issn,
external_ids nyersen, funding_text, mtmt_state, mtid, other_url) megmaradnak az
adatbázisban, csak nincsenek a widget-kártyán megjelenítve — moderációhoz,
exporthoz, jövőbeli widget-verzióhoz elérhetők maradnak.

## Elrendezés

- Header: eyebrow ("PUBLIKÁCIÓK") + H1 ("Lektorált publikációk") + egy soros alcím-leírás.
- **Év-fülek (tab-sáv)**, NEM accordion/összecsukható szekció: 2026 | 2025 | 2024 | 2023 | 2022,
  a kattintott év aktív, csak az adott év tételei listáznak alatta. Konkrétabb
  megvalósítás a §9.1 eredeti "év-szekciók, összecsukható vagy fejléces"
  javaslatához képest — a megbeszélésen a "dátum lapozóval" megfogalmazás (§14/10,
  "A" widget) ezt erősíti meg, valószínűleg ez a végleges irány.
- Soronkénti kártya-lista (nem grid): bal oldalt előnézeti kép (indexkép VAGY
  §14/8 placeholder+rágenerált cím, ha nincs feltöltve), jobbra tartalom, jobb
  felül szín-kódolt típus-badge.
- **A teljes kártya/sáv kattintható** (RESOLVED, §14/12) — nem külön gomb dönti el
  a célt: `https://doi.org/<doi>`, ha van DOI; ha nincs és az MTMT-link
  megjelenítése engedélyezve (§9.1 kapcsoló), akkor
  `https://m2.mtmt.hu/gui2/?mode=browse&params=publication;<mtid>` (VERIFIKÁLVA
  élesben, lásd decisions.md #17 — NEM az `/api/publication/<mtid>` végpont, az JSON-t ad).
- Lapozás alul (számozott oldalak + prev/next), NEM "load more"/infinite scroll.

## Widget-kártya mezők (VÉGLEGESÍTVE a megbeszélésen, 2026-08)

A megrendelő explicit megerősítette: a widget(ek) ÖSSZESEN ezt a mezőkészletet
használják, ezen felül semmit a kártyán.

| UI elem | Séma-mező | Megjegyzés |
|---|---|---|
| Előnézeti kép | `thumbnail_id`, üresnél §14/8 placeholder+cím | |
| Cím | `title` | |
| Szerzők | `authors_text` | **NYITOTT**: a mockupon rövidített forma volt ("S. Goblirsch, M. Piccinini"), a séma teljes névvel tárol (§5.4). A megbeszélés ezt NEM tisztázta — marad nyitott kérdés, ld. lent. |
| Szakmai terület | ÚJ mező, §14/1 | csak akkor jelenik meg, ha a terület-funkció be van kapcsolva a beállításokban |
| Forrás (folyóirat / kötet) | `source_title` | a mockupon kék linkként; kötet/füzet/oldaltartomány NEM külön kártya-mező, csak a `source_title` |
| DOI | `doi` | a teljes kártya linkcélja is ebből épül, nem csak szövegként jelenik meg |
| Kiadványtípus | `pub_type` | badge, színkódolva |
| Megjelenés éve | `published_year` | (ez alapján is megy az év-fülek csoportosítása) |
| SJR-negyed | `sjr_quartile` | badge |
| Egyéb azonosítós logó-gombok | `external_ids` | RESOLVED (§14/3,4) — PDF/Kód/Videó helyett: minden `external_ids`-beli forrásnak (WoS/Scopus/PubMed/SZTAKI…) egy logó-ikon, linkelve a `realUrl`-re. Logó-asset-eket be kell szerezni Fázis 5-ben. |

**Explicit NEM widget-kártya mező** (tárolva marad, csak nem jelenik meg itt):
`volume`, `issue`, `page_range`, `issn`, `norway_level`, `funding_text` (amúgy is
mindig üres, ld. field-map.md), `mtmt_state`, `mtid` (csak a linkgeneráláshoz kell
belül), `other_url`.

## Két widget (§14/10, §14/11)

- **„A" — összesítő központi widget**: minden jóváhagyott tétel (nem csak kiemelt),
  a fenti kártya-mezőkkel, év-fülekkel lapozva. Szűrők: szakmai terület szerinti
  lenyíló (csak ha a terület-funkció be van kapcsolva) + kereső.
- **„B" — terület-aloldal widget**: csak `is_featured=1` tételek, EGY konkrét
  területre/profilra szűkítve (Elementor widget-beállításban választva ki). Csak
  akkor jelenik meg az Elementor widget-listában, ha a "kiemelt cikk" opció be van
  kapcsolva a beállításokban (ez a §14/1 terület-toggle-tól FÜGGETLEN, saját kapcsoló).

## Nyitott kérdések Fázis 5 előtt

1. ~~Kell-e a Kód/Videó linkekhez új kézi mező?~~ **RESOLVED**: nem kellenek, helyettük egyéb azonosítós logó-gombok.
2. **Szerzőnév a widgeten: rövidített (monogram) vagy teljes forma?** — még mindig nyitott, a megbeszélés nem tisztázta.
3. ~~A körkörös nyíl-gomb hova vezet?~~ **RESOLVED**: nem számít a gomb pozíciója, a teljes kártya DOI-ra (vagy MTMT gui-linkre) vezet.
4. Év-fülek (tab) a végleges megoldás — a "dátum lapozó" megfogalmazás ezt erősíti, de explicit visszaigazolás nem volt.
5. Placeholder-kép generálása: CSS-overlay vs. szerver-oldali (GD/Imagick) képgenerálás — lásd CLAUDE.md §14/8, még nem eldöntött.
