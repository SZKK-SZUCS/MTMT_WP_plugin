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
