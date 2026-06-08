# Migrace legacy dat

Strategie importu dat ze staré MySQL DB (`slack.cz` z 2010) do nové Postgres DB.

## Princip 1 — re-runnable

**Kdykoli během vývoje musí jít pustit kompletní import na čistou novou DB a dostat
deterministicky stejný výsledek.**

Konkrétně to znamená:

- Každý import command podporuje `--truncate` flag (vymaže své cílové tabulky před importem)
- Bez `--truncate` import zachová už importovaná data (idempotent — opakované volání bez změn dat = noop)
- Pořadí runů musí být dokumentované (entity závislosti)
- Žádný import nesmí dělat side effect mimo svou cílovou tabulku (např. mazat něco, co jiný import zapsal)

Fresh import na čisté schéma — DB reset je ruční, samotné importy zabaluje `make legacyImport`:

```bash
docker compose exec php bin/console doctrine:database:drop --force --if-exists
docker compose exec php bin/console doctrine:database:create
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php bin/console app:import:highlines --truncate
docker compose exec php bin/console app:import:users     --truncate
docker compose exec php bin/console app:import:crossings --truncate
```

## Princip 2 — exception reporting

**Každý sken / skip / merge / fallback musí být reportovaný vývojáři.**

Konkrétně:

- Importy dědí `Symfony\Component\Console\Style\SymfonyStyle` (`SymfonyStyle::warning`, `note`)
- Každý record který se přeskočí musí mít řádek typu `[SKIP] uzivatel#157 — duplicate email "X" already used by #156`
- Každý merge / fallback se loguje
- Závěrečný summary obsahuje counts: `imported`, `skipped`, `merged`, `errors`
- Pokud cokoli neočekávaného (data violation, SQL error), import vyhodí exception, **nikdy si nedrží data v paměti a neukládá je tiše**

Cíl: vývojář při každém runu okamžitě vidí, co se v datech děje. Žádné tichý "data loss".

## Princip 3 — zdroj pravdy = legacy MySQL

- Importy čtou přes DBAL `Connection` (autowire `doctrine.dbal.old_connection`)
- **Žádné ORM entity pro legacy tabulky** — vše se čte přes DBAL `old` connection
- Raw SQL nebo `fetchAllAssociative()` — víme přesně, jaké sloupce čteme, žádný překlad přes mapping

---

## Stav per entita

### ✅ Highlines — DONE

- Command: `app:import:highlines`
- Source: `highline` + LEFT JOIN `gps` (přes `point1_id` jako fallback pro chybějící `lat`/`lng`, `parking_id` pro parkování)
- Cílová entita: `App\Entity\Highline` (FK `legacyId` na původní `highline.id`)
- Importováno: **254 / 254** (před fallbackem to bylo 138, 116 mělo prázdné lat/lng — viz `gps` table fallback). 133 / 254 má parkování naimportované z `gps WHERE type='PARKING'`.
- Skipy: 0
- **Pozn. (2026-06-09):** cílové sloupce `latitude`/`longitude` byly později zahozené (migrace `Version20260609120000`) — lajna se lokalizuje výhradně dvěma kotvícími body (`point1`/`point2`). Import command proto už lat/lng neplní a skip-gate je na `point1` místo legacy `lat`/`lng`. Viz `docs/architecture.md` § Mapa.
- Mapování `typ` (int) → `HighlineType` enum přes `HighlineType::fromLegacyId()`: legacy `1` (Highline), `2` (Top Highline) i `4` (Urban Line) → `Highline`; `3` → `Midline`; cokoli jiného → `Unsorted`. Enum má 5 hodnot — `Unsorted`, `Highline`, `Midline`, `Longline`, `Waterline` — poslední dvě legacy import neprodukuje (jsou připravené pro budoucí ruční zadávání; per-typ ikony viz `todo.md`). Migrace `Version20260608120000` navíc slila už naimportované legacy stringy `top_highline`/`urban_line` do `highline` (úklid dvou nadbytečných typů).
- **Verifikační flag** — migrace `Version20260510113004` flaguje všech 254 importovaných lajn jako `is_verified = true` (`UPDATE highline SET is_verified = TRUE WHERE legacy_id IS NOT NULL`). Tím se na ně automaticky aplikuje proposal flow pro budoucí edity. Detail v `docs/highline-edits.md`.

