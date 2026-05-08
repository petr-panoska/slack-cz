# TODO

Otevřené úkoly napříč projektem. Aktualizuj kdykoli zmizí / přibude.

## Migrace dat (priority 1)

Hotovo — viz archiv níž. Otevřená pouze deferred práce (first ascents) a doptávky na Koloucha.

## Migrace dat (deferred)

- [ ] First ascents (`prvni_prechody_hl`, 124 řádků) — rozhodnout sloučení s běžnými přechody vs vlastní entita; doptat se Koloucha o smyslu / UI prezentaci. 49 orphans (NULL `uzivatel_id` s textovým nickem) potřebují fallback strategii.
- [ ] Doptat se Koloucha co znamená `record` role (1 uživatel) — máme něco zachovat?
- [ ] Doptat se proč 6 uživatelů má `enabled=0` — má být login disabled, nebo skryti úplně?

## Mapa highlines

- [ ] Filtry na `/mapa` (typ, kraj, délka, výška)
- [ ] Vlastní ikony per typ (Highline / Top Highline / Midline / Urban Line — různé barvy)
- [ ] Clustering markerů v hustých oblastech (Tisá, Ostrov)
- [ ] Linie mezi `point1` a `point2` na hlavní mapě `/mapa` (na detailu už je)
- [ ] Foto galerie na detailu (potřeba import `highline_foto` + `highline_media` z legacy)
- [ ] Pretty-print legacy `kotveni` (často číselný kód) — namapovat na čitelný popis
- [ ] CRUD pro přidávání nové highline (jen pro přihlášené, click na mapě = nastaví GPS)

## Slack.cz TV

- [ ] Až bude existovat YT kanál CAS, přidat jeho channel ID do `feed.youtube.channels`
- [ ] Pokud chceme zobrazovat víc než 2 search queries, prodloužit `feed.cache.ttl_seconds` na 3600s (1h) — viz quota matematika v `architecture.md`
- [ ] Promyslet, jestli má smysl přidat i FB Page CAS jako další zdroj (pak nutný jiný fetcher; Meta API)

## Deník uživatele

- [ ] Longline deník (až bude longline import) — přidat tab nebo sekci na `/denik/{id}`
- [ ] Avatar / bio / odkazy (IG, web) — UI editor pro vlastní profil + sloupce v entitě, případně backfill z legacy
- [ ] „Síň slávy" / žebříček uživatelů (nejvíc přechodů, nejdelší lajna) — nový index na `/denicky`
- [ ] FA badge u přechodu / na profilu, jakmile dořešíme prvopřechody
- [ ] Edit profile (po přihlášení) — město, ročník, telefon, gender

## Ostatní

- [ ] Restrikce YouTube API key v Google Cloud Console (HTTP referrers + jen YouTube Data API v3) — momentálně je key bez restriction, je v transkriptech konverzace
- [ ] Po nasazení produkce: rotovat `YOUTUBE_API_KEY`
- [ ] Uklidit `App\Old\Entity\Uzivatel` — zatím se používá pro debug view `/old-users`. Po user importu zvážit smazání route + entity (po user importu už máme data v nové DB).
- [ ] Default Symfony `hello_controller.js` v `assets/controllers/` smazat až bude místo něj něco užitečného
- [ ] Index page má prázdný main sloupec vlevo nad mapou (kdyby měl menší výšku) — můžeme tam dát další box (nedávno přidané, top rated, něco z deníčku po user importu)

## Dokončené (krátký archiv)

- [x] **Deník uživatele `/denik/{id}`** — `UserController::denik` + `pages/user_denik.html.twig`. Hlavička (nick, město, ročník, datum prvního přechodu), stat panel (# přechodů, # unikátních highlines, suma délek, max délka, max výška), mini-mapa s body všech navštívených highlines (zoom na bbox, popup s odkazem na detail), tabulka přechodů. Linkováno z „Posledních přechodů" stripe a z popupu uživatele na `/mapa`.
- [x] **Highline detail `/highline/{slug}`** — slug v DB (unique), import doplňuje přes `AsciiSlugger`. Stránka: stat panel (délka/výška/rating), info tabulka (kotvení 1/2, přístup, napínání, autor, datum, historie názvu, GPS s odkazem na mapy.cz), mini-mapa s polyline mezi `point1Latitude/Longitude` a `point2Latitude/Longitude` (pokud oba body známe), seznam všech přechodů. Detail je nalinkovaný z popupu na `/mapa` i ze stripe „Posledních přechodů" na indexu.
- [x] **Highline `point1Latitude/Longitude` + `point2Latitude/Longitude`** — naimportováno z legacy `gps` tabulky přes JOIN na `point1_id` / `point2_id` (typ `LINE_POINT`).
- [x] **User import** — 440 nových User + 1 enriched (existující dev účet) + 6 dropped legacy řádků mergováno (5 duplicit emailů). MD5 hesla zachována, `migrate_from` v security.yaml přehashuje na bcrypt při prvním loginu.
- [x] **Crossings import** — 993 / 995 přechodů (2 skipy kvůli `0000-00-00` datům). Style enum s 9 hodnotami, mapping legacy → enum, neznámé hodnoty se reportují jako warning.
- [x] **`Makefile`** — targety `legacyImport` (re-import s `--truncate`) a `legacyImportFresh` (drop+create+migrate+full import).
- [x] **„Poslední přechody" panel** na indexu (vedle Slack.cz TV) — ukazuje 5 nejnovějších (datum / user / lajna / hodnocení v hvězdách).
- [x] Highline import 254/254 z legacy DB do nové Postgres
- [x] Mapa `/mapa` s Leaflet + OSM, fixnutý problém s ikonami (asset URLs přes Stimulus values)
- [x] Light theme s magenta accent (`#e91e63` z původního loga)
- [x] Index page se 2 panely (mini mapa + Slack.cz TV)
- [x] Slack.cz TV — YouTube Data API v3 fetcher s mock fallbackem a Symfony Cache TTL
- [x] About page `/o-projektu` s historií slacklive 2007 → slack.cz 2010 → ČAS 2011 → dnes, plus Kolouchovo úvodní slovo (verbatim) a archivní vizuály (slacklive logo, kolouch-vitejte foto)
- [x] Restrukturalizace legacy mapování — `App\Entity\Old\*` → `App\Old\Entity\*` (mimo `src/Entity/`), aby Doctrine default EM nepokoušel migrace pro legacy tabulky
- [x] `.env` deklaruje `YOUTUBE_API_KEY=` (prázdné, dokumentace), reálný v `.env.local` (gitignored)
