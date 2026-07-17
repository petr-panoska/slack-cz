# TODO

Otevřené úkoly napříč projektem. **Seřazeno podle priority.** (revize 2026-07-09 —
kompletní revize: hotové věci přesunuté do archivu na konci souboru, otevřené
přeorganizované nahoru podle toho, co dává smysl dělat teď.)

---

## Teď — dává smysl udělat

### Intro
Stránka `/intro` hotová (`app_intro` + seed citáty), ale zatím nelinkovaná z nav.
- [ ] Rozhodnout, jak a kde Intro ukázat uživatelům (homepage onboarding? první návštěva? odkaz v menu?).

### Cutover: `slack.cz` → nový VPS (launch blockery)
Detailně v `deploy.md`. Appka běží na `beta.slack.cz`, tohle je poslední balík
před ostrou doménou.

- [ ] **`MAILER_DSN` přes externí SMTP relay** (Brevo / Mailgun / Postmark) — Hetzner blokuje port 25 outbound. Dokud nejede, fallback: `bin/console app:user:reset-password <email>`. Odblokuje i admin/autor notifikace u verifikací (viz níž).
- [ ] **Fotky na betu** — `public/uploads/line/*` jsou mimo git i mimo `pg_dump`. `make syncBetaFromLocal` veze jen DB → po lokálním importu ještě **`make syncBetaPhotos`** (rsync masterů), jinak `line_photo` řádky ukazují na neexistující soubory.
- [ ] **DNS swap** — A pro `slack.cz` z legacy IP na `178.105.81.158` + AAAA (gray cloud, DNS-only).
- [ ] **`slack.cz` blok do `infra/Caddyfile`** (analogický k `beta.slack.cz`) + `make deployCaddy`.
- [ ] Po DNS swapu přepsat `DEFAULT_URI` v `/var/www/slack-cz/.env.local` z `https://beta.slack.cz` na `https://slack.cz` + reload PHP-FPM.
- [ ] Zkontrolovat `LineController::PRODUCTION_URL` (`https://www.slack.cz`) — používá ji `/data-report` pro proklik na legacy detail; upravit, pokud se prod host změní.
- [ ] Nastavit reálné `YOUTUBE_API_KEY` v `.env.local` na serveru pro slackTV feed.
- [ ] Po prvním deployi s galerií zkontrolovat `line_photo.width` na serveru (`SELECT count(*) FROM line_photo WHERE width IS NULL`). Kdyby zůstaly NULL, spustit **`bin/console app:photo:backfill-dimensions`**; bez toho `/galerie` kreslí fallback poměr 4:3.
- [ ] (volitelné) `unattended-upgrades` na auto-security patches.
- [ ] (volitelné) Hetzner snapshot schedule pro disaster recovery.

### Mapa
- [ ] „Přidat lajnu" CTA klikem na mapu — z `/mapa` klik na prázdné místo předvyplní Bod 1 (teď jen v hlavičce + ručně nastavené GPS).
- [ ] Vlastní ikony per typ (Highline / Midline / Longline / Waterline — různé barvy).

### UX — co nejjednodušší pro lajnery
Cíl (sezení 2026-06-12): ať je appka pro lajnery co nejjednodušší na používání.
- [ ] **Smooth highline edit flow** — projít celý edit flow, minimum kroků, žádné tření.
- [ ] **Smooth zadávání přechodu** — stejný princip: co nejmíň klikání, rychlé zapsání zážitku.

### Highline edit / verification — follow-upy
Trust model + proposal queue v `docs/line-edits.md`.
- [ ] Bulk verify — admin UI s checkboxy nad seznamem unverified lajn (teď to musí klikat 1×1).
- [ ] Notifikace adminovi mailem při `PENDING` proposalu — závisí na `MAILER_DSN` výš.
- [ ] Notifikace autorovi proposalu o schválení/zamítnutí.
- [ ] „Soft-delete" lajny (`deleted_at` flag) místo hard delete — kdyby admin chtěl revertovat omylem smazanou lajnu.