#### Co se ZTRATILO při importu

- **Foto galerie** (`highline_foto`, `highline_media`) — celé skipnuté. Cover photo se zatím tahá z legacy URL `https://slack.cz/line/high/{legacyId}/foto.jpg`. Plán: PR2 = own foto galerie + likes/komentáře, viz `todo.md`.

### ✅ Users — DONE

- Command: `app:import:users`
- Source: `uzivatel` (447 rows)
- Cílová entita: `App\Entity\User` — rozšířená o legacy fieldy (viz níže)
- Výsledek: **441 / 441 unique-email userů v DB** (440 nově vytvořených + 1 obohacený existující dev účet), **6 dropped legacy řádků mergováno** do 5 canonical účtů (5 duplicitních emailů — 6. duplicita to byly 3 řádky pod jedním emailem). MD5 hesla zachována 1:1, `migrate_from: legacy_md5` přehashuje na bcrypt při prvním přihlášení.

#### Stav dat v legacy

| Metrika | Hodnota |
|---|---|
| Total | 447 |
| S emailem | 447 (100 %) |
| Unique emails | 441 (6 duplicit) |
| Heslo (32 znaků = MD5) | 447 (100 %) |
| Enabled | 441 (6 disabled) |
| Role: `guest` | 438 |
| Role: `user` | 5 |
| Role: `admin` | 3 |
| Role: `record` | 1 (?) |

#### Hesla — strategie

Použít **Symfony `migrate_from`** pattern:

```yaml
# config/packages/security.yaml
security:
    password_hashers:
        App\Entity\User:
            algorithm: auto
            migrate_from:
                - legacy_md5

        legacy_md5:
            algorithm: md5
            encode_as_base64: false
            iterations: 1
```

Při importu uložíme MD5 hash 1:1 do `User.password`. Při prvním přihlášení Symfony ověří hash proti `legacy_md5`, a `UserRepository::upgradePassword()` (už implementované) ho přehashuje na bcrypt. Uživatelé nepoznají nic.

Důsledek: do první aktivity má každý zděděný uživatel stále MD5. Akceptovatelné, dokud `legacy_md5` zůstane v configu.

#### Pole k zachování — VŠECHNA

User entitu rozšíříme o:

| Legacy `uzivatel` | New `User` | Pozn. |
|---|---|---|
| `id` | `legacyId` | int, indexed |
| `email` | `email` | unique (s merge handlingem) |
| `heslo` | `password` | MD5 → bcrypt přes migrate_from |
| `nick` | `nick` | unique, index, používaný v UI místo emailu |
| `jmeno` | `firstName` | nullable string 30 |
| `prijmeni` | `lastName` | nullable string 30 |
| `mesto` | `city` | nullable string 50 |
| `rok_nar` | `birthYear` | nullable int |
| `telefon` | `phone` | nullable string 30 |
| `pohlavi` | `gender` | nullable enum (M/F/—) |
| `role` | `roles` | mapping níže |
| `enabled` | `isVerified` + nějaký `isActive` | enabled=0 → isActive=false (login zakázaný), ale data zachováme |

Mapping rolí:

| Legacy | New Symfony role | Pozn. |
|---|---|---|
| `guest` | `ROLE_USER` | default |
| `user` | `ROLE_USER` | (nebo přidat `ROLE_MEMBER` později — neřešit teď) |
| `admin` | `ROLE_ADMIN` | |
| `record` | `ROLE_USER` | nejasné co to bylo, doptat se Koloucha |

