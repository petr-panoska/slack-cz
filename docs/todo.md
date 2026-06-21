# TODO

Otevřené úkoly napříč projektem. **Seřazeno podle priority** (revize 2026-06-20).
---

### ✅ Refaktoring (2026-06-21)
- [x] **Rename `Highline` → `Line`** — entita + všechny třídy, tabulky, indexy, sekvence, routy i veřejné URL (`/highline/{slug}` → `/line/{slug}`; verby anglicizované: `/upravit`→`/edit`, `/smazat`→`/delete`, `/historie`→`/history`, `/prechod`→`/crossing`, `/fotky`→`/photos`, `/navrhy`→`/proposals`). Slovo „highline" záměrně zůstává jako **disciplína** (`LineType::Highline`, command `app:import:line-crossings`, UI labely, `#highline` deep-link, legacy MySQL tabulka + souborový strom). 3 migrace (`Version20260621120000/134540/140000`) renamují schema. Ověřeno `lint:container` + `lint:twig` + `debug:router` + `doctrine:schema:validate`. Konvence v `architecture.md` § *Pojmenování: entita `Line`, disciplíny „highline" a „longline" a další*.
- [x] **CSS split dokončen** — `app.css` monolit (~5000 ř.) → **30 komponentních partialů** + `@import` manifest. Viz `architecture.md` § *Frontend* a níž.
- [x] **`ImportHighlineCrossingsCommand` → `ImportLineCrossingsCommand`** + `docs/highline-edits.md` → `docs/line-edits.md` (2026-06-21).

### Jazykové sjednocení — kód EN, URL CS (follow-up rename)
**Pravidlo: kód = anglicky, veřejné URL = česky** (appka je pro české uživatele, anglické URL nedávají smysl). Rename `Highline→Line` to nakousl, ale (a) v kódu zůstaly **české identifikátory**, (b) naopak ve veřejných URL přibyly **anglické verby**. Obojí narovnat — **velký, ne-urgentní rename, dělat najednou** (znovu projít `lint:container`/`lint:twig`/`debug:router`).

- [ ] **Anglicizovat zbylé české identifikátory v kódu** (entity, metody, proměnné, route names, názvy šablon + JS controllerů — UI texty a legacy SQL/sloupce zůstávají CS):
  - Šablony: `templates/pages/denicky.html.twig`, `mapa.html.twig`, `user_denik.html.twig` → EN (`diaries`/`map`/`user_diary`) + opravit `render`/`include`/`renderView` cesty.
  - Stimulus: `user_denik_map_controller.js` → `user_diary_map_controller.js` (+ `data-controller`/`data-*` v šabloně).
  - PHP: `UserController::denik()` a podobné CS metody/proměnné; route names `app_user_denik`, `app_user_directory` (+ `app_line_map*` jsou už EN) → EN, a přepsat všechny `path('app_user_denik')`/`{{ path(...) }}`.
  - Sken: `grep -rniE 'denik|denicky|mapa|prechod|navrh|lajn|fotk|vyska|delka|oblast|misto' src/ assets/ templates/` → odfiltrovat UI texty + legacy SQL.
- [ ] **Zvážit revert anglických URL zpět do CS.** Rename anglicizoval veřejné cesty: `/line/{slug}/edit|delete|history`, `/line/{slug}/crossing/new`, `/line/{slug}/photos/*`, `/admin/proposals/*`, longline verby. Pro CZ appku zvážit `/line/`→`/lajna/` a verby zpět česky (`/upravit`, `/smazat`, `/historie`, …). **Pozn.:** každá změna URL = další break bez redirectů (staré linky 404) → rozhodnout společně s cutoverem legacy `slack.cz`.

### 🔥 Teď

- [ ] Responzivita celého webu
  - [x] **CSS refaktoring** (2026-06-20→21) — `app.css` 5000 řádků → **30 komponentních souborů** v `assets/styles/` (`app.css` je teď `@import` manifest). Sjednoceny breakpointy na 640/768/900px (bylo 600/640/700/720/760/800/900px). Přidán theme system v `_tokens.css` (4 webbing-color varianty: weaver/slackline-tools/gibbon/landcruising) přes CSS custom properties a `data-theme` atribut.
  - [ ] **Chybějící mobile styly** — nav na malých obrazovkách (hamburger / skrytí nav odkazů), forms, panel layout na mobilu
  - [ ] webbing themes picker

