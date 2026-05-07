# TODO

Otevřené úkoly napříč projektem. Aktualizuj kdykoli zmizí / přibude.

## Migrace dat (priority 1)

- [ ] **User import** (`app:import:users`)
  - rozšířit `App\Entity\User` o pole z legacy (nick, firstName, lastName, city, birthYear, phone, gender, isActive, legacyId, legacyMergedIds, legacyDataSnapshot) — viz `migration.md`
  - migrace pro nová pole
  - doplnit `password_hashers.legacy_md5` do `security.yaml` + `migrate_from`
  - command s `--truncate` a `--dry-run`, plný report (imported / merged / skipped)
  - merge logika 6 duplicit dle tabulky v `migration.md`
  - vystavit merge mapu (`dropped_legacy_id → canonical_user_id`) jako shared service `App\Legacy\UserMergeMap`
- [ ] **Crossings import** (`app:import:crossings`)
  - vytvořit entitu `App\Entity\HighlineCrossing`
  - vytvořit `App\Enum\HighlineCrossingStyle` dle `crossing-styles.md`
  - mapovat styl text → enum, neznámé hodnoty hodit warning
  - aplikovat user merge mapu na `uzivatel_id`
  - command `--truncate` a `--dry-run` s reportem
- [ ] Aktualizovat `Makefile` o `legacyImport` target (drop+create+migrate+highlines+users+crossings)

## Migrace dat (deferred)

- [ ] First ascents (`prvni_prechody_hl`, 124 řádků) — rozhodnout sloučení s běžnými přechody vs vlastní entita; doptat se Koloucha o smyslu / UI prezentaci. 49 orphans (NULL `uzivatel_id` s textovým nickem) potřebují fallback strategii.
- [ ] Doptat se Koloucha co znamená `record` role (1 uživatel) — máme něco zachovat?
- [ ] Doptat se proč 6 uživatelů má `enabled=0` — má být login disabled, nebo skryti úplně?

## Mapa highlines

- [ ] Detail stránka highline `/highline/{slug}` (slug z `name`) — popis, foto, anchoring, first ascent, list přechodů
- [ ] Filtry na `/mapa` (typ, kraj, délka, výška)
- [ ] Vlastní ikony per typ (Highline / Top Highline / Midline / Urban Line — různé barvy)
- [ ] Clustering markerů v hustých oblastech (Tisá, Ostrov)
- [ ] Linie mezi `point1` a `point2` místo pinu (pokud má lajna oba body)
- [ ] CRUD pro přidávání nové highline (jen pro přihlášené, click na mapě = nastaví GPS)

## Slack.cz TV

- [ ] Až bude existovat YT kanál CAS, přidat jeho channel ID do `feed.youtube.channels`
- [ ] Pokud chceme zobrazovat víc než 2 search queries, prodloužit `feed.cache.ttl_seconds` na 3600s (1h) — viz quota matematika v `architecture.md`
- [ ] Promyslet, jestli má smysl přidat i FB Page CAS jako další zdroj (pak nutný jiný fetcher; Meta API)

## Ostatní

- [ ] Restrikce YouTube API key v Google Cloud Console (HTTP referrers + jen YouTube Data API v3) — momentálně je key bez restriction, je v transkriptech konverzace
- [ ] Po nasazení produkce: rotovat `YOUTUBE_API_KEY`
- [ ] Uklidit `App\Old\Entity\Uzivatel` — zatím se používá pro debug view `/old-users`. Po user importu zvážit smazání route + entity (po user importu už máme data v nové DB).
- [ ] Default Symfony `hello_controller.js` v `assets/controllers/` smazat až bude místo něj něco užitečného
- [ ] Index page má prázdný main sloupec vlevo nad mapou (kdyby měl menší výšku) — můžeme tam dát další box (nedávno přidané, top rated, něco z deníčku po user importu)

## Dokončené (krátký archiv)

- [x] Highline import 254/254 z legacy DB do nové Postgres
- [x] Mapa `/mapa` s Leaflet + OSM, fixnutý problém s ikonami (asset URLs přes Stimulus values)
- [x] Light theme s magenta accent (`#e91e63` z původního loga)
- [x] Index page se 2 panely (mini mapa + Slack.cz TV)
- [x] Slack.cz TV — YouTube Data API v3 fetcher s mock fallbackem a Symfony Cache TTL
- [x] About page `/o-projektu` s historií slacklive 2007 → slack.cz 2010 → ČAS 2011 → dnes, plus Kolouchovo úvodní slovo (verbatim) a archivní vizuály (slacklive logo, kolouch-vitejte foto)
- [x] Restrukturalizace legacy mapování — `App\Entity\Old\*` → `App\Old\Entity\*` (mimo `src/Entity/`), aby Doctrine default EM nepokoušel migrace pro legacy tabulky
- [x] `.env` deklaruje `YOUTUBE_API_KEY=` (prázdné, dokumentace), reálný v `.env.local` (gitignored)