#### Duplicitní emaily — MERGE

**Pravidlo: jeden reálný člověk = jeden `User` v nové DB. Ale žádná data ze starých řádků se nesmí ztratit.**

Aktuálně 6 duplicit:

| Email | Keep | Drop | Důvod výběru |
|---|---|---|---|
| `jackob008@seznam.cz` | 156 (Komi) | 157 (Kuba) | nejstarší ID |
| `jurajsovcik@post.sk` | 146 (shovky) | 147 (ďuroSR) | nejstarší ID |
| `LidaSmutkova@seznam.cz` | 452 (Lili) | 455 (Lilli87) | nejstarší ID |
| `pepaanek@gmail.com` | 128 (Pepanek) | 197 (Pepek) | nejstarší ID |
| `vlkondra@email.cz` | **248 (Vlčák)** | 213, 848 | **má 1 přechod (2014) — držíme aktivní účet** |

**Mechanismus zachování dat:**

1. `User.legacyId` — primary kept ID (canonical)
2. `User.legacyMergedIds` — `int[]` (Doctrine `simple_array` nebo `json`) — list dropped IDs
3. `User.legacyDataSnapshot` — `json` — raw snapshot dropped rows + canonical row (full audit pro pozdější merge revize)
4. **Crossings remap**: pro každý `highline_prechody.uzivatel_id` se před importem konzultuje merge mapa: dropped ID se přepíše na kept ID. Tím historické přechody zůstanou pod merged účtem.

Algoritmus importu:

```
for each unique email in uzivatel:
    rows = all uzivatel rows with this email
    canonical = pick by rule (default: oldest id; výjimka u vlkondra)
    for each dropped row:
        log [MERGE] dropped uzivatel#X into uzivatel#Y (email "Z")
    create User with legacyId=canonical.id, legacyMergedIds=[dropped ids],
                    legacyDataSnapshot={canonical: {...}, dropped: [{...}]}
    add (dropped_id → canonical_id) entry to global merge map
final summary:
    "Imported N users, merged M legacy rows into N - M kept rows"
```

Když uživatel chce někdy v budoucnu duplicitu rozseknout zpátky, snapshot mu to dovolí.

#### Otázky k doptání (Kolouch / vedení CAS)

- `record` role — co to bylo, máme něco zachovat na nové straně?
- `enabled = 0` u 6 uživatelů — víme proč? mají být v new app login-disabled, nebo úplně skryti?

---

### ✅ Highline crossings (přechody) — DONE

- Command: `app:import:crossings`
- Source: `highline_prechody` (995 rows)
- Cílová entita: `App\Entity\HighlineCrossing`
- Výsledek: **993 / 995 přechodů importováno**, 2 skipy kvůli neplatnému `0000-00-00` datu
- 82 unique uživatelů aktivních (přechody dělalo)
- 472 přechodů s ratingem (1–5 hvězd)
- 552 s komentářem

Schema (implementováno v `App\Entity\HighlineCrossing`):

```php
class HighlineCrossing
{
    private ?int $id = null;
    private Highline $highline;     // ManyToOne, not nullable
    private User $user;             // ManyToOne, not nullable (po user merge → vždy canonical)
    private \DateTimeImmutable $crossedAt;             // date_immutable (jen datum, ne čas)
    private ?HighlineCrossingStyle $style = null;       // enum string col length 20, viz crossing-styles.md
    private ?int $rating = null;                        // 1–5
    private ?string $comment = null;                    // text (legacy bylo 100 — text dává rezervu)
    private ?int $legacyId = null;                      // nullable, lookup index pro --truncate idempotenci
    private \DateTimeImmutable $createdAt;              // datetime_immutable
}
```

Index `idx_crossing_crossed_at` na `crossedAt` — repo často filtruje a řadí podle data.

#### Mapování stylů — kompletní taxonomie viz [`crossing-styles.md`](crossing-styles.md)

#### Závislosti