### Wiki
- [ ] Doplnit chybějící obrázky — úvodní Google Docs seed přenesl jen 8 z ~N obrázků. Editovat `wiki/NN-skupina/NN-slug.md`, přidat `![][imageN]` ref-defs. (LOW, kosmetika)

### Obsah ze starého slack.cz
Vyžaduje ruční sběr materiálu, ne jen dev práci.
- [ ] Archiv článků — nasosat texty/články z původního slack.cz, udělat z nich archiv v nové appce.
- [ ] slackTV — content z old slack.cz — nasát videa/odkazy do slackTV.
- [ ] Nové fotky — z původního slack.cz máme hodně low-quality fotek. Hlavní výzva pro Intro stránku.

### Údržba / drobnosti
- [ ] Rozšířit smoke testy o DB-backed routy (`/`, `/mapa`, `/denik/{id}`, …) — vyžaduje test DB se schématem.
- [ ] Přidat `/registrace` (a další form-rendering routy) do smoke testů až po deprecation cleanupu — render `RegistrationForm` teď spouští přímé array-option constraint deprecations. Viz `roadmap.md` deprecation checklist.
- [ ] Default Symfony `hello_controller.js` v `assets/controllers/` smazat až bude místo něj něco užitečného.
- [ ] Restrikce `YOUTUBE_API_KEY` v Google Cloud Console (HTTP referrers + jen YouTube Data API v3) — teď bez restrikce. (nízká)
- [ ] Po nasazení produkce rotovat `YOUTUBE_API_KEY`. (nízká)

---

## Backlog / odloženo (neřešit teď)