### Obsah ze starého slack.cz (nasát + archivovat)
- [ ] **Archiv článků** — nasosat texty/články z původního slack.cz a udělat z nich archiv v nové appce.
- [ ] **slackTV — content z old slack.cz** — nasát videa/odkazy ze starého webu do slackTV.
- [ ] **Nové fotky** — z původního slack.cz máme hodně low-quality fotek, potřebujeme nové. Hlavní výzva pro Intro stránku.

### Intro
- Intro: stránka `/intro` hotová (`app_intro` + `templates/pages/intro.html.twig`, seed citáty „deníček" + „co po nás zbyde"), ale **zatím nelinkovaná z nav.**
  - [ ] Rozhodnout, jak a kde Intro ukázat uživatelům (homepage onboarding? první návštěva? odkaz v menu?).

### UX — co nejjednodušší pro lajnery
Cíl (sezení 2026-06-12): ať je appka pro lajnery co nejjednodušší na používání.
- [ ] **Smooth highline edit flow** — když user aktualizuje info o lajně, ať je to co nejjednodušší (minimum kroků, žádné tření). Projít celý edit flow a vyhladit.
- [ ] **Smooth zadávání přechodu** — stejný princip pro přidání přechodu: co nejmíň klikání, rychlé zapsání zážitku.

### Legacy import fotek ze slack.cz
Foto stažené z legacy webu do `../old-slack-cz`. Highline import **hotový** (`make importLegacyPhotos`). Zbývají vedlejší věci níž.

- [x] **`app:import:line-photos`** (2026-06-16) — cover (`foto.jpg`, filesystem-only konvence) + galerie (`highline_foto`) → WebP mastery, navázané na highline přes `legacyId`. Datum = 1. napnutí lajny / null. Ověřeno disk × legacy DB × živý web, **0 ztraceno**. Detail v `migration.md` § *Line photos*.
- [x] **Cover fotka lajny** (2026-06-16) — self-hostovaná přes `Line.coverPhoto` (FK), legacy hotlink zrušen (bez fallbacku).
- [ ] **`highline_media`** — externí odkazy (foto/video/clanek na youtube / rajce / lezec), **ne lokální fotky** → potřebuje vlastní entitu + UI sekci na detailu. Deferred, viz `migration.md` § Mimo scope.
- [ ] **Non-highline fotky (FotoLIVE)** — legacy tabulka `fotolive` jsou fotky nepatřící ke konkrétní lajně. Rozhodnout, jestli pro ně udělat zvlášť kategorii / galerii.
- [ ] **Fotky na betu** — `public/uploads/line/*` jsou soubory mimo git **i mimo `pg_dump`**. `make syncBetaFromLocal` veze jen DB → po lokálním importu ještě **`make syncBetaPhotos`** (rsync masterů na betu), jinak budou `line_photo` řádky ukazovat na neexistující soubory. (cutover krok, viz `deploy.md`)
- [ ] **Nasadit `@photos` cache blok** z `infra/Caddyfile` na betu (`make deployCaddy`) — teď drift, `make checkCaddy` upozorňuje a `make deploy` je kvůli tomu blokovaný.


### Mapa
- [x] **BUG: mapa nejde zoomovat Ctrl+kolečkem** (2026-06-16) — sdílený helper `assets/map_scroll_zoom.js` (`enableCtrlScrollZoom`): inline mapa = plain kolečko scrolluje stránku, **Ctrl/⌘ + kolečko zoomuje** (mirror Leafletího debounce/zoom-to-cursor) + hint overlay „podrž Ctrl"; **ve fullscreenu zoomuje plain kolečko bez modifikátoru** (není co scrollovat). Nasazeno na vložené mapy: `hp_map`, `user_denik_map`, `line_detail_map`, `line_form_map`. Dedikovaná `/mapa` (`map_controller`) **záměrně ponechána** se zoomem samotným kolečkem (na celostránkové mapě očekávané).
- [ ] Filtry na `/mapa` (typ, délka, výška)
- [ ] Vlastní ikony per typ (Highline / Midline / Longline / Waterline — různé barvy)
- [ ] Clustering markerů v hustých oblastech (Tisá, Ostrov)
- [ ] „Přidat lajnu" CTA klikem na mapu — z `/mapa` klik na prázdné místo předvyplní Bod 1 (teď jen v hlavičce + ručně nastavené GPS)