- Vyžaduje, aby běžel **po** `app:import:users` (kvůli FK `User`)
- Sdílí službu `App\Legacy\UserMergeMap` — singleton, kterou plní `app:import:users` (kept canonical + dropped → kept) a čte `app:import:crossings` přes `userMergeMap->find($legacyUzivatelId)`. Tím se přechody pod dropped emailem automaticky napárují na canonical User.

#### Co NEbude v MVP

- Žádný ORM mapping pro legacy přechody — čteme přes DBAL `old` connection.
- Žádné UI pro přidávání přechodů přes nové appce. To řešíme později.

---

### ⏸️ First ascents (`prvni_prechody_hl`) — DEFERRED

124 záznamů „prvních přechodů" highline. **Zatím skipujeme.** Doptat se a řešit později.

#### Stav dat (pro budoucí rozhodnutí)

- 124 řádků celkem, roky 2009–2018 (peak 2012–2017)
- **49 orphans** — `uzivatel_id IS NULL`, jen string `nick` ve sloupci. Pravděpodobně neregistrovaní hosté, kteří chodili lajny, ale nikdy neměli účet.
- Jedna lajna může mít víc first-ascent záznamů ve stejný den → **týmové prvovýstupy** (např. „Ťuky ťuk" 2012-11-18 má 7 lidí)
- Styl pole jako u běžných přechodů (`OS fm`, `fm`, `one way`, `OS,fm`, `OS`)
- Schema: `id`, `nick`, `styl`, `uzivatel_id` (nullable), `highline_id`

#### Otevřené možnosti pro budoucí migraci

1. **Sloučit s běžnými přechody** — přidat na `HighlineCrossing` flag `isFirstAscent: bool`. Plus pole `legacyGuestNick` pro orphans.
   - Výhoda: jeden datový model, jednoduchá UI
   - Nevýhoda: konceptuálně mícháme „přejití lajny" s historickou skutečností „kdo lajnu poprvé přešel"
2. **Vlastní entita `HighlineFirstAscent`** s ref na Highline + (volitelný) User + datum + styl + free-text nick
   - Výhoda: jasná sémantika, samostatné UI
   - Nevýhoda: další tabulka, redundance se přechody (stejný uživatel může mít první přechod a několik dalších)

**Doptat se Koloucha**: jak se na prvovýstupech historicky pohlíželo, mají být zvlášť zviditelněné v UI, nebo to bylo spíš info v deníčku.

---

### ⏭️ Mimo scope

- **Longline crossings** — uživatel řekl explicitně neresit
- **`highline_prechody_n`** — 0 řádků v legacy, ignorujeme
- **`bany`, `forum`, `kalendar`, `clanky`, `clanky_koment`, `eshopBanners`, `event`, `kategorie_rs`, `krouzky`, `media_o_nas`, `novinky`, `objednavky`, `products`, `videa`, `fotolive`, `slack_*`** — všechny ostatní legacy tabulky zatím mimo plán importu

---

## Summary commands

```bash
# Legacy import = jednorázová lokální věc na čisté schéma.
# Předpoklad: legacy MySQL má nahraný dump (`make loadLegacyDump` jednorázově po prvním `up`).
make legacyImport
```

`make legacyImport` spustí jen importy v pořadí daném FK závislostmi — na čistém schématu, takže žádný `DELETE FROM highline_crossing` workaround není potřeba:

```bash
docker compose exec -T php bin/console app:import:highlines --truncate
docker compose exec -T php bin/console app:import:users --truncate
docker compose exec -T php bin/console app:import:crossings --truncate
```

Zopakování od nuly = ruční reset DB před tím (a pokud je legacy MySQL prázdná, nejdřív `make loadLegacyDump`):

```bash
docker compose exec -T php bin/console doctrine:database:drop --force --if-exists
docker compose exec -T php bin/console doctrine:database:create
docker compose exec -T php bin/console doctrine:migrations:migrate -n
```