- [ ] **Time-travel („Přehrát historii") — doladit do „super feature"** — user ho chce zachovat a vylepšit, ne smazat. Toggle je zakomentovaný v `map.html.twig` (a `top: 44px` offset v `_map.scss`) od 2026-07-05; mobilní redesign ovládání, na který se čekalo, mezitím proběhl (map-panel taby, 2026-07-07/08), takže je to odemčené — jen teď nemá prioritu.
- [ ] Profil: avatar / bio / odkazy (IG, web) — UI editor pro vlastní profil + sloupce v entitě, případně backfill z legacy.
- [ ] Non-highline fotky (FotoLIVE) — legacy tabulka `fotolive` jsou fotky nepatřící ke konkrétní lajně. Rozhodnout, jestli pro ně udělat zvlášť kategorii / galerii.
- [ ] ⏸ webbing themes picker — odloženo na neurčito (2026-07-01).

### slackTV — follow-upy
- [ ] **Cold-cache `/tv` dělá ~19 sekvenčních HTTP volání** — při expiraci `tv.sections` (6 h) první návštěvník čeká ~5–10 s. Paralelizovat (concurrent HttpClient) nebo nahřívat cache cronem. (MED)
- [ ] **Hashtag slider „view on YouTube" míří na `/hashtag/{tag}` místo na search** — dead-end (~10 videí místo tisíců). Fix = jeden řádek: `url: 'https://www.youtube.com/results?search_query=' . rawurlencode($tag)`. (LOW, hotový recept)
- [ ] Hashtag taby bez klávesové navigace (arrow keys / roving `tabindex`). (LOW, a11y)
- [ ] Přidat další search query / kanál — TTL 6h má quota rezervu ~25×. Až bude YT kanál CAS, přidat jeho channel ID do `feed.youtube.channels`.
- [ ] Promyslet FB Page CAS jako další zdroj (jiný fetcher; Meta API).
- [ ] `highline_media` — externí odkazy (foto/video/článek na youtube / rajce / lezec), ne lokální fotky → potřebuje vlastní entitu + UI sekci na detailu. **Deferred**, viz `migration.md` § Mimo scope.
- Galerie: fotky z přechodů / hero momenty — netřeba akce teď; až budou user uploady s reálnými EXIF daty, kronika se zpřesní sama (legacy fotky mají datum = 1. napnutí lajny).

### Offline PWA — highline mapa v terénu (PLÁN)

> Stav: **návrh k doladění.** Sepsáno session 2026-06-02. Záměr: highliner v horách bez signálu si appku nainstaluje jako PWA a má offline **celou ČR** (podkladová mapa + body highlinů + popupy + časová osa). Nic se zatím nestaví — nejdřív dorozhodnout otevřené body níž.

#### Rozhodnutí, která už padla
- **Zůstává Leaflet, nemění se za MapLibre.** Použít `protomaps-leaflet` (renderuje vektorové PMTiles přímo v Leafletu, canvas). Vymění se jen podkladová vrstva — `L.tileLayer(OSM)` → vektor nad pmtiles. Všechny 4 mapové controllery (`map`, `line_detail_map`, `user_denik_map`, `line_form_map`) i veškerý marker/popup/timeline kód zůstávají beze změny.
- **Instalace PWA = souhlas se stažením celé ČR.** Po instalaci se automaticky na pozadí stáhne `cz.pmtiles`. Install je vědomý úkon → bereme ho jako „chci to celé offline".
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
- Po instalaci stáhnout `cz.pmtiles` → **OPFS** (Origin Private File System, iOS 16.4+ OK), progress bar.
- Custom pmtiles source čte rozsahy z OPFS přes `File.slice()` → range requesty lokálně.
- Resume při přerušení (foreground download); fallback na online range requests dokud není staženo.

**Etapa 4 — prod parity** (~půlden)
- Caddy: servírovat `.pmtiles` s `Range` + MIME `application/octet-stream` (`infra/Caddyfile` + `make deployCaddy`).
- `.pmtiles` **mimo git** (desítky MB) → stáhnout/vygenerovat při deployi. Update `deploy.md`, `deploy.sh`, paměť.

#### Odhad velikosti (ballpark, změřit po reálném extractu)
- maxzoom 14 ≈ ~80–150 MB — dobrý lokální detail (vidíš ke skalám)
- maxzoom 13 ≈ ~40–60 MB — terénní kontext, hrubší zoom

#### OTEVŘENÉ ROZHODOVACÍ BODY (k doptání)
1. **Styl podkladu / vrstevnice.** `protomaps-leaflet` basemap je „city" styl bez vrstevnic / hillshade / terénu — pro highliny v horách možná chceme topo. **Tohle je největší otevřená otázka.**
2. **maxzoom** — z13 (lehčí) vs z14 (detailnější).
3. **Co všechno je offline.** Jen `/mapa`, nebo i detail stránky `/lajna/{slug}` (254 stránek)? Fotky/audio offline spíš ne.
4. **iOS UX** — když nejde chytit install event, kdy přesně spustit download?
5. **Aktualizace pmtiles** — jak často přegenerovat a jak řešit cache-busting / verzování.
6. **Stažení jen na wifi?** Desítky MB — nabídnout potvrzení / detekci typu připojení?
7. **PWA scope** — instalovatelná celá appka, nebo „nainstaluj si mapu"?

### Doporučení obsahu od userů (DEFERRED)

> Stav: **myšlenka k dořešení.** User si není jistý, jestli celý koncept dává smysl — implementaci odložit. Sepsáno session 2026-06-11.

- Záměr: přihlášený user vloží YouTube odkaz (video / kanál / playlist) jako tip; po **moderaci adminem** se schválené objeví v sekci „Doporučeno komunitou".
- Rozhodnuto v konzultaci: sbírat videa + kanály + playlisty; schválené → vlastní sekce; default (k potvrzení) — doporučovat jen přihlášení, nic bez schválení adminem.
- Technika: **oEmbed** na validaci + metadata — 0 kvóty, bez klíče. Nová entita `ContentSuggestion` (user, typ, YT id, metadata, status pending/approved/rejected). Admin moderační fronta.
- **OTEVŘENÉ (blokuje to):** kde žije seznam zdrojů sekcí — config (`feed.yaml`) vs DB + admin. Nerozhodnuto; proto je feature odložená.

---

## Hotové (archiv)

### Analytics
- [x] **Matomo self-hosted analytics** (2026-07-09) — `analytics.slack.cz`, native PHP+MariaDB (ne Docker, sedí na existující prod stack), cookieless tracking gated na `app.environment == 'prod'`. Provisioning v `scripts/setup-server.sh`/`check-server-env.sh` + `infra/Caddyfile`, rollout popsaný v `deploy.md` § Analytics. Cestou nalezen a opravený leak (DB credentials soubor krátce dostupný přes web — přesunuto mimo Caddy `root`). Rollout kompletně dokončen a ověřen na provozu.

### Registrace + profil + ochrana osobních údajů (2026-07-09)
- [x] **Nick přesunut do registrace** — `RegistrationForm` má nově `nick` (`NotBlank` + `Length(max:30)`, `UniqueEntity` na `User` hlídá kolizi), `UserForm` (`/profil/uprava`) ho ztratil. **Nick se dá nastavit jen při registraci a pak už ho v appce nejde změnit** — vědomé rozhodnutí, ne opomenutí.
- [x] **Redirect po registraci → rovnou na `app_profile_edit`**, flash zpráva přepsaná z anglického Symfony-scaffold textu na český: „Děkujeme za registraci! Jestli chceš, můžeš o sobě uvést víc informací. Ať se daří 🍀". Email-verifikační flow (`verifyUserEmail()`) záměrně beze změny (mimo scope).
- [x] **`agreeTerms` checkbox napojený na reálnou stránku** — dřív odkazoval na neexistující „podmínky používání". Přelabelováno na souhlas se zpracováním osobních údajů, link na `/ochrana-osobnich-udaju` (`form_row(..., {label_html: true})` s `path()`, ne natvrdo napsaná URL). Validační hláška přeformulovaná odpovídajícně.
- [x] **Nová stránka `/ochrana-osobnich-udaju`** (`app_privacy`, `PagesController::privacy()`) — plain-language shrnutí (ne právní review): co je povinné (email, nick) vs. nepovinné (jméno/příjmení/město/ročník/telefon), co je veřejné vs. jen pro přihlášené, že se nic nepředává třetím stranám (Matomo self-hosted bez cookies), poznámka k legacy účtům, kontakt `info@slack.cz`. Prolinkovaná z `/o-projektu` a z registračního checkboxu. Text prošel dvěma koly oprav podle reality appky (nick byl označen jako nepovinný než se dal povinným, jméno/příjmení chybělo v seznamu veřejných údajů — obojí opraveno po review userem).
- [x] **Info box v `/profil/uprava`** — Bootstrap `alert` + inline SVG ikonka (stejný vzor jako ostatní ručně kreslené ikony v appce), text „Vyplň dle libosti. Všechny údaje jsou nepovinné."
- [x] **Telefon — viditelnost omezená na přihlášené** — dřív nikde veřejně nezobrazený (mrtvý údaj), teď na `/denik/{id}` gated `{% if app.user %}` (komunitní sdílení kontaktu, ne veřejné — legacy slack.cz to takhle měl). Ostatní profilová pole (jméno/příjmení/město/ročník/nick) veřejná bez podmínky.
- [x] **Telefon — validace formátu** — iterováno přes 3 kola zpětné vazby na finální `Assert\Regex` `/^(\d{9}|(\+|00)?\d{12})$/`: buď přesně 9 číslic bez prefixu, nebo `+`/`00` + přesně 12 číslic (prefix se **nesmí** pojit s 9místným číslem — to byl mezikrok, který prošel testem a neměl). HTML `pattern` + `title` atribut zrcadlí server-side regex/hlášku, takže i nativní prohlížečová „Please match the requested format" bublina je srozumitelná.
- [x] **Registrační formulář — pixel fix** — `NewPasswordType` (2 sub-pole) měl zdvojený `mb-3` po druhém inputu (vlastní + outer `<fieldset>` row). Symfonyho `row_attr: {class: ''}` override nefunguje (Twigí `default()` filtr bere prázdný string jako "chybí"), funkční fix byl `row_attr: {class: 'mb-0'}` na fieldsetu.

### Line form — hodnocení, titulní fotka, GPS picker (2026-07-09)
- [x] **Hodnocení (`rating`, 1–5★)** — plnohodnotně v audit systému (`LineCrudController::FIELDS`/`snapshot()`/`applySnapshot()`), na rozdíl od cover fota jde přes proposal queue jako ostatní pole. Renderuje se jako klikací **`rating-picker`** star-widget (sdílený partial `templates/_partials/_rating_picker.html.twig` + `rating_picker_controller.js`), ne `<select>` — nasazeno stejně na `LineCrossingForm` (hodnocení přechodu). Field type je `HiddenType`, ne `ChoiceType` — vykreslování přes `form_widget()`, protože Symfonyho `form_end()` **tiše dorenderuje nevykreslené pole navíc jako `<select>`** (`render_rest`), což vedlo k duplicitnímu `name` atributu, dokud se na to nepřišlo. Vizuál sladěný s `.hl-rating-large` z detailu lajny přes tokeny (`$brand`, `var(--bs-border-color)`), ne přes půjčené třídy (BEM pravidlo).
- [x] **Titulní fotka (`coverPhotoFile`)** — unmapped pole, **mimo celý trust/proposal systém** (aplikuje se vždy okamžitě, stejně jako existující `/lajna/{slug}/fotky/pridat` flow). Cestou odhalen a opravený bug: VichUploader directory namer pro `line_photo` potřebuje `line.id` → v `new()` (vytvoření lajny) musí být lajna flushnutá **před** persist+flush fotky (dřív jeden společný flush → `Directory name could not be generated: property line.id is empty`).
- [x] **`Line::height` přestal předvyplňovat formulář nulou** — `private int $height = 0` → `?int $height = null` (PHP-nullable, DB sloupec zůstává NOT NULL — stejný vzor jako `name`, hlídá `Assert\NotNull` + form `required`). Cestou doplněn null-safe cast do `LineCrudController::applySnapshot()` i do **`SyncLineFromAuditCommand`** (zrcadlová kopie, kde navíc chyběl i `rating` z předchozího bodu — doplněno).
- [x] **Read-only délka + live redraw při tažení bodu** — `<output>` se live-updated délkou vedle výšky (wiring mimo Stimulus controller element přes „by-ID" trik, stejně jako lat/lng inputy). Marker měl jen `dragend` listener (čára/délka se překreslily až po puštění) → přidán `drag` listener, `refreshLine()` teď běží při každém pohybu myši (`setLatLngs()` místo remove+recreate polyline, kvůli frekvenci). Mapový distance badge vidět jen ve fullscreenu (`.hl-form-map:fullscreen .hl-form-distance`), mimo fullscreen stačí `<output>`.
- [x] **Iniciální zoom/fit + refit po fullscreenu** — `fitToKnownPoints()` sestaví bounds ze všech umístěných bodů (bod 1, bod 2 i parkování — dřív jen natvrdo `zoom=15`, u delší lajny ořízlo druhý bod/parkování mimo výřez), volá se při `connect()` i při opuštění fullscreenu (jiná aspect ratio → starý fit neseděl).
- [x] **Wheel-zoom citlivost** (`assets/map_scroll_zoom.js`, sdílené napříč 4 vloženými mapami — homepage/deník/detail lajny/form lajny) — `performZoom()` byl 1:1 kopií Leafletí pixel-akumulace/sigmoid dampening matiky (naladěná na trackpad pinch), s klasickým kolečkem myši výrazně necitlivější než tlačítka. Zjednodušeno na jeden krok `zoomDelta` na debounced gesto — stejný krok jako tlačítka.

### Mapa
- [x] **Homepage: auto-advance na dalším přechodu vytahoval scroll stránky** (2026-07-09) — `hp_map_controller.js`'s `activate()` volalo `item.scrollIntoView({block:'nearest'})`, což prolézá **všechny** scrollovatelné předky včetně `document` — i když má `.hp-map__crossings` vlastní `overflow-y: auto`. Nahrazeno `scrollItemIntoView()`, co scrolluje ručně jen ten vnitřní box.
- [x] **Deník už není „highline deník"** (2026-07-09) — kategorizace přechodů byla mylně vázaná na highline, přitom `LineCrossing` pokrývá všechny typy lajen. `UserRepository::findDiaryRows()` `highlineCount` → `lineCrossingCount`, `/denicky` sloupec „Highline" → „Přechody", tab hash `#highline` → `#denik` (`user_diary.html.twig`). „Longline deník" (samostatný legacy systém, jiná tabulka) beze změny.
- [x] **Desktop: panel na celou výšku sloupce** (2026-07-09) — `.map-panel` na desktopu bez drag úchytu a bez JS výšky, prostě vyplní `.map-side` (`media-up(md)` v `_map.scss`); collapse tlačítko na desktopu schované (sbalování zůstává mobilní koncept). `map_panel_controller.js` má `isMobile()` guardy, aby na desktopu nikdy nenastavil inline pixel výšku.
- [x] **BUG: mapa nejde zoomovat Ctrl+kolečkem** (2026-06-16) — sdílený helper `assets/map_scroll_zoom.js` (`enableCtrlScrollZoom`): inline mapa = Ctrl/⌘ + kolečko zoomuje + hint overlay; fullscreen = plain kolečko. Nasazeno na `hp_map`, `user_denik_map`, `line_detail_map`, `line_form_map`. Dedikovaná `/mapa` záměrně ponechána se zoomem samotným kolečkem.
- [x] **Redesign `/mapa` — sreality styl** (2026-07-03) — levý sloupec `.map-side`: box Lajny ve výřezu (abecedně, default otevřený) + box Přechody (default sbalené). Nový `line_feed_controller.js`, `map_controller` broadcastuje `slack:viewport-lines` na `moveend/zoomend`. Žádný nový backend (`app_line_map_data`, filtr přes `map.getBounds()`).
- [x] **Mobil: přechody na `/mapa` → tabový `.map-panel`** (2026-07-07) — boxy Lajny + Přechody sloučené do jednoho panelu s taby (desktop i mobil stejně). Nový `map_panel_controller.js` (taby + collapse + drag výška + gradient, sessionStorage). Bloky přejmenované na skutečný BEM (`map-panel__*--*`, `line-feed__*`, `crossing-feed__*`).
- [x] **Redesign boxu Lajny** (2026-07-07) — vlastní BEM blok `.line-feed`. Výška těla PEVNÁ, user-driven (drag úchyt / šipky), fit-to-content zavržen (nekonečná resize smyčka s mapou). `/mapa` bez patičky a bez svislého scrollu.
- [x] **Clustering markerů** (2026-07-07) — `leaflet.markercluster`, `staticLayer` = `L.markerClusterGroup` (radius 50, `disableClusteringAtZoom` = `LINE_MIN_ZOOM`). Vlastní ikona `.map-cluster` (BEM). Focus ze sidebaru přes `zoomToShowLayer`.
- [x] **Opravy z code review `.map-panel`** (2026-07-08) — sdílený `assets/breakpoints.js` (`isMobile()`, přesně `media-down(md)`) místo hardcoded matchMedia; sessionStorage try/catch; re-clamp výšky na resize; SVG ikony přejmenované na skutečný BEM; české komentáře v `_map.scss` přeložené do EN.
- [x] **Přeškrtnuté oko v tab-headeru Přechody** (2026-07-08) — `map-panel__tab-eye`, button nese buď počet přechodů, nebo oko.
- [x] **Taby panelu = Bootstrap buttony** (2026-07-08) — `btn btn-outline-primary btn-sm`, JS řídí Bootstrap `.active`. Touch fix pro sticky `:hover` po tapu.
- [x] **Kompaktní výpis přechodů + klik → marker** (2026-07-08) — dvouřádkové hairline řádky, klik → `slack:crossing-focus` → focus markeru, skryté markery se klikem zviditelní. Vybraná položka podbarvená (`__item--active`).
- [x] Linie mezi `point1` a `point2` na hlavní mapě (viditelná od zoom ≥ 14).
- [x] Mapové zoom controly na `bottomright`.
- [x] Crossing news-bar sidebar na `/mapa` — eye toggle, collapse, time-travel režim.
- ❌ ~~Filtry na `/mapa` (typ, délka, výška)~~ — nebude (2026-07-01, rozhodnutí usera).

### Mobile-first Bootstrap rewrite (2026-07-01 → 07-04)
Pivot: mobil = priorita č. 1, custom CSS (30 partialů) označen jako nepoužitelný (feedback od kamaráda + usera). Desktop skoro nikdo.

- [x] **Fáze 0 — Bootstrap základ** — `symfonycasts/sass-bundle` + Bootstrap 5 JS+Popper přes importmap. Brand vrstva `assets/styles/brand.scss` (accent `#e1005b`). Wiring v `app.js`: Bootstrap CSS → legacy `app.css` → `brand.scss` (poslední = přebíjí).
- [x] **Fáze 1 — Shell + mobil** — `user_toolbar.twig` na Bootstrap navbar + offcanvas, fixní výška hlavičky (`--header-height: 72px`). Mobil header jen „Přihlásit"; profil/odhlášení v offcanvasu. Flash → Bootstrap alerts.
- [x] **Fáze 1 — bugfixy po headless kontrole** (2026-07-03) — hlavička na mobilu se lámala na 2 řádky (logo scaling fix); search panel ujížděl mimo obrazovku (přesunut do offcanvasu).
- [x] **Mobilní header = jen logo + hamburger** (2026-07-03) — search i „Přihlásit" v offcanvasu (inline search varianta + `w-100` tlačítko).
- [x] **Fáze 1 — cleanup** (2026-07-03/04) — smazán mrtvý `nav_controller.js` + `_nav.css`; `_buttons.css`/`_flash.css` smazány (Bootstrap `.btn` převzal vzhled).
- [x] **Breakpoint systém** (2026-07-03) — `assets/styles/_breakpoints.scss` jediný zdroj breakpointů (Bootstrap škála), mixiny `media-up($bp)`/`media-down($bp)`. Žádné nové hardcoded px v `@media`.
- [x] **Fáze 3 — migrace zbylých stránek, `app.css` smazán** (2026-07-04) — homepage/about/data_report, /tv taby, tokeny+base do `brand/_shell.scss`, zbylých 11 partialů portnuto do `brand/`. **`app.css` + 18 souborů smazáno** — appka má jediný stylesheet.
  - [x] **Detail lajny → Bootstrap** (2026-07-04) — `row/col` layout, staty `col-lg-4 order-lg-2`, `brand/_line-detail.scss`. Smazán celý `_line.css`.
  - [x] **Formuláře → Bootstrap** (2026-07-04) — všech 9 formulářových stránek + lajna form → `form-page` + card + `form_row()`. Nové `brand/_forms.scss` + `brand/_line-form.scss`.
  - [x] **Tabulky → Bootstrap** (2026-07-03) — `/denicky` + `_longline_crossings` partial. Theme vrstva `.table` v `brand.scss`.
  - [x] **Deníček + panel→card všude** (2026-07-04) — `user_diary.html.twig` přepsán, všichni konzumenti `.panel` → `.card`, theme rozsekaná do `assets/styles/brand/` partialů.
- [x] **Docs pryč z menu** (2026-07-01) — odkaz přesunut do `/o-projektu`.

### Refaktoring a jazykové sjednocení
**Pravidlo: kód anglicky, veřejné URL česky** — appka je pro české uživatele.
- [x] **Rename `Highline` → `Line`** (2026-06-21) — entita + třídy, tabulky, routy, URL. Slovo „highline" záměrně zůstalo jako **disciplína**. 3 migrace, ověřeno lint/router/schema.
- [x] **CSS split dokončen** (2026-06-21) — `app.css` monolit → 30 komponentních partialů.
- [x] **`ImportHighlineCrossingsCommand` → `ImportLineCrossingsCommand`** + doc rename (2026-06-21).
- [x] **denik→diary + mapa→map cluster anglicizován** (2026-06-30) — šablony, Stimulus, CSS, controller metody, route names. URL paths + UI text zůstávají CS.
- [x] **Dohledat zbylé CS identifikátory** (2026-07-01) — cílený sken našel jediný zbylý: `redirectToDenik()` → `redirectToDiary()`. **Jazykové sjednocení tímto uzavřeno.**
- [x] **Veřejné URL zpět do CS** (2026-06-30) — `/line/`→`/lajna/`, `/crossing/`→`/prechod/`, verby česky, auth cesty česky. Jména rout zůstala EN. Mezinárodní termíny (`/longline`, `/docs`, `/wiki`, `/tv`, `/intro`, `/data-report`) ponechány EN.

### Legacy import fotek
Foto stažené z legacy webu do `../old-slack-cz`. Highline import hotový (`make importLegacyPhotos`).
- [x] **`app:import:line-photos`** (2026-06-16) — cover + galerie → WebP mastery. Ověřeno disk × legacy DB × živý web, 0 ztraceno.
- [x] **Cover fotka lajny** (2026-06-16) — self-hostovaná přes `Line.coverPhoto`, legacy hotlink zrušen.
- [x] **Nasadit `@photos` cache blok** z `infra/Caddyfile` na betu (ověřeno 2026-07-05).

### Galerie
- [x] **Samostatná stránka `/galerie` — kronika po letech** (2026-07-04) — roční sekce se scrollspy, justified grid (`line_photo.width/height`, nulový CLS). Liip filtr `gallery_thumb`. Ověřeno Playwright.

### Rané funkce a migrace (do 2026-06-16)
- [x] WebP/HEIC upload pipeline — `App\Service\PhotoNormalizer`, každý upload → WebP master, EXIF datum/GPS do DB.
- [x] Veřejný rozcestník deníčků `/denicky` — přehled aktivních uživatelů, fulltext filtr + sort.
- [x] Longline deník — tab na `/denik/{id}`, entita `LonglineCrossing` + import + CRUD.
- [x] slackTV → `/tv` stránka + redesign na sekce (Kanály / Playlisty / Hashtagy), YouTube Data API + cache.
- [x] Homepage panely — slackTV strip dole; „Highline mapa" + „Poslední přechody" sloučeny do jednoho panelu Mapa.
- [x] Cleanup — smazán `App\Old\Entity\Uzivatel`, oživen CI workflow + smoke testy.
- [x] Migrace legacy dat — kompletní (highlines/users/crossings). Detail v `migration.md`.
- [x] Gender kompletně odstraněn (2026-06-12).
- [x] Edit profile — `/profile/edit`.
- [x] Infra: Caddyfile v repu + drift gate (2026-05-11).
- [x] Galerie sociální vrstva + AJAX likes (2026-05-11) — `LinePhotoLike`/`LinePhotoComment`.
- [x] Audit historie + stack-pop curation + verifikační merge (2026-05-10) — `/line/{slug}/history`, `LineEdit`. Detaily v `docs/line-edits.md`.
- [x] Slug stability na verified + rename slug regen na unverified (2026-05-10).
- [x] Parking GPS import (2026-05-10) — `Line.parkingLatitude/Longitude`.
- [x] Line CRUD + verifikační/proposal flow (2026-05-10) — `LineCrudController`, `LineEdit` audit log. Detaily v `docs/line-edits.md`.
- [x] Crossing form (CRUD pro přechody) — edit/delete vlastních přechodů z deníku.
- [x] Deník uživatele `/denik/{id}` — hlavička, mini-mapa, tabulka přechodů.
- [x] Single source of truth pro recent crossings — `LineCrossingRepository::RECENT_LIMIT`.
- [x] Intro splash overlay — fullscreen logo + „Vstoupit".
- [x] Line detail `/line/{slug}` — slug, stat panel, mini-mapa, seznam přechodů.
- [x] Line `point1`/`point2` GPS import z legacy `gps`.
- [x] User import — 440 nových + 1 enriched + 6 dropped.
- [x] Crossings import — 993/995.
- [x] `Makefile` target `legacyImport`.
- [x] „Poslední přechody" panel na indexu.
- [x] Line import 254/254 z legacy DB do Postgres.
- [x] Mapa `/mapa` s Leaflet + OSM.
- [x] Light theme s magenta accent (`#e91e63`).
- [x] Index page se 2 panely (mini mapa + slackTV teaser).
- [x] slackTV — YouTube Data API v3 fetcher s mock fallbackem a cache TTL.
- [x] About page `/o-projektu`.
- [x] Restrukturalizace legacy mapování — `App\Entity\Old\*` → `App\Old\Entity\*`.
- [x] `.env` deklaruje `YOUTUBE_API_KEY=` (prázdné, dokumentace).
