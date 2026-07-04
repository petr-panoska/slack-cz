# Architektura

Symfony 7.4 LTS aplikace (composer min **PHP 8.2**; reálně dev+CI image jede na **8.4**, prod **8.3** — viz `roadmap.md`). Vyvíjí se v Dockeru (Compose); staging na <https://beta.slack.cz> (Hetzner CX22, native PHP 8.3 + Postgres 16 + Caddy — viz `deploy.md`), cutover legacy `slack.cz` ještě neproběhl.

Dev běží na **native docker-ce** (Linux) — žádný Docker Desktop, žádný `linuxkit`, žádný FUSE wrapper. Bind mount je raw `ext4` přímo z hostu, atomic-rename funguje korektně (důležité, viz historie editací; DD's `fakeowner` FUSE měl invalidation bug).

> **Infrastruktura, prod provoz, deploy flow, CI workflow, skripty** → `deploy.md` (sekce „Infrastruktura na první pohled" je 2-minutový rozcestník).
> **Operační cookbook** (`make` targety, časté konzole příkazy, smoke testy) → `dev.md`.

## Stack

| Vrstva | Komponenta | Host port |
|---|---|---|
| Web | Apache 2.4 | **8000** (pevný) |
| App | PHP-FPM (image `slack-cz-php`, Symfony 7.4, PHP 8.4 alpine) | – (jen interní 9000) |
| DB nová | PostgreSQL 16 (Doctrine EM `default`) | dynamický (compose ho přidělí) |
| DB legacy | MySQL (Doctrine EM `old`, čte `slackcz_44953.sql` dump) | – (interní) |
| Mail | Mailpit (UI 8025, SMTP 1025) | dynamické pro UI i SMTP |
| Adminer | DB UI | **8080** (pevný) |

Aktuální dynamické porty: `docker compose port database 5432`, `docker compose port mailer 8025` atp.

## Struktura kódu

```
src/
  Controller/       # PagesController, LineController, LineCrudController, LinePhotoController,
                    # CrossingController, LonglineCrossingController, UserController, MarkdownSectionController, Registration/Reset/Security
  Entity/           # NOVÉ entity (Postgres, EM default): User, Line, LineCrossing, LonglineCrossing,
                    # LineEdit, LinePhoto, LinePhotoComment, LinePhotoLike, ResetPasswordRequest
  Enum/             # LineType, CrossingStyle, LineEditStatus
  Service/          # PhotoNormalizer (magick + exiftool: datum/GPS/rozměry → DB, soubor → WebP master), NormalizedImage
  Feed/             # plochý homepage feed + cache + dispatcher (mock fallback)
    Tv/             # sekcový /tv feed: FeedGroup, TvFeedInterface, YoutubeClient, paginace + cache
  Form/             # Symfony forms (LineForm, LineCrossingForm, LonglineCrossingForm, RegistrationForm, ...)
  Legacy/           # UserMergeMap (singleton — duplicit-email merge mapa; plní app:import:users, čte :line-crossings + :longline-crossings)
  Markdown/Section/ # MD subsystém pro /docs + /wiki, čte z lokálního checkoutu (detail v § Markdown sections níž)
  Repository/       # Doctrine repos (LineCrossingRepository::RECENT_LIMIT je single source of truth)
  Security/         # EmailVerifier
  Command/          # Console commands: app:import:*, app:admin:grant, app:edit:sync-from-history,
                    # app:user:list, app:user:reset-password, app:photo:backfill-dimensions
```

### Pojmenování: entita `Line`, disciplíny "highline" a "longline" a další

- Entita pro lajny se jmenuje **`Line`**
- `LineType::Highline` (enum case — highline vs midline vs longline vs waterline),
- legacy import command `app:import:line-crossings` (zapisuje do `LineCrossing`, třída je `ImportLineCrossingsCommand`,
- dál existuje `app:import:longline-crossings` - to je speciální případ a v podstatě nesystémové řešení pro zachování původního longline deníku uživatelů (nesystémové protože entita Line umožňuje zadávat i typ `LineType::Longline`)
- UI labely a `#highline`/`#longline` deep-link hash na `/denik/{id}`, tohle je taky nesystémové, protože `#highline` ukazuje na všechny deník všech typů z `LineType`
- legacy data (MySQL tabulka `highline`, souborový strom `line/high/<id>/`, prod URL `…/highlines/detail/{id}`).

Pravidlo: **`Line` = entita v novém schématu; „highline" = disciplína / legacy.**

### Důležité — legacy data se čtou přes DBAL, ne přes ORM

Legacy MySQL se **nemapuje na ORM entity**. Import commandy (`app:import:*`) čtou z legacy přes DBAL connection `old` raw SQL (autowire `doctrine.dbal.old_connection` + `fetchAllAssociative`) a zapisují nové `App\Entity\*` přes default (Postgres) EM. Díky tomu default EM o legacy tabulkách vůbec neví a `make:migration` je nikdy nezahrne do Postgres schématu.

```yaml
# config/packages/doctrine.yaml — jen DBAL connection, žádný ORM entity_manager nad legacy
dbal:
    connections:
        old:
            url: '%env(resolve:OLD_DATABASE_URL)%'
```

Pravidlo: **legacy tabulky čti raw SQL přes `doctrine.dbal.old_connection`, nemapuj je na ORM entity.**

## Frontend

- **Asset Mapper** + **Stimulus** + **Turbo Drive** (Symfony stack; jediný „build step" je Sass — `symfonycasts/sass-bundle`, `make dcSassBuild` / `make dcSassWatch`)
- **Importmap** (`importmap.php`) drží: stimulus, turbo, leaflet, **bootstrap** (JS + Popper) a `bootstrap/dist/css/bootstrap.min.css`
- Turbo je aktivně zapnuté — link kliky a form submity jsou frame swapy, ne full reloady. Předpokládá to, že stránky extendují `base.html.twig` a vrací 30x redirecty po POST.
- Stimulus controllery v `assets/controllers/`:
  - `hello_controller.js` — placeholder z generátoru
  - `csrf_protection_controller.js` — vendored z Symfony Flex recepty (přišel s 7.4): stateless CSRF, dopočítává `framework.csrf_protection.check_header` token na form submitech. Neměň ručně.
  - `map_controller.js` — hlavní Leaflet mapa: highline markery (`staticLayer`), emoji markery posledních přechodů (`usersLayer`, přepínatelné okem ze sidebaru), time-travel režim (`timelineLayer`). Leaflet zoom je přesunutý na `bottomright`, aby se nemlátil se sidebarem vlevo nahoře.
  - `hp_map_controller.js` (identifier `hp-map`) — homepage mapa v panelu Mapa: ukazuje jen aktivní přechod, vykreslí reálnou linku + emoji walker animaci po lajně a auto-showcase cyklus přes posledních N přechodů. Sdílí emoji paletu (`assets/user_emoji.js`) s `map_controller`. Detailní chování v § *Hotové features → Index page*.
  - `line_detail_map_controller.js` (identifier `line-detail-map`) — slim mini-mapa pro detail lajny: jeden pin nebo polyline mezi `point1` a `point2` GPS (pokud má lajna oba body)
  - `line_form_map_controller.js` (identifier `line-form-map`) — 2-endpoint GPS picker pro line form. Alternující klik 1→2→1, oba markery draggable, polyline + live haversine length overlay. Sync se 4 input poli (point1Lat/Lng + point2Lat/Lng) oboustranně.
  - `user_diary_map_controller.js` — mini-mapa na `/denik/{id}` se všemi unikátními highlines, co user prošel
  - `crossing_feed_controller.js` — vertikální „news bar" sidebar na `/mapa`: list posledních N přechodů, dvě tlačítka v hlavičce (oko = visibility emoji markerů, šipka = collapse panelu), reaguje na time-travel režim (změní se v okno -7 dní zpět od virtuálního času)
  - `search_controller.js` — globální vyhledávání lajn v hlavičce
  - `intro_controller.js` — slackvibes 📻 audio player; persistent přes Turbo přes `data-turbo-permanent`. Detaily v `docs/audio-player.md`.
  - `live_slug_controller.js` — live URL preview pro unverified line edit form: slugify-uje typed name a updatuje `<code>` preview. Server na save přegeneruje slug přes `makeUniqueSlug`.
  - `photo_like_controller.js` — like button na `/lajna/{slug}/fotky/{id}`; intercept-uje form submit, POST přes fetch s `Accept: application/json`, endpoint vrátí `{liked, count}` JSON, controller updatuje classy + `aria-pressed` + icon + count. Při fetch failu padá zpět na nativní form submit (graceful degradation).
  - `tv_controller.js` — click-to-play facade na `/tv`: náhled karty nahradí `youtube-nocookie` embedem až po kliknutí (neload 24 iframů + privacy). Viz § *Feed (slackTV)*.
  - `tabs_controller.js` — **obecná** tab komponenta: přepíná `aria-selected` + `hidden` panel podle indexu (+ Bootstrap `.active` na tabu), volitelný deep-link přes URL hash (`data-tabs-hash-param`). Vzhled dodává Bootstrap (`nav-underline` / `nav-pills` s brand theme v `_tabs.scss`). Použito na `/tv` (hashtag taby) a `/denik/{id}` (Highline/Longline).
  - `tv_more_controller.js` — AJAX „načíst další" stránkování sliderů na `/tv` (`/tv/more`, `pageToken`).
  - `data_table_controller.js` — generická filtrovatelná + řaditelná tabulka (fulltext filtr, klik na `th[data-sort-type]` řadí, `aria-sort`, count badge). Použito na `/denicky`.
  - `line_feed_controller.js` — box „Lajny ve výřezu" v sidebaru `/mapa`: čistý view, poslouchá `slack:viewport-lines` broadcast z `map_controller` (viz event bus níž), klik na řádek → `slack:line-focus`.
  - `gallery_nav_controller.js` — lišta roků na `/galerie`: scrollspy (rAF-throttled scroll listener zvýrazňuje rok právě pod hlavičkou + doscrolluje aktivní chip v liště) a klik na rok (`jump`). **Gotcha:** klik na in-page kotvu nesmí propadnout Turbu — to ho bere jako novou visit (refetch celé stránky + vlastní scroll, který přestřeluje), proto `preventDefault` + `scrollIntoView` + `history.replaceState`.

#### Cross-controller event bus

Mapový sidebar a mapa potřebují komunikovat napříč nezávislými Stimulus controllery. Používáme `document`-level CustomEvents:

| Event | Producer | Consumer | Payload |
|---|---|---|---|
| `slack:map-mode` | `map` | `crossing-feed` | `{ mode: 'recent' \| 'time-travel', date?, days? }` — feed podle toho přefetchuje data (sloty se debouncují 200 ms + AbortController) |
| `slack:users-visibility` | `crossing-feed` (eye button) | `map` | `{ visible: bool }` — mapa přidá/odebere `usersLayer` |
| `slack:viewport-lines` | `map` (na `moveend`/`zoomend`) | `line-feed` | `{ lines: [...] }` — lajny v aktuálním výřezu (box „Lajny ve výřezu") |
| `slack:lines-request` | `line-feed` (na connect) | `map` | replay posledního `viewport-lines` — jistí boot race při pořadí connectů |
| `slack:line-focus` | `line-feed` (klik na řádek) | `map` | `{ id }` — setView na lajnu + otevření popupu |

Stav přežívá Turbo navigaci přes `sessionStorage` (`slack.cz:map:feed-collapsed`, `slack.cz:map:users-hidden`, `slack.cz:map:view`). Map controller čte `users-hidden` přímo na bootu (ne přes event), aby nedocházelo k race condition s pořadím Stimulus connectů.
- **CSS = Bootstrap 5 + jedna vlastní Sass vrstva** (mobile-first pivot 2026-07-01, custom CSS monolit zahozen). Kaskáda ve `app.js`: `bootstrap.min.css` (importmap) → **`assets/styles/app.scss`** — jediný vlastní stylesheet. `app.scss` je **jen `@use` manifest** (žádné selektory!): shell (tokeny + base) → porty legacy stránek → Bootstrap theme vrstvy. Konvence:
  - **jedna komponenta = jeden flat `_*.scss`** v `assets/styles/` + řádek v manifestu (pořadí = kaskáda)
  - **breakpointy VŽDY přes mixiny** `media-up($bp)`/`media-down($bp)` z `_breakpoints.scss` (Bootstrap škála sm 576 / md 768 / lg 992 / xl 1200 / xxl 1400) — žádné hardcoded px v `@media`
  - **žádný surový Bootstrap vzhled** — default je polotovar; komponenty dostávají brand theme (paleta v `_variables.scss`: `$brand` `#e1005b`, theming přes Bootstrap CSS proměnné v `_shell.scss`, ne přes Sass `$primary`)
  - `_shell.scss` drží `--slack-header-h` (72 px fixní hlavička) + legacy `--color-*` tokeny, které ještě čtou portnuté partialy
  - build: `make dcSassBuild` (jednorázově) / `make dcSassWatch` (dev); **v dev pozor na `public/assets/`** — zkompilované assety z `asset-map:compile` stínují živé soubory, po změně assetů je přegenerovat nebo smazat
- Obrázky v `assets/images/` (logo, leaflet ikony, archivní artefakty)
- Audio v `public/audio/` (12 stop, 128 kbps stereo). Originály v `var/audio-original/` (gitignored).
- Google Fonts: **Space Grotesk** (400, 500, 600, 700) jako globální font na `body` — geometrický sans s charakterem, sedí k bold display logu. Linkovaný přes `<link>` tag v `base.html.twig`.

## Mapa — Leaflet

- **Lokace lajny = jen 2 kotvící body** (`point1Latitude/Longitude` + `point2Latitude/Longitude`, + volitelně parking). **Žádný** uložený `latitude`/`longitude` — to byl dřív denormalizovaný „střed", co u většiny legacy lajn dávno nesedělo (drift až 166 km, protože se přepočítával jen při re-save formuláře), zahozený v migraci `Version20260609120000`. Kde je potřeba jeden reprezentativní bod (tečka v deníku/feedu, odkaz na mapy.cz, úvodní střed mini-mapy), odvozuje se z **`point1`**: `Line::getLatitude()/getLongitude()` jsou ponechané jako **alias na point1** (kvůli Twig šablonám), read queries aliasují `h.point1Latitude AS latitude`. `deriveGeometry()` po submitu počítá už jen `length` (haversine z obou bodů).
- **Podklady:** OpenStreetMap (default) + **Esri World Imagery ortofoto** — přepínač pilulkou „Ortofoto"/„Mapa" na **všech** mapách. Sdílené v `assets/basemap.js` (`addBasemapToggle`) jako jediný zdroj URL/atribucí; homepage mapa startuje rovnou na ortofotu (`{ ortho: true }`).
- **Fullscreen** na všech mapách — ikonka v rohu (`assets/map_fullscreen.js`, `addFullscreenToggle`, sebe-úklid přes `map.on('unload')`); na `/mapa` jde do fullscreenu celý wrapper (zůstane feed + time-travel ovládání), jinde plátno mapy.
- **Linka mezi body na `/mapa`:** vlastní layer, viditelná až od **zoom ≥ 14** (`LINE_MIN_ZOOM`) — z pohledu na celou ČR by byla sub-pixelová; marker sedí na `point1`. Detail lajny ukazuje **oba body** (řádky „GPS bod 1 / bod 2" s odkazy na mapy.cz) + polyline na mini-mapě.
- Markery: defaultní Leaflet ikony — kvůli AssetMapperu jsme musely PNG (`marker-icon`, `marker-icon-2x`, `marker-shadow`) stáhnout do `assets/images/leaflet/` a předat URLs z Twigu jako `data-*` (klasický bundling problém)
- Zoom controly jsou na **`bottomright`** (default `topleft` koliduje se sidebarem `/mapa`); v time-travel módu se zoom navíc CSSkem zvedne nad time-travel panel. Basemap + fullscreen pilulky jsou v `topright` (na `/mapa` CSSkem posunuté pod tlačítko „Přehrát historii").
- Použití na 4 místech: full mapa `/mapa` (s sidebar feedem + time-travel), mini mapa v panelu na indexu (sdílí `map_controller`), mini-mapa detailu lajny (`line_detail_map_controller`), mini-mapa deníku (`user_diary_map_controller`)

### Recent crossings — single source of truth

Konstanta `App\Repository\LineCrossingRepository::RECENT_LIMIT` (default 10) určuje, kolik nejnovějších přechodů se zobrazí v UI. Sjednocuje tři místa, která jindy „žila vlastním limitem":

| Místo | Metoda repa | Tvar |
|---|---|---|
| Index page — sidebar přechodů v panelu Mapa | `findRecent()` | entity (server-render `<li>` s `data-*` geometrií pro `hp-map`) |
| `/mapa` emoji markery | `findRecentForJson()` | array (lat/lng + popup data) |
| `/mapa` sidebar feed | `findRecentForJson()` | array (sdílený endpoint `/mapa/feed`) |

Bez dedup by user — homepage list, emoji markery i sidebar zobrazují **stejné** přechody. Když má jeden user 3 ze 10 nejnovějších, mapa ukáže 3× jeho emoji na 3 různých lajnách (fan-offset stackuje pouze identické GPS).

Emoji paleta lajnerů je **jen živí tvorové** (žádné stromy/objekty — lajner je pohyblivá bytost) a je sdílená v `assets/user_emoji.js` (`USER_EMOJIS` + `emojiForUser`), importovaná do `map_controller.js` i `hp_map_controller.js`, aby stejný user mapoval na stejného tvora v obou mapách. Indexy 0–29 zůstaly shodné s původní paletou (stávající uživatelé si tvora drží).

Pro time-travel režim je separátní `findForFeedInRange(from, to)` (sidebar volá `/mapa/feed?date=YYYY-MM-DD&days=7`).

## Foto galerie — upload pipeline

Fotky lajn (`LinePhoto`, Vich storage `public/uploads/line/<id>/`, gitignored). Uploady jsou z 99 % z mobilu včetně iPhonů (HEIC). Každý upload — user i legacy import — projde **`App\Service\PhotoNormalizer`** (volá `magick` + `exiftool`, viz `docker/php/Dockerfile`):

1. **Vytáhne metadata** přes exiftool: `DateTimeOriginal` → `LinePhoto.createdAt`, GPS → `gpsLat`/`gpsLng`. Nic víc (ISO/model foťáku = balast).
2. **Normalizuje soubor**: HEIC/JPG/PNG → **WebP master**, auto-orientace, zmenšení na ≤ 2560 px delší hrana, q85, strip všech metadat.
3. **Uloží pixel rozměry masteru** → `LinePhoto.width`/`height` (z `getimagesize` nad výstupem, tj. po resize + rotaci). Potřebuje je justified grid na `/galerie` — poměr stran musí být známý před načtením obrázku (layout bez CLS). Řádky z doby před sloupci dorovnává **`app:photo:backfill-dimensions`** (čte mastery přes Vich storage; jednorázově po importu / na novém prostředí).

Důvod modelu „metadata do DB sloupců + čistý soubor": embedovaná metadata v souborech jsou pro web nepraktická a reencode (nutný kvůli HEIC/velikosti) je stejně zahodí. Tak vytáhneme ty 2 užitečné věci (datum + GPS) do DB a ukážeme je na detailu fotky; soubor je čistý.

`LiipImagine` (GD driver, WebP in/out) pak z WebP masteru generuje thumb/medium/full + `gallery_thumb` (inset 1600×480 — výška pro ~240px řádek galerie ve 2× retina, poměr stran zachovaný; cachované v `public/media/cache/`). Disk-conscious schválně — běžíme na nejlevnějším Hetzner VPS (40 GB sdílených). Ladicí knoby (`MAX_EDGE`, `QUALITY`) jsou konstanty v `PhotoNormalizer`. Originály nedržíme (jsou v mobilech lidí).

`Assert\File` na uploadu povoluje `jpeg/png/webp/heic/heif` do 30 MB (syrový vstup; downscale řeší velikost). HEIC detekuje `finfo` jako `image/heic` (libmagic v Alpine).

## Feed (slackTV)

Dvě paralelní vrstvy nad stejným YouTube Data API v3 (`googleapis.com/youtube/v3`). API key v `.env.local` jako `YOUTUBE_API_KEY` (gitignored); `.env` ji deklaruje prázdnou (dokumentace).

**1) Homepage teaser** — plochý feed (`FeedFetcherInterface`), panel na indexu (`pages/index.html.twig`), pár nejnovějších videí.

**2) Sekcová stránka `/tv`** (`TvController` → `app_tv` + AJAX `app_tv_more`, `pages/tv.html.twig`) — rozdělená na sekce **Kanály / Playlisty / Hashtagy**, každý zdroj = horizontální slider karet. `tv_controller.js` zůstává click-to-play facade (náhled → `youtube-nocookie` embed až po kliknutí, neload desítek iframů + privacy). VideoID se v Twigu derivuje z `FeedItem.id` (`yt:VIDEOID` → `|slice(3)`).

### Plochý feed (homepage)
- Config `feed.youtube.channels` (channel IDs) + `feed.youtube.queries` (search queries).
- `FeedFetcherInterface` → `CachedFeedFetcher` (cache.app, TTL 6 h) → `FeedFetcherDispatcher` (real když je key, jinak mock) → `YoutubeFeedFetcher` / `MockFeedFetcher`.
- Fallback kaskáda: real fetch → last-known-good (7 dní, jen z reálných úspěšných fetchů) → mock (necachuje se). Prázdný real fetch se cachuje jen 60 s, aby se po obnovení kvóty rychle vrátila reálná data.

### Sekcový feed (`/tv`)
- Config v `feed.yaml`: `feed.tv.channels` (`@handle`y — **v YAML escapovat jako `@@`** — samotné `@` je reference na službu), `feed.tv.playlists` (PL ids), `feed.tv.hashtags`. Přidání zdroje = jeden řádek; stránka i load-more endpoint jsou nad ním generické. Položka kanálu/playlistu je buď holé id, nebo mapa `{ id, sort }`, kde `sort: asc` = od nejstarších, `desc` = od nejnovějších (default). Mixovat formy lze; `@@` se un-escapuje i uvnitř mapy. Normalizuje `TvSource::fromConfig`.
- `src/Feed/Tv/`:
  - `FeedGroup` — pojmenovaná řada videí (`kind` channel/playlist/hashtag, `title`, `url`, `items`, `nextPageToken`). `key` = stabilní identifikátor zdroje (`channel:@x` / `playlist:PL…` / `hashtag:#x`), kterým AJAX adresuje další stránku.
  - `TvFeedInterface` — `sections()` (první stránka všech zdrojů, seskupené) + `page(key, token)` (další stránka jednoho zdroje).
  - `YoutubeClient` — raw wrapper: `resolveChannel` (`@handle` → uploads playlist přes `forHandle`), `resolvePlaylist`, `playlistItems`/`search` se stránkováním přes `pageToken`. `allPlaylistItems` projde **celý** playlist (loop přes `pageToken` dokud token nedojde — končí i na chybě API, kdy `call()` vrátí `[]`) pro oldest-first zdroje: `playlistItems` neumí reverse order a nejstarší videa jsou až na poslední stránce. Chyba volání → prázdný výsledek (logged), neshodí stránku.
  - `TvSource` — `{ id, oldestFirst }`, výsledek `fromConfig` (holý string nebo `{id, sort}`).
  - `YoutubeTvFeed` — skládá sekce; padlý zdroj se přeskočí, ne fatal. Oldest-first zdroj (`TvSource::oldestFirst`) stáhne celý výpis, otočí ho a stránkuje **lokálně** — page token je prostý integer offset počítaný od nejstaršího (stabilní i když přibydou nová videa), ne YouTube `pageToken`.
  - `CachedTvFeed` — decorator přes `cache.app`: cachuje celý balík (`tv.sections`) i jednotlivé load-more stránky (`tv.page.<md5>`). Stejná fallback kaskáda jako plochý feed (last-known-good 7 dní + mock SLACKHOVOR group jako poslední záchrana).
- **Stránkování**: první stránka každého slideru je server-side; „load more" karta volá `GET /tv/more?key=&page=` → JSON `{html, nextPage}` (`_tv_cards.html.twig`, sdílený s prvním renderem). Když zdroj dojde, button se v JS přemění na „Vše na YouTube" odkaz.
- Stimulus: `tv-more` (AJAX donačtení další stránky), `tabs` (hashtag taby — obecná komponenta, `.tabs-pills` varianta), `tv` (přehrávání).
- **Validace klíče:** `/tv/more` přijme jen klíč z `TvFeedInterface::knownKeys()` (nakonfigurované zdroje). `CachedTvFeed::page()` odmítne neznámý klíč ještě před cache i API voláním (+ `YoutubeTvFeed::page()` totéž jako defense-in-depth), takže veřejný endpoint nejde zneužít k pálení kvóty (search = 100 units) ani k zaplevelení `cache.app`.

### Quota economics

YouTube Data API daily free tier = **10 000 units / GCP project / den** (reset v PT půlnoc ≈ 09:00 CEST). Cena endpointů, které používáme:

| Endpoint | Cost | Kde |
|---|---|---|
| `search.list` | **100 units / call** | `feed.youtube.queries`, `feed.tv.hashtags` |
| `channels.list` | 1 unit / call | lookup uploads playlistu (handle/id) |
| `playlists.list` | 1 unit / call | název playlistu (`/tv`) |
| `playlistItems.list` | 1 unit / call | skutečná videa (kanály i playlisty) |

Drahé jsou **jen hashtagy/queries** (search = 100 units); kanály i playlisty stojí ~1–2 units bez ohledu na to, kolik videí stránka vrátí.

- **Plochý feed (homepage):** (queries × 100 + kanály × 2) × (86400 / TTL). Aktuálně 1 query, 0 kanálů, TTL 6 h = **400 units/den**.
- **Sekcový `/tv`** — jeden `tv.sections` miss: 5 kanálů × 2 + 2 playlisty × 2 + 5 hashtagů × 100 = **~514 units**; při TTL 6 h (4 missy/den) = **~2 056 units/den**. Load-more stránky jsou navíc, cachované per `(key, token)`: kanál/playlist +1–2 units/stránka, hashtag +100 units/stránka.
- **Oldest-first zdroj (`sort: asc`)** přitáhne při každém fetchi celý playlist (`ceil(videí/50)` units), ne jednu stránku — a to i u load-more, který otáčí celý výpis znovu (každá stránka cachovaná zvlášť). U našich malých kanálů 1–2 units/fetch, takže v rámci šumu; u stovek videí to roste lineárně.

Součet obou vrstev ≈ **2,5 k units/den** z 10 000 — pohodlná rezerva, ale **hashtagy nesou ~95 % nákladu**. Přidání hashtagu = +100 units × (86400/TTL)/den; přidání kanálu/playlistu prakticky zdarma.

**Historicky jsme limit přepálili** s `feed.youtube.queries: [#czechslackline, czech slackline, czech highline]` a `feed.cache.ttl_seconds: 1800` (30 min):
3 queries × 48 fetchů/den × 100 units = **14 400 units/den** — kvóta vyčerpaná každé odpoledne. Plus každý `bin/console cache:clear` během vývoje vyhodí `cache.app` a vyvolá další 300 units instantně. Comment v `config/packages/feed.yaml` má aktuální vzorec, používej ho při ladění queries listu.

### Recovery po vyčerpání kvóty / rotaci klíče

```bash
# vyhodit 60s "empty fetch" lockout, aby další request hned re-tryoval
docker compose exec -T php bin/console cache:pool:clear cache.app

# načíst nový YOUTUBE_API_KEY z .env.local
docker compose exec -T php bin/console cache:clear --env=dev
```

### GCP "new project" search gate (gotcha)

Čerstvě založený GCP projekt s povolenou YouTube Data API: `/channels` jede (1 unit, 200 OK), ale `/search` vrací **403 Forbidden** i s plnou kvótou. To je standardní GCP posture pro neověřené projekty — `search` je gated dokud (a) nezapneš billing, nebo (b) projde quota-extension request přes Cloud Console. Rotace klíče na nový projekt sama o sobě `/search` nerozjede.

Diagnostika v `var/log/dev.log`: `quotaExceeded` → starý projekt vyčerpal kvótu, počkat na PT reset; `accessNotConfigured` / 403 jen na `/search` → nový projekt čeká na verifikaci.

## Auth

- `App\Entity\User` (email, password, roles, isVerified)
- Symfony security s form_login (login_path/check_path: `app_login`)
- Reset password přes `symfonycasts/reset-password-bundle`
- Email verify přes `symfonycasts/verify-email-bundle`
- `UserRepository` implementuje `PasswordUpgraderInterface`
- **`ROLE_ADMIN`** — kurátorská role (mark verified, schvalovat proposals, mazat verified lajny). Granted přes `bin/console app:admin:grant <email> [--revoke]`. Aktuální admin: `p***@***l.com`. Symfony role hierarchy: ROLE_ADMIN ⇒ ROLE_USER auto.
- **Konzolové utility pro správu uživatelů**: `app:user:list` (id/email/nick/verified/active, volitelné `-s` substring filter, `--unverified`) a `app:user:reset-password <email|id>` (vygeneruje absolutní password-reset URL přes `ResetPasswordHelperInterface::generateResetToken()`). Workaround dokud nejede mailer na betě — host pro URL bere router z `framework.router.default_uri` (= `DEFAULT_URI` env). Návod v `dev.md` § *Účty / správa uživatelů* a `deploy.md` § *Reset hesla / aktivace účtu, když nechodí maily*.

## Routes (zkrácený přehled)

| Path | Name | Public? |
|---|---|---|
| `/` | `app_index` | ✓ |
| `/mapa` | `app_line_map` | ✓ |
| `/mapa/data` | `app_line_map_data` | ✓ JSON — všechny lajny (markery na mapě) |
| `/mapa/feed` | `app_line_map_feed` | ✓ JSON — N posledních přechodů (default `RECENT_LIMIT`); s `?date=YYYY-MM-DD&days=7` vrací time-travel okno |
| `/mapa/timeline-data` | `app_line_map_timeline` | ✓ JSON — vše pro time-travel playback (lajny + crossings chronologicky) |
| `/lajna/{slug}` | `app_line_detail` | ✓ |
| `/lajna/pridat` | `app_line_new` | `ROLE_USER` — formulář nové lajny (priority 10 kvůli kolizi s `/lajna/{slug}`) |
| `/lajna/{slug}/uprava` | `app_line_edit` | `ROLE_USER` — direct edit (owner of unverified / admin) nebo proposal (verified + non-admin); detail v `docs/line-edits.md` |
| `/lajna/{slug}/smazat` | `app_line_delete` | `ROLE_USER` — owner-of-unverified nebo admin |
| `/lajna/{slug}/overeni` | `app_line_verify` | `ROLE_ADMIN` — flag isVerified=true |
| `/lajna/{slug}/historie` | `app_line_history` | ✓ — veřejný audit log editů (APPLIED + REJECTED) |
| `/lajna/{slug}/historie/{editId}/smazat` | `app_line_history_delete` | `ROLE_ADMIN` POST — stack-pop poslední revize |
| `/lajna/{slug}/fotky/pridat` | `app_line_photo_new` | `ROLE_USER` — upload fotky |
| `/lajna/{slug}/fotky/{id}` | `app_line_photo_detail` | ✓ — photo detail (like, komentáře, prev/next) |
| `/lajna/foto/{id}/libi` | `app_line_photo_like` | `ROLE_USER` POST — toggle like |
| `/lajna/foto/{id}/smazat` | `app_line_photo_delete` | `ROLE_USER` POST — owner uploadu / admin |
| `/lajna/komentar/{id}/smazat` | `app_line_photo_comment_delete` | `ROLE_USER` POST — owner komentu / admin |
| `/lajna/{slug}/prechod/pridat` | `app_crossing_new` | `ROLE_USER` — přidat přechod |
| `/prechod/{id}/uprava` | `app_crossing_edit` | `ROLE_USER` — vlastní přechody |
| `/prechod/{id}/smazat` | `app_crossing_delete` | `ROLE_USER` — vlastní přechody, CSRF |
| `/longline/new` | `app_longline_new` | `ROLE_USER` — přidat longline přechod (disciplína = EN) |
| `/longline/{id}/edit` | `app_longline_edit` | `ROLE_USER` — vlastní longline přechody |
| `/longline/{id}/delete` | `app_longline_delete` | `ROLE_USER` — vlastní, CSRF |
| `/admin/navrhy` | `app_admin_proposals` | `ROLE_ADMIN` — fronta pending proposals + diff tabulky |
| `/admin/navrhy/{id}/schvalit` | `app_admin_proposal_approve` | `ROLE_ADMIN` |
| `/admin/navrhy/{id}/zamitnout` | `app_admin_proposal_reject` | `ROLE_ADMIN` |
| `/data-report` | `app_data_report` | ✓ veřejná, ale odkaz v menu jen adminovi — proklik na legacy detail (`LineController::PRODUCTION_URL`) |
| `/denik/{id}` | `app_user_diary` | ✓ deník konkrétního uživatele (taby Highline / Longline, deep-link `#highline`/`#longline`) |
| `/denicky` | `app_user_directory` | ✓ adresář deníčků (žebříček počtu přechodů) |
| `/galerie` | `app_gallery` | ✓ kronika všech fotek po letech (justified grid, scrollspy roků) |
| `/o-projektu` | `app_about` | ✓ |
| `/intro` | `app_intro` | ✓ splash overlay (zatím nelinkováno z nav) |
| `/profil` | `app_profile` | login required |
| `/prihlaseni`, `/registrace`, `/odhlaseni` | auth | ✓ |
| `/obnova-hesla`, `/obnova-hesla/zmena/{token}` | reset | ✓ |
| `/overeni-emailu` | email verify | login required |
| `/wiki`, `/wiki/{slug}` | `app_wiki_*` | ✓ highline guidebook (17 kapitol z `wiki/`, čte se z lokálního checkoutu, `NN-slug.md` konvence, žádný frontmatter) |
| `/docs`, `/docs/{slug}` | `app_docs_*` | ✓ technická dokumentace (interní), čte se z `docs/*.md` v repu |

## Hotové features

- ✅ **Line CRUD + verifikační flow** — `LineCrudController` (new/edit/delete/verify + admin proposal queue). Trust model: kdokoli logged-in přidá lajnu (`unverified` + `createdBy=user`), edituje vlastní unverified lajny direct; cokoli jiného (cizí/legacy/verified lajna) jde přes `LineEdit` proposal queue (návrh smí poslat kdokoli přihlášený, schvaluje admin). Pozn.: všech 254 legacy lajn je aktuálně `unverified` (flag-migrace je no-op kvůli pořadí migrate→import — schválně, admin je verifikuje při pročištění dat; viz `docs/line-edits.md`). Form má 2-endpoint GPS picker (Stimulus), `length` se počítá z point1/point2 přes haversine (lokace lajny = ty dva body, žádný separátní střed). ROLE_ADMIN přes `app:admin:grant`. Detail v `docs/line-edits.md`.
- ✅ **Crossing CRUD** — `CrossingController` + `LineCrossingForm`. „Přidat přechod" na detailu lajny (logged-in), edit/delete vlastních přechodů z deníku (`show_actions` flag v `_recent_crossings.html.twig`).
- ✅ **Line import** — 254 / 254 lajn z legacy MySQL do Postgres (re-runnable s `--truncate`, GPS fallback přes `gps` table). Detaily v `docs/migration.md`.
- ✅ **User import** — 441 unique-email userů (440 nových + 1 obohacený dev účet), 6 dropped legacy řádků mergováno. MD5 hesla 1:1 zachována, `migrate_from: legacy_md5` přehashuje na bcrypt při prvním přihlášení. Crossings remap přes merge mapu.
- ✅ **Crossings import** — 993 / 995 přechodů (2 skipy kvůli `0000-00-00` datu). Style enum (`App\Enum\CrossingStyle`, 9 hodnot, viz `docs/crossing-styles.md`), neznámé legacy hodnoty se reportují jako warning.
- ✅ **Longline deník** (session 2026-06-13) — druhý tab na `/denik/{id}` vedle highline obsahu. `LonglineCrossing` entita (bez lajny/GPS — longline se nezapisuje k highline, `place` je volný text), `LonglineCrossingController` (plný CRUD, owner-gated + CSRF, vzor `CrossingController`). Import `app:import:longline-crossings` z legacy `longline` (414 / 435, 21 skipů kvůli `0000-00-00`), idempotentní přes `legacyId`. **Styl** sdílí stejný enum jako highline — proto `HighlineCrossingStyle` přejmenován na `App\Enum\CrossingStyle`; longline picker filtruje leash-only styly (`swami`/`solo`/`kotník`) přes `CrossingStyle::appliesToLongline()`. Vznikly u toho dvě **obecné UI komponenty**: Stimulus `tabs` controller + page-agnostic CSS (`.tabs`/`.tab`/`.tab-panel`, `.tabs-pills` varianta, deep-link přes URL hash; `/tv` na ni převedeno, `tv-tabs` smazán) a generická **`.table`** (+ `.table-wrap`/`.table-num`/`.table-actions`/`.table-subtext`), kterou používá longline tabulka.
- ✅ **Line mapa** s 254 lajnami (Leaflet, OSM + Esri ortofoto přepínač, fullscreen, linka mezi body od zoom ≥ 14, popup linkuje na detail)
- ✅ **Line detail** `/lajna/{slug}` — slug unikátně v DB (gen. přes `AsciiSlugger`), info tabulka, mini-mapa s polyline mezi kotvícími body, list všech přechodů
- ✅ **Index page** (single-column rework): sloučený panel **Mapa** (`hp_map_controller.js`, identifier `hp-map`) = levý scrollovatelný sidebar posledních 10 přechodů + mapa, která ukazuje **jen aktivní přechod** (na load první). Aktivní přechod vykreslí reálnou linku (polyline point1↔point2), zazoomuje na ni a animuje ikonku (emoji walker) po posledním úseku lajny (`WALK_FRACTION`/`WALK_DURATION`, do budoucna chování dle typu přechodu). Po dojití se na ikonce spustí náhodná oslavná animace (`CELEBRATIONS` → CSS `hp-cel-*`: bounce/flip/spin/pop/wobble, respektuje `prefers-reduced-motion`). Má-li přechod komentář (`data-comment`), po dokončení chůze se nad ikonou objeví decentní „thought" bublina (`showThought()` injektuje `.hp-thought` do DOM markeru až na konci, ne během walku; clamp 3 řádky, fade-in, `textContent` = auto-escape). Stejná geometrie po sobě (víc přechodů na téže lajně) let přeskočí (`lastGeoKey`), aby mapa necukala; jiná lajna `flyToBounds` glide. Úvodní zobrazení po načtení je **instantní** (`activate(0, {animate:false})` + mapa se rovnou seedne u prvního přechodu) — jinak by velký z7→z17 fly-through nafoukl vrstvy a rozmazal tiles. Homepage běží jako **auto-showcase**: po dokončení přechodu se počká `DWELL` (7 s) a `scheduleAdvance()` přepne na další přechod dokola (cyklus ≈ fly 1,6 s + walk 5 s + dwell 7 s ≈ 14 s). Timer je vázaný na generaci (`gen`) a maže se v `activate`, takže manuální klik cyklus jen převezme z nového místa. Geometrie se čte ze `data-*` na `<li>` (server-render, žádný extra endpoint). Pod tím panel „Z galerie" a na konci horizontální slackTV strip.
- ✅ **slackTV** — YouTube feed, hashtag/search/channel zdroje, in-memory cache, dedikovaná stránka `/tv` s inline přehráváním
- ✅ **slackTV redesign na sekce** (session 2026-06-11) — `/tv` rozdělena na Kanály / Playlisty / Hashtagy, horizontální slidery, AJAX „load more" se stránkováním (`/tv/more`), hashtag taby. Druhá feed vrstva `App\Feed\Tv\*` (`TvFeedInterface`, `YoutubeClient` s `forHandle` + `pageToken`, `CachedTvFeed`). Viz § *Feed (slackTV)*.
- ✅ **slackTV per-source řazení** (session 2026-06-13) — kanál/playlist v `feed.yaml` umí `{ id, sort }` (`asc` = od nejstarších, `desc` default). YouTube `playlistItems` neumí reverse, takže `asc` zdroj se přes `YoutubeClient::allPlaylistItems` stáhne celý, otočí a stránkuje lokálně integer offsetem. Config normalizuje `TvSource`. Viz § *Feed (slackTV)*.
- ✅ **About / O projektu** s historií slacklive 2007 → slack.cz 2010 → ČAS 2011 → dnes, vč. archivního Kolouchova úvodního slova
- ✅ Hlavní vizuál — světlý theme, magenta accent (`#e91e63` z původního slack.cz loga)
- ✅ Auth (registrace, login, reset, email verify) — předvyřízeno před začátkem této vývojové větve
- ✅ **slackvibes 📻 audio player** — persistent přes Turbo, docked v hlavičce / floating expandable / hidden, equalizer animace, draggable; viz `docs/audio-player.md`
- ✅ **Intro splash overlay** — fullscreen logo + „Vstoupit" button (component `intro-overlay`), magenta glow, single-action vstup do appky + spuštění audio playeru
- ✅ **Time-travel mapa** — historický playback lajn + crossings v čase, controly v `.map-tt-panel` (z-index 500)
- ✅ **Crossing news-bar sidebar** na `/mapa` — vertikální panel vlevo s posledními N přechody (sdílí data s emoji markery na mapě). Eye toggle skryje/zobrazí emoji markery, šipka collapsne panel na samotnou hlavičku. V time-travel režimu se obsah přepíná na okno -7 dní zpět od virtuálního času.
- ✅ **Deník uživatele** `/denik/{id}` — hlavička (nick, město, ročník, datum prvního přechodu), mini-mapa s navštívenými lajnami, list všech přechodů
- ✅ **Markdown sections** `/docs` + `/wiki` — sjednocený subsystém pro MD obsah z repa (čte se z disku). Detail níže.
- ✅ **Line foto galerie + sociální vrstva** — `LinePhoto` (line FK, uploadedBy FK SET NULL, filename, caption, createdAt) + `LinePhotoLike` (UNIQUE photo+user) + `LinePhotoComment` (photo FK CASCADE, author FK SET NULL, text, createdAt). Upload přes `vich/uploader-bundle` (mapping `line_photo` → `public/uploads/line/{id}/<uniqid>.webp`; vstup JPG/PNG/WebP/HEIC do 30 MB, ukládá se vždy WebP master z `PhotoNormalizer`). Thumby on-demand přes `liip/imagine-bundle` (filter sety `line_thumb` 320×240 outbound, `gallery_thumb` 1600×480 inset, `line_medium` 800×600 inset, `line_full` 2400 inset; všechny s `auto_rotate` + `strip`). EXIF (datum + GPS) jde do DB sloupců, soubor je čistý — viz § *Foto galerie — upload pipeline*. Per-photo detail `/lajna/{slug}/fotky/{id}` s AJAX like-toggle (Stimulus `photo_like_controller`, fetch s `Accept: application/json`, endpoint vrací `{liked, count}`), plain-text flat komentáři (owner/admin delete), prev/next navigací. Grid v `_line_gallery.html.twig` zobrazuje overlay badges (likes ❤, komenty 💬). Homepage panel „Z galerie" rotuje N fotek z posledních 7 dní (fallback all-time top-liked) + „Otevřít →" na `/galerie`. Cover lajny je **self-hostovaný** přes `Line.coverPhoto` FK (legacy hotlink zrušen 2026-06-16). Legacy import cover + galerie **hotový** (`app:import:line-photos`, viz `migration.md` § *Line photos*); `highline_media` (externí odkazy) zůstává deferred.
- ✅ **Galerie `/galerie` — kronika po letech** (2026-07-04) — všech ~376 fotek v ročních sekcích 2026→2004 + „Bez data", sticky lišta roků se scrollspy (`gallery_nav_controller.js`). **Justified grid čistě v CSS** (`_gallery.scss`): `flex-grow` i `flex-basis` položky škálované poměrem stran (`--ar` inline z `LinePhoto.getAspectRatio()`), takže řádek má jednotnou výšku a layout je stabilní ještě před načtením obrázků (nulový CLS — výška dokumentu se s obrázky nemění); `::after` spacer hlídá poslední řádek. Hover overlay se jménem lajny + popiskem (na touch skrytý), klik → stávající photo detail. Koncept „čas jako jediná osa" je rozhodnutí usera — u legacy fotek je `createdAt` datum 1. napnutí lajny (aproximace), s user uploady (reálný EXIF) se kronika zpřesňuje sama. Seskupení po lajnách/místech záměrně ne: ~1,6 fotky na lajnu, `area`/`region` prázdné.

## Markdown sections (`/docs`, `/wiki`)

Jeden generický subsystém v `App\Markdown\Section\*` slouží jak technické dokumentaci (`/docs` = `docs/*.md` v repu), tak highline guidebooku (`/wiki` = `wiki/NN-skupina/NN-slug.md` v repu). Obsah se čte **z lokálního checkoutu** (ne přes GitHub API) — deploy je `git pull`-ne na server. Žádný per-sekci kód, žádný frontmatter — všechno řídí filename + obsah markdownu.

### Komponenty

```
src/Markdown/Section/
  Page.php                  # value object: slug, filename, body, GH urls, title (z prvního H1)
  Entry.php                 # lightweight DTO pro sidebar (slug, filename, label, group)
  Config.php                # per-sekci konfigurace (owner/repo/branch/path/prefix)
  FetcherInterface.php      # list() + get(slug)
  FilesystemFetcher.php     # čte MD z lokálního checkoutu (wiki/, docs/), slug/folder label parsing
src/Controller/MarkdownSectionController.php   # 4 routes (docs index/show, wiki index/show)
templates/pages/_section/
  _sidebar.html.twig        # shared partial — README link + chapter list, group separators
  index.html.twig           # README.md as index body + sidebar
  show.html.twig            # detail body + sidebar (vše v `.md-prose`, H1 nese sám body)
config/packages/markdown.yaml                  # per-sekci service wiring
```

### Service wiring

Každá sekce = dvojice services v `config/packages/markdown.yaml`:

| Service ID | Třída | Účel |
|---|---|---|
| `app.section.<name>.config` | `Config` | GitHub coordinates (už jen pro blob/edit URL) + route prefix |
| `app.section.<name>.fetcher` | `FilesystemFetcher` | čte MD z `%kernel.project_dir%/{path}`, injected do controlleru |

Přidání nové sekce = další dvojice services + 2 route metody v controlleru. Controller binduje fetchery + configs přes `#[Autowire('@app.section.<name>.fetcher')]`.

Obsah se čte z lokálního checkoutu (deploy ho `git pull`-ne), takže žádný GitHub token, rate-limit ani cache vrstva. `owner/repo/branch` v Configu slouží už jen ke skládání blob/edit odkazů do GitHub UI (tlačítka „zobrazit/editovat na GitHubu").

### Konvence MD souborů (žádný frontmatter)

Po dropu YAML frontmatteru řídí všechno chování dva vstupy: **filename** a **obsah markdownu**.

- **Filename `NN-slug.md`** — `NN` určuje pořadí v sidebaru (lexikografický sort relativního path, takže `01-foo/02-bar.md` < `01-foo/03-baz.md` < `02-x/...`). Slug = filename bez `^\d+-` prefixu a `.md` přípony — `02-bezpecnost.md` je dostupné na `/wiki/bezpecnost`. URL slug musí být **unikátní napříč subtree**, jinak první vyhrává.
- **Label v sidebaru = první `#` H1** v souboru (fallback slug). Title v `<title>` tagu taky.
- **Pull-quote = první `>` blockquote hned po H1**, stylovaný přes CSS `.md-prose h1 + blockquote` (větší písmo, italic, accent background). Žádný speciální HTML tag, čistý markdown blockquote.
- **Skupinové separátory v sidebaru** se derivují z root `README.md` sekce: `## H2 nadpis` zaštiťuje skupinu, foldery linkované pod ním (přes `[text](NN-folder/...)`) dostanou ten H2 jako group label. Diakritika + `&` se zachovají, žádný extra soubor.

### Layout v GH repu

- **Docs** flat (`docs/*.md`), bez subfolderů, bez group separátorů (jejich `README.md` nemá H2 + linky → mapa folderů je prázdná).
- **Wiki** nested (`wiki/NN-skupina/NN-slug.md`). Root `wiki/README.md` udržuje index + definuje group labely. Fetcher prochází adresář sekce rekurzivně (`RecursiveDirectoryIterator`).

### README.md

Index `/docs` resp. `/wiki` rendrují `README.md` z root sekce (pulluje se přes `get('README')`, mimo slugMap). `/docs/README` resp. `/wiki/README` redirectují 301 na index. Subfolder `README.md` se ignorují (slug mapě se vyhnou — nemají v současném modelu žádný účel).

### CommonMark gotcha (relevantní pro inline base64 obrázky ve Wiki)

Wiki kapitoly můžou mít base64 obrázky inline jako MD reference-style:

```markdown
Text s ![alt][image1] obrázkem.

[image1]: data:image/png;base64,iVBOR...
```

**MUSÍ být `[image1]: data:...` (bare URL).** Pokud obalíš angular brackets — `[image1]: <data:...>` — CommonMark to fallne na autolink + odmítne parsovat při velkých URL (>10 KB). Výsledek: `<img>` tag se vůbec neudělá, ref-def se vypíše jako text.

### Žádná cache (čtení z disku)

Obsah žije v deployovaném checkoutu, takže fetcher čte přímo z disku — žádná cache vrstva ani last-known-good fallback (řešily by výpadek/ rate-limit GitHubu, který už nehrozí). `list()` čte u každého souboru jen **hlavičku po první H1** (label do sidebaru), aby netahal MB-velké inline base64 přílohy; plný `body` se načítá až v `get()` pro samotnou stránku. `slugMap` + `folderLabels` jsou memoizované per-request.

### Internal MD link rewriting

`MarkdownRenderer::render($body, $internalRoutePrefix)` přepisuje relativní `*.md` linky (i v subfolderech: `01-pouzivani-highline/02-bezpecnost.md`) na `/{prefix}/{slug}` (`/wiki/bezpecnost`). Slug stripuje `^\d+-` přes `FilesystemFetcher::slugFromFilename()` (sjednocený zdroj transformace). Externí URL (s schemem `http:`, `mailto:` atd.) se ponechávají.