### Profil — avatar / bio / odkazy
- [ ] Avatar / bio / odkazy (IG, web) — UI editor pro vlastní profil + sloupce v entitě, případně backfill z legacy.

### Highline edit / verification — follow-upy
Trust model + proposal queue v `docs/line-edits.md`.
- [ ] Bulk verify — admin UI s checkboxy nad seznamem unverified lajn (teď to musí klikat 1×1).
- [ ] Notifikace adminovi mailem při `PENDING` proposalu (Symfony Mailer — závisí na `MAILER_DSN`, viz Cutover).
- [ ] Notifikace authorovi proposalu o schválení/zamítnutí.
- [ ] „Soft-delete" lajny (`deleted_at` flag) místo hard delete — kdyby admin chtěl revertovat omylem smazanou lajnu.

### slackTV — follow-upy
- [ ] **Cold-cache `/tv` dělá ~19 sekvenčních HTTP volání** — při expiraci `tv.sections` (6 h) první návštěvník čeká ~5–10 s (worst case víc; 6s timeout × N). Paralelizovat (concurrent HttpClient) nebo nahřívat cache cronem. (MED — UX jednou za TTL)
- [ ] Až bude existovat YT kanál CAS, přidat jeho channel ID do `feed.youtube.channels`.
- [ ] Přidat další search query / kanál — při TTL 6h je quota rezerva ~25×, pár dalších zdrojů se vejde bez úprav TTL (vzorec v `architecture.md` § *Quota economics*). TTL prodlužovat až kdyby queries narostly o řád.
- [ ] Promyslet FB Page CAS jako další zdroj (pak nutný jiný fetcher; Meta API).
- [ ] Hashtag taby bez klávesové navigace (arrow keys / roving `tabindex`) — není plný WAI-ARIA tablist. (LOW, a11y)
- [ ] **Hashtag slider: „view on YouTube" míří na `/hashtag/{tag}` místo na search** — `hashtagGroup()` (`YoutubeTvFeed.php`) odkazuje na `youtube.com/hashtag/{tag}` = YT-ořezaná plocha (~10 videí, i když čítač hlásí tisíce) → dead-end. Fix = jeden řádek: `url: 'https://www.youtube.com/results?search_query=' . rawurlencode($tag)`. Ověřeno přes API: `#` je v searchi no-op; hashtagy v configu zůstávají. (LOW)

### Wiki
- [ ] Doplnit chybějící obrázky — úvodní Google Docs seed přenesl jen 8 z ~N obrázků. Editovat `wiki/NN-skupina/NN-slug.md` na GitHubu, přidat `![][imageN]` ref-defs s base64 (případně inline `data:image/png;base64`). (LOW, kosmetika)

---

## Cutover legacy slack.cz → nový VPS (až po featurách)
Detailně v `deploy.md`. Launch-blockery.

- [ ] **`MAILER_DSN` přes externí SMTP relay** (Brevo / Mailgun / Postmark) — Hetzner blokuje port 25 outbound. Dokud nejede, fallback: `bin/console app:user:reset-password <email>` (viz `deploy.md`). Odblokuje i admin/author notifikace u verifikací.
- [ ] **DNS swap** — A pro `slack.cz` z legacy IP na `178.105.81.158` + AAAA (gray cloud, DNS-only).
- [ ] **`slack.cz` blok do `infra/Caddyfile`** (analogický k `beta.slack.cz`) + `make deployCaddy`.
- [ ] Po DNS swapu přepsat `DEFAULT_URI` v `/var/www/slack-cz/.env.local` z `https://beta.slack.cz` na `https://slack.cz` + reload PHP-FPM (čte to `framework.router.default_uri` pro absolutní URL z CLI commandů).
- [ ] Zkontrolovat `LineController::PRODUCTION_URL` (`https://www.slack.cz`) — používá ji veřejná „Data report" stránka (`/data-report`) pro proklik na legacy detail; upravit, pokud se prod host změní.
- [ ] Nastavit reálné `YOUTUBE_API_KEY` v `.env.local` na serveru pro slackTV feed.
- [ ] (volitelné) `unattended-upgrades` na auto-security patches.
- [ ] (volitelné) Hetzner snapshot schedule pro disaster recovery.

