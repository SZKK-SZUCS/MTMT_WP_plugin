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

## 🔜 Fázis 1.5 — Utólagos kiegészítés a meglévő ingest-kódhoz

**KÓD KÉSZ + unit-tesztelve (WP-független stub-harnessben, 17/17 assertion zöld),
ÉLES WP-ellenőrzés MÉG NEM TÖRTÉNT.** Két pont a megbeszélésből közvetlenül a már megírt Fázis 1
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

## 🔜 Fázis 2 — Cron + napló + email + kézi szinkron

**KÓD KÉSZ, ág: `fazis-2-cron-log-email`, ÉLES ELLENŐRZÉS MÉG NEM TÖRTÉNT.**
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

## Fázis 3 — Moderáció + gazdagítás + jogosultságok

Eredeti scope (§8) + két új mező a megbeszélésből:

- `WP_List_Table` lista (indexkép, cím, szerzők, forrás, év, típus, SJR, MTMT-státusz,
  DOI-link, MTMT-link, kutatócsoport/terület, státusz), sor- és tömeges műveletek.
- Szerkesztő/gazdagító űrlap: indexkép, kutatócsoport, támogatás-override,
  projektazonosító + ellenőrizve pipa.
- **ÚJ: "Kiemelt cikk" checkbox** (§14/9, `is_featured` — már a sémában) a
  szerkesztő űrlapon, **saját be/ki plugin-beállítással** (§14/11) — ha ki van
  kapcsolva, a checkbox nem jelenik meg az űrlapon (ez a beállítás FÜGGETLEN a
  Fázis 4-es terület-toggle-től).
- Két capability (`mtmt_moderate`, `mtmt_classify`), role→capability mapping.
- *Előkészítés Fázis 4-hez:* az űrlapot úgy érdemes építeni, hogy a "szakmai
  terület" választó egy Fázis 4-ben behúzható, feltételesen megjelenő blokk
  legyen (a toggle és az adatmodell csak Fázis 4-ben készül el, de a form-layout
  már itt számoljon vele).

*Kész, ha:* jóváhagyás/elutasítás/visszavonás megy, kézi mezők (thumbnail,
funding_override, project_*, is_featured) syncnél túlélnek, jogosultságok
elkülönülnek, nonce+capability mindenhol, a kiemelt-cikk toggle ki/bekapcsolva
tényleg elrejti/mutatja a checkboxot.

## Fázis 4 — Taxonómia + aloldalak ("Szakmai terület")

Eredeti scope (§7), a megbeszélésen **megerősítve átnevezve** "Szakmai terület"-re
(docs/decisions.md #18) — NEM külön, második kategória-rendszer a kutatócsoport
mellett, ugyanaz a mechanizmus.

- `mtmt_research_group`-szerű taxonómia (belső néven maradhat, UI-szövegben
  "Szakmai terület"), pivot tábla a publikáció↔terület sokoldalú kapcsolathoz.
- Terület↔aloldal párosítás (melyik területhez melyik WP-oldal tartozik).
- **ÚJ: teljes funkció opt-in plugin-beállítás** (§14/1) — kikapcsolva: a Fázis 3
  szerkesztő űrlapon nincs terület-választó, a Fázis 5 widgeteken nincs
  terület-badge/szűrő.
- Fázis 3 edit-formja bővül a terület-választóval (feltételesen, a toggle szerint).

*Kész, ha:* egy terület aloldalán csak az adott terület tételei jönnek; a
funkció kikapcsolva a moderációs form és a widgetek is korrektül eltüntetik a
terület-UI-t.

## Fázis 5 — Elementor widget (A + B)

Eredeti "Fázis 5 — alap" (§9.1) helyett **két widget** (§14/10), a
`docs/widget-design.md`-ben rögzített, megrendelő által megerősített
mezőkészlettel és linkviselkedéssel:

- **Közös:** csak `status='approved'`, kizárólag a saját táblából olvas. Kártya-mezők:
  cím, szerzők, szakmai terület (ha be van kapcsolva), forrás, DOI, kiadványtípus,
  megjelenés éve, SJR-negyed, egyéb azonosítós logó-gombok (§14/4 — logó-asset-ek
  beszerzése itt esedékes: WoS/Scopus/PubMed/SZTAKI stb.). Teljes kártya kattintható,
  cél DOI-nál `doi.org/<doi>`, DOI hiányában (ha engedélyezve) a **gui-link**
  `m2.mtmt.hu/gui2/?mode=browse&params=publication;<mtid>` (VERIFIKÁLVA, NEM az
  API-endpoint). Indexkép hiányában placeholder-kép + rágenerált cím (§14/8 —
  előbb el kell dönteni: CSS-overlay vagy szerver-oldali GD/Imagick, ld. lent).
- **„A" — összesítő központi widget:** minden jóváhagyott tétel, év-fülekkel
  lapozva, szakmai terület szerinti szűrő-lenyíló (ha a Fázis 4 toggle be van
  kapcsolva), jól megcsinált kereső (cím/szerző/forrás).
- **„B" — terület-aloldal widget:** csak `is_featured=1` tételek, EGY konkrét
  terület/profil widget-beállításban kiválasztva. Csak akkor jelenik meg az
  Elementor widget-listában, ha a Fázis 3-as "kiemelt cikk" toggle be van
  kapcsolva (§14/11 — ez a widget-regisztráció feltétele).

*Kész, ha:* mindkét widget csak approved (ill. "B" esetén approved+featured)
tételt mutat, csak a saját táblát hívja, a linkviselkedés és a mezőkészlet a
widget-design.md szerint működik.

**Nyitott döntés Fázis 5 indítása előtt** (docs/widget-design.md "Nyitott
kérdések" szakasza): szerzőnév rövidített vagy teljes forma a kártyán;
placeholder-kép CSS-overlay vagy szerver-oldali generálás.

## Fázis 6 — GitHub + PUC

Változatlan (§10): header/verzió bump, `readme.txt`, PUC v5 init, első GitHub
Release. *Kész, ha:* egy teszt-oldal a GitHub release-ből frissülést lát a PUC-on át.

## Fázis 7 (opcionális) — nice-to-have-ek

Változatlan (§9.2), csak külön jóváhagyással: Dimensions idézettség-badge,
BibTeX lenyíló, haladó frontend-szűrők (szerző/típus/folyóirat/SJR/Norvég-szint),
Norvég-szint API-útjának lezárása (ha idáig még nem történt meg).
