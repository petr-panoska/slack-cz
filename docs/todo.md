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
- [ ] Foto galerie — legacy import `highline_foto` + `highline_media` (sociální vrstva hotová, viz „Foto galerie" sekce dál).
- [ ] Pretty-print legacy `kotveni` (často číselný kód) — namapovat na čitelný popis
- [ ] „Přidat lajnu" CTA klikem na mapu (currently jen v hlavičce + ručně nastavené GPS) — z `/mapa` klik na prázdné místo by měl rovnou předvyplnit Bod 1

## Foto galerie — sociální vrstva (PR2.2)

Sociální vrstva (likes + komentáře + cover-promote + homepage rotace) hotová v session 2026-05-11. Otevřené follow-upy:

- [ ] Legacy import `highline_foto` + `highline_media` — samostatný `app:import:legacy-photos` skript, vyžaduje SSH na slack.cz pro stažení JPG souborů (`/line/high/{id}/*.jpg`). Doladíme později.
- [ ] Nasadit `@photos` cache blok z `infra/Caddyfile` na betu: `make deployCaddy`. Zatím drift — `make checkCaddy` upozorňuje, `make deploy` je dokud nedeploynem blokovaný.

## Highline edit / verification (PR1, hotové → archiv níž)

Trust model + proposal queue dokumentace v `docs/highline-edits.md`. Otevřené follow-upy:

- [ ] Bulk verify — admin UI s checkboxy nad seznamem unverified lajn (teď to musí klikat 1×1)
- [ ] Notifikace adminovi mailem při `PENDING` proposalu (Symfony Mailer + simple `MAILER_DSN` přes Brevo)
- [ ] Notifikace authorovi proposalu o schválení/zamítnutí
- [ ] „Soft-delete" lajny (deleted_at flag) místo hard delete — kdyby admin chtěl revertovat omylem smazanou lajnu

## Wiki

- [ ] Doplnit chybějící obrázky — úvodní Google Docs seed přenesl jen 8 z ~N obrázků, co jsou v rendrovaném dokumentu. Které kapitoly to postihuje a jak je dorovnat: editovat `wiki/NN-skupina/NN-slug.md` přímo na GitHubu, přidat `![][imageN]` ref-defs s base64 (případně inline `![](data:image/png;base64,...)`). Zatím low-priority kosmetika.

## slackTV

- [x] Feature přejmenována „Slack.cz TV" → **slackTV** + dedikovaná stránka `/tv` (grid karet, click-to-play inline embed přes `tv_controller.js`). Homepage panel zůstává jako teaser s odkazem „Otevřít →", nav má položku slackTV.
- [ ] Až bude existovat YT kanál CAS, přidat jeho channel ID do `feed.youtube.channels`
- [ ] Přidat další search query / kanál — při aktuálním TTL 21600s (6h) je quota rezerva ~25×, takže pár dalších zdrojů se vejde bez úprav TTL (vzorec viz `architecture.md` § *Quota economics*). TTL prodlužovat až kdyby queries narostly o řád.
- [ ] Promyslet, jestli má smysl přidat i FB Page CAS jako další zdroj (pak nutný jiný fetcher; Meta API)

## Deník uživatele

- [ ] Longline deník (až bude longline import) — přidat tab nebo sekci na `/denik/{id}`
- [ ] Avatar / bio / odkazy (IG, web) — UI editor pro vlastní profil + sloupce v entitě, případně backfill z legacy
- [ ] „Síň slávy" / žebříček uživatelů (nejvíc přechodů, nejdelší lajna) — nový index na `/denicky`
- [ ] FA badge u přechodu / na profilu, jakmile dořešíme prvopřechody
- [ ] Edit profile (po přihlášení) — město, ročník, telefon, gender

## Cutover legacy slack.cz → nový VPS

Detailně v `deploy.md`. Krátce:

- [ ] Doimport legacy dat — chybí first ascents (viz „Migrace dat (deferred)" výš). Sync lokál → beta je hotový (`make syncBetaFromLocal`, viz `deploy.md`).
- [ ] `MAILER_DSN` přes externí SMTP relay (Brevo / Mailgun / Postmark) — Hetzner blokuje port 25 outbound. Dokud nejede, fallback: `bin/console app:user:reset-password <email>` (viz `deploy.md` § *Reset hesla / aktivace účtu, když nechodí maily*).
- [ ] DNS swap: A pro `slack.cz` z legacy IP na `178.105.81.158` + AAAA (gray cloud, DNS-only)
- [ ] Přidat `slack.cz` blok do `infra/Caddyfile` (analogický k `beta.slack.cz`) a `make deployCaddy`
- [ ] Po DNS swapu přepsat `DEFAULT_URI` v `/var/www/slack-cz/.env.local` z `https://beta.slack.cz` na `https://slack.cz` + reload PHP-FPM (čte to `framework.router.default_uri` pro absolutní URL z CLI commandů)
- [ ] Nastavit reálné `YOUTUBE_API_KEY` + `DOCS_GITHUB_TOKEN` v `.env.local` na serveru
- [ ] (volitelné) `unattended-upgrades` na auto-security patches
- [ ] (volitelné) Hetzner snapshot schedule pro disaster recovery

## Ostatní

- [ ] Restrikce YouTube API key v Google Cloud Console (HTTP referrers + jen YouTube Data API v3) — momentálně je key bez restriction, je v transkriptech konverzace
- [ ] Po nasazení produkce: rotovat `YOUTUBE_API_KEY`
- [ ] Uklidit `App\Old\Entity\Uzivatel` — zatím se používá pro debug view `/old-users`. Po user importu zvážit smazání route + entity (po user importu už máme data v nové DB).
- [ ] Default Symfony `hello_controller.js` v `assets/controllers/` smazat až bude místo něj něco užitečného
- [ ] Index page má prázdný main sloupec vlevo nad mapou (kdyby měl menší výšku) — můžeme tam dát další box (nedávno přidané, top rated, něco z deníčku po user importu)

## Dokončené (krátký archiv)

- [x] **Infra: Caddyfile v repu + drift gate** (session 2026-05-11) — `infra/Caddyfile` jako single source of truth. `scripts/check-caddy.sh` (SSH cat + diff vs repo, exit 1 na drift) + `scripts/deploy-caddy.sh` (scp → `caddy validate` → atomic cp → `systemctl restart caddy` + smoke test). Makefile targety `checkCaddy` + `deployCaddy`; `make deploy` chain-uje `checkServerEnv checkCaddy` jako preflight (fail-fast). CI workflow `.github/workflows/deploy.yml` přidává `Check Caddy config drift` step do preflight jobu. Caddy restart se nikdy nedělá implicitně při code deploy — vlastní rozhodovací bod. Princip uložen do paměti jako [Infra v repu](feedback_infra_in_repo.md).
- [x] **Galerie sociální vrstva + AJAX likes** (session 2026-05-11) — `HighlinePhotoLike` (UNIQUE photo+user) + `HighlinePhotoComment` (flat, plain-text, owner/admin delete). Per-photo detail page `/highline/{slug}/fotky/{id}` s like-toggle, prev/next nav, plain-text komentáři. Grid v `_highline_gallery.html.twig` přepsán: thumbnaily linkují na detail, overlay badges (❤ count, 💬 count). Homepage panel „Z galerie" rotuje N fotek z posledních 7 dní; fallback all-time top-liked (sjednocený SQL přes Postgres `ORDER BY RANDOM()`). Like button neredirectuje — Stimulus `photo_like_controller.js` intercept-uje submit, POST s `Accept: application/json`, endpoint vrací `{liked, count}` JSON, controller updatuje UI bez reloadu. Graceful degradation: při fetch failu padá zpět na nativní form submit. Cover stránky highline je zatím **čistě legacy URL** (`highline.legacyCoverUrl`) — fotky z galerie do coveru nemícháme, vyřeší se později. Manuální cover-promote endpoint + `Highline.coverPhoto` field zahozeny v rámci stejné session. Migrace `Version20260511134125`.
- [x] **Audit historie + stack-pop curation + verifikační merge** (session 2026-05-10) — `/highline/{slug}/historie` veřejný view nad `HighlineEdit` rows. Každá řádka má **vlastní `beforeSnapshot`** (migrace `Version20260510185639` + backfill), takže diff je nezávislý na sousedech a nikdy se nepropisují změny ze smazaných řádků. Admin tlačítko **„Smazat poslední revizi"** (červené, stack-pop) — fyzicky odstraní jen chronologicky poslední row + zavolá `applySnapshot(newLatestApplied)` na entitu, takže audit log je zdroj pravdy stavu lajny. První záznam smazat nelze. Při `verify` se celá dosavadní historie sloučí do jediné nové APPLIED creation revize (preserves `createdBy`, admin = reviewer) — odstraňuje konflikt mezi unverified rename historií a po-verifikaci zamknutým slugem. Creation row v UI ukazuje plnou tabulku Pole / Hodnota ze snapshot-u, ne prázdný „žádný diff". No-op submit (žádné změny) audit row vůbec nezapisuje. Maintenance command `app:edit:sync-from-history` opravil drift z předchozích deletů. Detaily v `docs/highline-edits.md`.
- [x] **Slug stability na verified + rename slug regen na unverified** (session 2026-05-10) — `HighlineForm` derivuje `nameLocked = $highline->isVerified()` z form data → `disabled: true` (server-side ignoruje POSTed value) + 🔒 ikonka u labelu s native tooltip „URL: /highline/{slug} — název je pevný". Pro unverified lajnu v edit módu naopak URL panel s live preview: Stimulus `live_slug_controller.js` slugify-uje typed name → `<code>` se okamžitě přepočítává. Server na save regeneruje slug pro unverified rename přes `makeUniqueSlug($name, ..., excludeId: $highline->getId())`. `deriveGeometry` zaokrouhluje midpoint na 7 desetinných míst (`number_format($val, 7, '.', '')`) — bez tohohle PHP cast `(string)(float/2)` plival 8+ míst, DB to truncovala na 7, a každý save vyrobil falešný diff lat/lng.
- [x] **Parking GPS import** (session 2026-05-10) — `Highline.parkingLatitude/Longitude` (nullable decimal), `ImportHighlinesCommand` LEFT JOIN `gps WHERE type='PARKING'` přes `highline.parking_id` (133 / 254 lajn má parking po backfillu). Detail: tabulka „Parkování" s mapy.cz linkem + modrý P marker na mapě. Form: optional sekce + „Přidat parkování" toggle button na mapě (next click = parking, drag = move, klik na aktivní = smazat). Migrace `Version20260510175252`.
- [x] **Highline CRUD + verifikační/proposal flow** (session 2026-05-10) — `HighlineCrudController` (new/edit/delete/verify + admin proposal queue), `Highline.isVerified` + `Highline.createdBy`, `HighlineEdit` entita coby unified audit log + pending queue (statuses `applied`/`pending`/`rejected`), 254 legacy lajn flagnuté `verified=true` při migraci. `ROLE_ADMIN` + `app:admin:grant` console command. Stimulus `highline_form_map_controller` jako 2-endpoint GPS picker s haversine length + midpoint computation. Detaily v `docs/highline-edits.md`.
- [x] **Crossing form (CRUD pro přechody)** — `CrossingController` + `HighlineCrossingForm`. Tlačítko „Přidat přechod" na detailu lajny (logged-in), edit/delete vlastních přechodů z deníku (`show_actions` flag v `_recent_crossings.html.twig` partial).
- [x] **Deník uživatele `/denik/{id}`** — `UserController::denik` + `pages/user_denik.html.twig`. Hlavička (nick, město, ročník, datum prvního přechodu přes `findFirstCrossingDate`), mini-mapa s body všech navštívených highlines (zoom na bbox, popup s odkazem na detail), tabulka přechodů. Linkováno z „Posledních přechodů" stripe a z popupu uživatele na `/mapa`.
- [x] **Crossing news-bar sidebar** na `/mapa` — vertikální panel vlevo s N posledními přechody (`RECENT_LIMIT`), eye toggle (skryje/zobrazí emoji markery, persistent přes `sessionStorage`), collapse na samotnou hlavičku (taky persistent). Time-travel režim přepne sidebar na okno -7 dní zpět od virtuálního času. Cross-controller komunikace přes CustomEvents `slack:map-mode` a `slack:users-visibility` (viz `architecture.md`).
- [x] **Single source of truth pro recent crossings** — `HighlineCrossingRepository::RECENT_LIMIT`. Index page stripe, mapové emoji markery i sidebar feed teď čerpají ze stejného setu (žádný dedup by user). Sjednoceno přes `findRecent()` + `findRecentForJson()`.
- [x] **Intro splash overlay** — fullscreen logo + „Vstoupit" button na vstupu do appky (komponenta `intro-overlay` v `user_toolbar.twig`). Klik = dismiss + start audio playeru.
- [x] **Mapové zoom controly přesunuty na `bottomright`** — defaultní `topleft` koliduje se sidebarem. V time-travel módu se zoom zvedá nad time-travel panel (CSSkem).
- [x] **Highline detail `/highline/{slug}`** — slug v DB (unique), import doplňuje přes `AsciiSlugger`. Stránka: stat panel (délka/výška/rating), info tabulka (kotvení 1/2, přístup, napínání, autor, datum, historie názvu, GPS s odkazem na mapy.cz), mini-mapa s polyline mezi `point1Latitude/Longitude` a `point2Latitude/Longitude` (pokud oba body známe), seznam všech přechodů. Detail je nalinkovaný z popupu na `/mapa` i ze stripe „Posledních přechodů" na indexu.
- [x] **Highline `point1Latitude/Longitude` + `point2Latitude/Longitude`** — naimportováno z legacy `gps` tabulky přes JOIN na `point1_id` / `point2_id` (typ `LINE_POINT`).
- [x] **User import** — 440 nových User + 1 enriched (existující dev účet) + 6 dropped legacy řádků mergováno (5 duplicit emailů). MD5 hesla zachována, `migrate_from` v security.yaml přehashuje na bcrypt při prvním loginu.
- [x] **Crossings import** — 993 / 995 přechodů (2 skipy kvůli `0000-00-00` datům). Style enum s 9 hodnotami, mapping legacy → enum, neznámé hodnoty se reportují jako warning.
- [x] **`Makefile`** — targety `legacyImport` (re-import s `--truncate`) a `legacyImportFresh` (drop+create+migrate+full import).
- [x] **„Poslední přechody" panel** na indexu (vedle Slack.cz TV) — ukazuje N nejnovějších (datum / user / lajna / hodnocení v hvězdách). Limit centrálně v `RECENT_LIMIT`.
- [x] Highline import 254/254 z legacy DB do nové Postgres
- [x] Mapa `/mapa` s Leaflet + OSM, fixnutý problém s ikonami (asset URLs přes Stimulus values)
- [x] Light theme s magenta accent (`#e91e63` z původního loga)
- [x] Index page se 2 panely (mini mapa + Slack.cz TV)
- [x] Slack.cz TV — YouTube Data API v3 fetcher s mock fallbackem a Symfony Cache TTL
- [x] About page `/o-projektu` s historií slacklive 2007 → slack.cz 2010 → ČAS 2011 → dnes, plus Kolouchovo úvodní slovo (verbatim) a archivní vizuály (slacklive logo, kolouch-vitejte foto)
- [x] Restrukturalizace legacy mapování — `App\Entity\Old\*` → `App\Old\Entity\*` (mimo `src/Entity/`), aby Doctrine default EM nepokoušel migrace pro legacy tabulky
- [x] `.env` deklaruje `YOUTUBE_API_KEY=` (prázdné, dokumentace), reálný v `.env.local` (gitignored)