---

## Údržba / drobnosti
- [ ] Rozšířit smoke testy o DB-backed routy (`/`, `/mapa`, `/denik/{id}`, …) — vyžaduje test DB se schématem (přidat `doctrine:schema:create --env=test` step do `symfony.yml`; migrace jsou Postgres-specific, na SQLite je nepustíš).
- [ ] Přidat `/register` (a další form-rendering routy) do smoke testů až po deprecation cleanupu — render `RegistrationForm` teď spouští **přímé** array-option constraint deprecations, takže s `failOnDeprecation=true` by shodil CI. Viz `roadmap.md` deprecation checklist.
- [ ] Default Symfony `hello_controller.js` v `assets/controllers/` smazat až bude místo něj něco užitečného.
- [ ] Restrikce `YOUTUBE_API_KEY` v Google Cloud Console (HTTP referrers + jen YouTube Data API v3) — teď bez restrikce. (nízká, nic na tom nestojí)
- [ ] Po nasazení produkce rotovat `YOUTUBE_API_KEY`. (nízká)

---

## Backlog / plány (neimplementovat teď)

### Offline PWA — highline mapa v terénu (PLÁN)

> Stav: **návrh k doladění.** Sepsáno session 2026-06-02. Záměr: highliner v horách bez signálu si appku nainstaluje jako PWA a má offline **celou ČR** (podkladová mapa + body highlinů + popupy + časová osa). Nic se zatím nestaví — nejdřív dorozhodnout otevřené body níž.

#### Rozhodnutí, která už padla
- **Zůstává Leaflet, nemění se za MapLibre.** Použít `protomaps-leaflet` (renderuje vektorové PMTiles přímo v Leafletu, canvas). Vymění se jen podkladová vrstva — `L.tileLayer(OSM)` → vektor nad pmtiles. Všechny 4 mapové controllery (`map`, `line_detail_map`, `user_denik_map`, `line_form_map`) i veškerý marker/popup/timeline kód zůstávají beze změny. MapLibre by znamenal přepsat všechno.
- **Instalace PWA = souhlas se stažením celé ČR.** Po instalaci se automaticky na pozadí stáhne `cz.pmtiles` (žádné druhé klikání). Install je vědomý úkon → bereme ho jako „chci to celé offline".
- **Vlastní hostovaný pmtiles**, ne OSM tile server (OSM policy zakazuje hromadný předstah; navíc chceme nezávislost na cizím zdroji).

#### Architektura (4 etapy, ~2,5 dne, největší riziko etapa 3)

**Etapa 1 — PWA základ** (~půlden)
- `symfonycasts/pwa-bundle` (integruje AssetMapper). Manifest + service worker.
- Precache: app shell, Leaflet/protomaps-leaflet assety, marker ikony.
- JSON endpointy (`app_line_map_data` / `_feed` / `_timeline`) → runtime cache *stale-while-revalidate*.
- Výsledek: appka instalovatelná, body + popupy + časová osa offline. Podklad zatím chybí.

**Etapa 2 — vektorový podklad ČR** (~půlden)
- CZ extract z `protomaps.com/downloads` (bbox ~12.0,48.5,18.9,51.1) → `public/maps/cz.pmtiles`.
- V `map_controller.js` vyměnit OSM tileLayer za `protomaps-leaflet` vrstvu (flavor sladit s light theme + magenta).
- Online jede přes HTTP range requesty z vlastního souboru.

**Etapa 3 — auto-offline po instalaci** (~1 den, tady je vlastní inženýrská práce)
- Detekce instalace: `appinstalled` (Chromium/Android) **+** standalone-detekce při startu (`display-mode: standalone`) pro iOS, kde `appinstalled`/`beforeinstallprompt` neexistují.
- Po instalaci stáhnout `cz.pmtiles` → **OPFS** (Origin Private File System, iOS 16.4+ OK), progress bar (kolik z X MB).
- Custom pmtiles source čte rozsahy z OPFS přes `File.slice()` → range requesty lokálně.
- Resume při přerušení (foreground download — browser nedá spolehlivý background download); fallback na online range requests dokud není staženo.

**Etapa 4 — prod parity** (~půlden)
- Caddy: servírovat `.pmtiles` s `Range` + MIME `application/octet-stream` (`infra/Caddyfile` + `make deployCaddy`).
- `.pmtiles` **mimo git** (desítky MB) → stáhnout/vygenerovat při deployi. Update `deploy.md`, `deploy.sh`, paměť.

#### Odhad velikosti (ballpark, změřit po reálném extractu)
- maxzoom 14 ≈ ~80–150 MB — dobrý lokální detail (vidíš ke skalám)
- maxzoom 13 ≈ ~40–60 MB — terénní kontext, hrubší zoom

#### OTEVŘENÉ ROZHODOVACÍ BODY (k doptání)
1. **Styl podkladu / vrstevnice.** `protomaps-leaflet` basemap je „city" styl **bez vrstevnic / hillshade / terénu** — pro highliny v horách možná chceme topo. Varianty: (a) smířit se s plochým podkladem, (b) přidat druhou pmtiles vrstvu s vrstevnicemi/hillshade (víc dat + práce), (c) jiný zdroj tilesetu. **Tohle je největší otevřená otázka.**
2. **maxzoom** — z13 (lehčí) vs z14 (detailnější). Ovlivní velikost stažení i ostrost u skal.
3. **Co všechno je offline.** Jen `/mapa`, nebo i detail stránky `/line/{slug}` (server-rendered Twig, 254 stránek)? Buď runtime-cache jen navštívené, nebo přestavět detail na data-driven z cachovaného JSON. Fotky/audio offline spíš ne (velikost).
4. **iOS UX** — když nejde chytit install event, kdy přesně spustit download (hned při prvním standalone startu? za potvrzením kvůli mobilním datům?).
5. **Aktualizace pmtiles** — jak často přegenerovat CZ extract a jak řešit cache-busting / verzování souboru.
6. **Stažení jen na wifi?** Desítky MB — nabídnout potvrzení / detekci typu připojení před stažením?
7. **PWA scope** — instalovatelná celá appka, nebo prezentovat jako „nainstaluj si mapu"?

### Doporučení obsahu od userů (DEFERRED)

> Stav: **myšlenka k dořešení.** User si není jistý, jestli celý koncept dává smysl — implementaci odložit. Sepsáno session 2026-06-11.

- Záměr: přihlášený user vloží YouTube odkaz (video / kanál / playlist) jako tip; po **moderaci adminem** se schválené objeví v sekci **„Doporučeno komunitou"**.
- Rozhodnuto v konzultaci:
  - Sbírat **videa + kanály + playlisty** (ne jen videa).
  - Schválené → **vlastní sekce „Doporučeno komunitou"** (oddělená od kanálů/playlistů/hashtagů).
  - Default (k potvrzení): doporučovat jen **přihlášení**, nic se nezobrazí **bez schválení adminem**.
- Technika: **oEmbed** (`youtube.com/oembed?url=…&format=json`) na validaci + metadata (název, autor, náhled) — **0 kvóty, bez klíče**. Nová entita `ContentSuggestion` (user, typ, YT id, metadata, status pending/approved/rejected). Admin moderační fronta.
- **OTEVŘENÉ (blokuje to):** kde žije seznam zdrojů sekcí — **config (`feed.yaml`) vs DB + admin**. Schválený kanál/playlist by se musel propsat mezi zdroje → tlačí to k DB-backed zdrojům spravovaným v adminu. Nerozhodnuto; tohle je důvod, proč je celá feature odložená.

---

## Hotové (archiv)

- [x] **WebP/HEIC upload pipeline** (2026-06-16) — `App\Service\PhotoNormalizer`: každý upload (user i legacy import) → WebP master (auto-orient, ≤ 2560 px, q85, strip metadat), datum + GPS z EXIF do DB (`LinePhoto.createdAt` nullable, `gpsLat`/`gpsLng`), HEIC z iPhonů. ImageMagick + exiftool v containeru i na prod (`setup-server.sh` + `check-server-env.sh` preflight). Nahradil GD `LinePhotoSanitizerSubscriber`. Disk-conscious (WebP, cap 2560) kvůli 40 GB VPS. Detail `architecture.md` § *Foto galerie — upload pipeline*.
- [x] **Legacy foto import** (2026-06-16) — viz „🔥 Teď → Legacy import fotek" (cover + galerie → WebP, 0 ztraceno).
- [x] **Veřejný rozcestník deníčků `/denicky`** (2026-06-15) — přehled všech aktivních uživatelů (nick, jméno, # highline/longline přechodů, poslední aktivita) s prolinkem na `/denik/{id}`, fulltext filtr + sort přes sloupce. Vlastní generický Stimulus `data-table` controller.
- [x] **Longline deník** (2026-06-13) — tab na `/denik/{id}` (entita `LonglineCrossing` + import `app:import:longline-crossings` + plný CRUD + obecná Tab komponenta).
- [x] **slackTV → `/tv` stránka + redesign na sekce** (2026-06-11) — feature přejmenována „Slack.cz TV" → **slackTV**, dedikovaná `/tv` (grid + click-to-play inline embed přes `tv_controller.js`). Rozděleno na sekce Kanály / Playlisty / Hashtagy (slidery + taby). Data přes YouTube Data API se stránkováním (`tv-more` AJAX), `FeedGroup` tvar, per-page cache. Zdroje v `feed.yaml`, detaily v `architecture.md` § *Feed (slackTV)*. Code-review fixy: `/tv/more` validace `key` proti configu; transient load-more chyba cachuje prázdno jen 60 s (ne 6 h).
- [x] **Homepage panely** — slackTV ze sidebaru → horizontální strip dole; panely „Highline mapa" + „Poslední přechody" sloučeny do jednoho **Mapa** se sidebarem přechodů (aktivní přechod, reálná linka, zoom + emoji walker animace, `hp_map_controller.js`).
- [x] **Linie mezi `point1` a `point2`** na hlavní mapě `/mapa` (vlastní layer, viditelná od zoom ≥ 14).
- [x] **Cleanup** — smazán `App\Old\Entity\Uzivatel` (entita + repo + debug view `/old-users` + ORM `old` EM; DBAL `old` connection zůstává pro raw-SQL importy). Oživen `.github/workflows/symfony.yml` + `tests/Controller/PublicPagesSmokeTest.php` (DB-free routy na SQLite). Smazána mrtvá env `APP_SHARE_DIR`.
- [x] **Migrace legacy dat — kompletní.** Highlines / users / crossings naimportované; deferred otázky vyřešené (first ascents = neimportovat; `record` role = mrtvý štítek; `enabled=0` = test junk; cross-email merge Terky 93→878). Plný příběh v `migration.md`.
- [x] **Gender kompletně odstraněn** (2026-06-12) — vyhozeno z importu, smazána `User.gender` + enum `App\Enum\Gender`, drop-column migrace `Version20260612120000`.
- [x] **Edit profile** — `/profile/edit` (`app_profile_edit`, `UserForm`). User edituje město, ročník, telefon.
- [x] **Infra: Caddyfile v repu + drift gate** (2026-05-11) — `infra/Caddyfile` jako single source of truth, `scripts/check-caddy.sh` + `scripts/deploy-caddy.sh`, Makefile `checkCaddy`/`deployCaddy`, `make deploy` preflight. Caddy restart nikdy implicitně při code deploy.
- [x] **Galerie sociální vrstva + AJAX likes** (2026-05-11) — `LinePhotoLike` (UNIQUE photo+user) + `LinePhotoComment` (flat, plain-text), detail page `/line/{slug}/photos/{id}`, like-toggle bez reloadu (`photo_like_controller.js`, JSON endpoint), homepage „Z galerie" rotace. Cover zatím čistě legacy URL. Migrace `Version20260511134125`.
- [x] **Audit historie + stack-pop curation + verifikační merge** (2026-05-10) — `/line/{slug}/history` nad `LineEdit` rows, vlastní `beforeSnapshot` per row, admin „Smazat poslední revizi", verify slévá historii do jediné APPLIED creation revize. Maintenance `app:edit:sync-from-history`. Detaily v `docs/line-edits.md`.
- [x] **Slug stability na verified + rename slug regen na unverified** (2026-05-10) — `nameLocked` z `isVerified()`, live slug preview (`live_slug_controller.js`), `makeUniqueSlug` regen na unverified rename, midpoint rounding na 7 míst.
- [x] **Parking GPS import** (2026-05-10) — `Line.parkingLatitude/Longitude`, LEFT JOIN `gps WHERE type='PARKING'` (133/254 lajn), detail + form toggle. Migrace `Version20260510175252`.
- [x] **Line CRUD + verifikační/proposal flow** (2026-05-10) — `LineCrudController`, `Line.isVerified` + `createdBy`, `LineEdit` unified audit log + pending queue, 254 legacy lajn `verified=true`, `ROLE_ADMIN` + `app:admin:grant`, `line_form_map_controller` 2-endpoint GPS picker. Detaily v `docs/line-edits.md`.
- [x] **Crossing form (CRUD pro přechody)** — `CrossingController` + `LineCrossingForm`, edit/delete vlastních přechodů z deníku.
- [x] **Deník uživatele `/denik/{id}`** — hlavička, mini-mapa navštívených lajn, tabulka přechodů. Linkováno z „Posledních přechodů" + z popupu na `/mapa`.
- [x] **Crossing news-bar sidebar** na `/mapa` — N posledních přechodů, eye toggle, collapse, time-travel režim. Cross-controller eventy `slack:map-mode` / `slack:users-visibility`.
- [x] **Single source of truth pro recent crossings** — `LineCrossingRepository::RECENT_LIMIT`, `findRecent()` + `findRecentForJson()`.
- [x] **Intro splash overlay** — fullscreen logo + „Vstoupit" (komponenta `intro-overlay`), klik = dismiss + start audio.
- [x] **Mapové zoom controly na `bottomright`** (kolidovaly se sidebarem; v time-travel se zvedají nad panel).
- [x] **Line detail `/line/{slug}`** — slug v DB, stat panel, info tabulka, mini-mapa s polyline mezi body, seznam přechodů.
- [x] **Line `point1`/`point2` GPS** — naimportováno z legacy `gps` přes JOIN na `point1_id`/`point2_id` (`LINE_POINT`).
- [x] **User import** — 440 nových + 1 enriched + 6 dropped (5 duplicit emailů). MD5 hesla, `migrate_from` přehashuje na bcrypt při loginu.
- [x] **Crossings import** — 993/995 (2 skipy `0000-00-00`). Style enum 9 hodnot, neznámé → warning.
- [x] **`Makefile`** — target `legacyImport` (jednorázový import na čisté schéma, `--truncate`).
- [x] **„Poslední přechody" panel** na indexu (datum / user / lajna / hvězdy). Limit v `RECENT_LIMIT`.
- [x] Line import 254/254 z legacy DB do Postgres.
- [x] Mapa `/mapa` s Leaflet + OSM (fixnuté ikony přes Stimulus values).
- [x] Light theme s magenta accent (`#e91e63` z původního loga).
- [x] Index page se 2 panely (mini mapa + slackTV teaser).
- [x] slackTV — YouTube Data API v3 fetcher s mock fallbackem a Symfony Cache TTL.
- [x] About page `/o-projektu` — historie slacklive 2007 → slack.cz 2010 → ČAS 2011 → dnes + Kolouchovo úvodní slovo + archivní vizuály.
- [x] Restrukturalizace legacy mapování — `App\Entity\Old\*` → `App\Old\Entity\*` (mimo `src/Entity/`), aby Doctrine default EM nepokoušel migrace.
- [x] `.env` deklaruje `YOUTUBE_API_KEY=` (prázdné, dokumentace), reálný v `.env.local`.
