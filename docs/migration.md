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
docker compose exec php bin/console app:import:users --truncate
docker compose exec php bin/console app:import:highline-crossings --truncate
docker compose exec php bin/console app:import:longline-crossings --truncate
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
- **Verifikační flag — POZOR, no-op:** migrace `Version20260510113004` sice obsahuje `UPDATE highline SET is_verified = TRUE WHERE legacy_id IS NOT NULL`, ale **neflagne nic** — migrace běží nad prázdnou DB **před** importem (`make migrate` → `make legacyImport`), takže UPDATE potká 0 řádků; import pak lajny vytvoří s defaultem `is_verified = false`. **Všech 254 legacy lajn je tedy `unverified`** a je to tak ponecháno schválně (nutí admina data postupně pročistit = verifikovat). Migraci needitujeme (už aplikovaná + staví celé `highline_edit`/`is_verified` schéma; ten UPDATE je jen neškodný no-op). Detail a důsledky v `docs/highline-edits.md`.

#### Co se ZTRATILO při importu

- **Foto galerie** (`highline_foto`, `highline_media`) — celé skipnuté. Cover photo se zatím tahá z legacy URL `https://slack.cz/line/high/{legacyId}/foto.jpg`. Plán: PR2 = own foto galerie + likes/komentáře, viz `todo.md`.

#### Vypnutá legacy pole (lokalita + kotvení)

Tyhle `highline` sloupce v legacy datech byly, ale v nové app jsou **vypnuté** — zahozené z formuláře, detailu i importu. Sloupce v DB zůstaly (kvůli auditu/snapshotům), jen se neplní ani nezobrazují.

**Lokalita / oblast** (`stat` → `country`, `kraj` → `region`, `oblast` → `area`)

- **Země a kraj** jdou dopočítat z GPS (reverse-geocoding) → není nutné je držet ručně.
- **`oblast`** byl ale konkrétní lokální název (Ostrov, Adršpach, Tisá, Divoká Šárka…), jemnější než vesnice a z GPS reverse-geocodingu ho spolehlivě nedostaneš. **Časem dodělat** — buď vlastní pole „Oblast", nebo odvodit z GPS + ruční korekce.

**Typ kotvení** (`kotveni` → `anchoring`)

- Legacy `kotveni` byly číselné kódy (1/2/3 a kombinace `13`, `23`, `123`…) **bez dochované lookup tabulky** → pole „Typ kotvení" zahozeno z formuláře, detailu i importu. Sloupec v DB ponechán. Pokud se význam kódů někdy dohledá, šlo by je namapovat na čitelný popis a pole vrátit.

### ✅ Users — DONE

- Command: `app:import:users`
- Source: `uzivatel` (450 rows)
- Cílová entita: `App\Entity\User` — rozšířená o legacy fieldy (viz níže)
- Výsledek: **443 User řádků v DB**, **7 legacy řádků mergováno** do canonical účtů: 6 duplicitních-email dropů přes 5 canonical účtů + **1 same-person cross-email merge** (Terka 93 → Terezka Slack 878, viz níž). MD5 hesla zachována 1:1, `migrate_from: legacy_md5` přehashuje na bcrypt při prvním přihlášení.

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
| Role: `record` | 1 (Danny M. — mrtvý štítek, viz analýza níž) |

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
| `pohlavi` | ~~`gender`~~ | **zahozeno 2026-06-12** — gender kompletně odstraněn (sloupec, enum `App\Enum\Gender`, import). Už se neimportuje. |
| `role` | `roles` | mapping níže |
| `enabled` | `isVerified` + nějaký `isActive` | enabled=0 → isActive=false (login zakázaný), ale data zachováme |

Mapping rolí:

| Legacy | New Symfony role | Pozn. |
|---|---|---|
| `guest` | `ROLE_USER` | default |
| `user` | `ROLE_USER` | (nebo přidat `ROLE_MEMBER` později — neřešit teď) |
| `admin` | `ROLE_ADMIN` | |
| `record` | `ROLE_USER` | mrtvý legacy štítek (1× Danny M.), bez funkce — nic k zachování, viz „`record` role — analýza" |

#### Duplicitní emaily — MERGE

**Pravidlo: jeden reálný člověk = jeden `User` v nové DB. Ale žádná data ze starých řádků se nesmí ztratit.**

Aktuálně 6 duplicit:

| Email | Keep | Drop | Důvod výběru |
|---|---|---|---|
| `j***@***m.cz` | 156 (Komi) | 157 (Kuba) | nejstarší ID |
| `j***@***t.sk` | 146 (shovky) | 147 (ďuroSR) | nejstarší ID |
| `L***@***m.cz` | 452 (Lili) | 455 (Lilli87) | nejstarší ID |
| `p***@***l.com` | 128 (Pepanek) | 197 (Pepek) | nejstarší ID |
| `v***@***l.cz` | **248 (Vlčák)** | 213, 848 | **má 1 přechod (2014) — držíme aktivní účet** |

**Mechanismus zachování dat:**

1. `User.legacyId` — primary kept ID (canonical)
2. `User.legacyMergedIds` — `int[]` (Doctrine `simple_array` nebo `json`) — list dropped IDs
3. `User.legacyDataSnapshot` — `json` — raw snapshot dropped rows + canonical row (full audit pro pozdější merge revize)
4. **Crossings remap**: pro každý `highline_prechody.uzivatel_id` se před importem konzultuje merge mapa: dropped ID se přepíše na kept ID. Tím historické přechody zůstanou pod merged účtem.

Algoritmus importu:

```
for each unique email in uzivatel:
    rows = all uzivatel rows with this email
    canonical = pick by rule (default: oldest id; výjimka u účtu 248)
    for each dropped row:
        log [MERGE] dropped uzivatel#X into uzivatel#Y (email "Z")
    create User with legacyId=canonical.id, legacyMergedIds=[dropped ids],
                    legacyDataSnapshot={canonical: {...}, dropped: [{...}]}
    add (dropped_id → canonical_id) entry to global merge map
final summary:
    "Imported N users, merged M legacy rows into N - M kept rows"
```

Když uživatel chce někdy v budoucnu duplicitu rozseknout zpátky, snapshot mu to dovolí.

#### Same-person merge (cross-email)

Email-grouping výš spojí jen řádky se **stejným** emailem. Pár účtů ale patří jednomu člověku pod **různými emaily** — ty se grupováním nechytí. Řeší je ruční tabulka `ImportUsersCommand::SAME_PERSON_MERGES` (`dropped uzivatel.id => canonical uzivatel.id`): dropnutý řádek se vytáhne z email-grupování a přilepí do skupiny canonicalu → **identický výstupní tvar jako u email-duplicity** (canonical drží svůj řádek, dropped id padne do `legacyMergedIds` + snapshotu, a přechody se přemapují přes `UserMergeMap`). Canonical = **aktivní** účet (ne nejstarší ID).

Aktuálně:

| Dropped | Canonical (keep) | Kdo | Proč |
|---|---|---|---|
| 93 (Terka, disabled) | **878 (Terezka Slack, active)** | Tereza P. | Stejný člověk, jiný email. 93 drží 1 přechod (Alter Weg 2011) → po merge visí na aktivním 878. Vyplynulo z `enabled=0` analýzy níž. |

#### Otázky k doptání (Kolouch / vedení CAS)

- ~~`record` role — co to bylo, máme něco zachovat?~~ — **vyřešeno analýzou 2026-06-12, viz níž** (od Koloucha už nic nezjistíme).
- ~~`enabled = 0` u 6 uživatelů~~ — **vyřešeno analýzou 2026-06-12, viz níž** (od Koloucha už nic nezjistíme).

#### enabled=0 — analýza (2026-06-12)

Otázka „login-disabled, nebo úplně skrýt?" je vedle — těch 6 účtů **není smysluplná kategorie reálných userů**. Jsou to test / joke / opuštěné účty, z toho jeden je duplicitní profil aktivního usera. Žádný nemá obsah, který by stál za zobrazení.

| legacy id | nick | kdo | verdikt |
|---|---|---|---|
| 93 | Terka | **Tereza P.** — duplicitní profil aktivního usera **878 „Terezka Slack"** (stejné jméno, jiný email → email-merge to nechytil). Má 1 přechod (Alter Weg, 2011-11-20, one way). | **Merge 93 → 878** (cross-email, stejný člověk); pak disabled řádek nic unikátního nedrží. |
| 124 | Cipísek | Joke účet (Cipísek Rumcajsů, rok nar. 1900), 0 aktivity. | junk |
| 129 | Barčík | Barbora K. — reálně vypadá, ale žádný alt profil, 0 aktivity. | opuštěná / deaktivovaná registrace, není co zobrazit |
| 845 | Test1 | Testovací účet Vejvise (jmeno „Vejvis", garbage email `a***@***d.cz`). Reálný účet = legacy **1 Vejvis**. | junk |
| 869 | jezevec | Test účet Petra P. (`p***@***l.com`). Reálný = **868** (Panda → dnes „Kokos"). | junk |
| 876 | ananas | Druhý test účet Petra P. (`p***@***s.cz`). Reálný = **868**. | junk |

**Závěr:** `enabled=0` v legacy ≈ „deaktivováno / test junk", ne „reálný user, kterého chceme zobrazit, ale zablokovat mu login". Současný import to mapuje správně (`enabled=0 → isActive=false`, login zakázán). 5 z 6 má 0 přechodů; jediný s daty (93 Terka) je duplicita aktivního účtu a jeho přechod patří na 878.

#### `record` role — analýza (2026-06-12)

Jediný nositel: **Danny M.** (id 149, Praha, aktivní — 49 přechodů, 5 prvovýstupů, vlastní 11 highlinů). Role **nic neguard-ovala**: zakládání lajn bylo otevřené všem (191 z 254 highlinů vlastní guests, ne admini/record). A Danny **není ani rekordman** — přechody #5 (49 vs. Peeto 78), prvovýstupy ~#5 (5 vs. Lukš 9), nejdelší přešlá lajna 102 m (celkový max je 230 m). „record" je tak **mrtvý štítek** na jednom power-userovi, bez dochovaného významu a bez navázané funkce.

**Závěr: není co zachovat.** Dannyho data (přechody, prvovýstupy, highliny) se importují normálně bez ohledu na roli. Mapování `record → ROLE_USER` je správné — je to běžný (aktivní) user. (Sedí i s filozofií appky: rekordy/žebříčky neřešíme.)

**Akce:**
1. ✅ **Merge legacy 93 → 878** (Tereza P.) — **hotovo (2026-06-12)**: zařazeno do importu přes `SAME_PERSON_MERGES` (viz „Same-person merge" výš). Při čerstvém importu se 93 nevytvoří jako vlastní User a její přechod (Alter Weg 2011) visí na aktivní 878. Ověřeno scratch importem: 878 má `legacy_merged_ids=[93]` + 2 přechody.
2. Zbylých 5 nechat `is_active=false` (už jsou). Test / joke / opuštěné, 0 dat — nikde nezobrazovat. Volitelně hard-delete 4 zjevné junk účty (124, 845, 869, 876); neškodí je ale nechat (žádné přechody).

---

### ✅ Highline crossings (přechody) — DONE

- Command: `app:import:highline-crossings`
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
    private ?CrossingStyle $style = null;       // enum string col length 20, viz crossing-styles.md
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
- Sdílí službu `App\Legacy\UserMergeMap` — singleton, kterou plní `app:import:users` (kept canonical + dropped → kept) a čte `app:import:highline-crossings` přes `userMergeMap->find($legacyUzivatelId)`. Tím se přechody pod dropped emailem automaticky napárují na canonical User.

#### Co NEbude v MVP

- Žádný ORM mapping pro legacy přechody — čteme přes DBAL `old` connection.
- Žádné UI pro přidávání přechodů přes nové appce. To řešíme později.

---

### ✅ Longline crossings (longline deník) — DONE (2026-06-13)

- Command: `app:import:longline-crossings`
- Source: `longline` (435 rows)
- Cílová entita: `App\Entity\LonglineCrossing`
- Výsledek: **414 / 435 importováno**, 21 skipů kvůli neplatnému `0000-00-00` datu
- **Žádná lajna / GPS** — longline se nezapisuje k highline, `place` (legacy `misto`) je volný text. Proto vlastní entita, ne `HighlineCrossing`.

Schema (implementováno v `App\Entity\LonglineCrossing`):

```php
class LonglineCrossing
{
    private ?int $id = null;
    private User $user;                          // ManyToOne, not nullable (po user merge → canonical)
    private \DateTimeImmutable $crossedAt;       // date_immutable (legacy `datum`)
    private int $length;                         // metry (legacy `delka`)
    private string $place;                       // legacy `misto` (varchar 30 → 120), volný text
    private ?CrossingStyle $style = null;        // sdílený enum s highline, viz níž
    private ?string $comment = null;             // nově (legacy nemá), pro nové zápisy v appce
    private ?int $legacyId = null;               // lookup index pro --truncate idempotenci
    private \DateTimeImmutable $createdAt;
}
```

Index `idx_longline_crossed_at` na `crossedAt`.

#### Mapování stylů — sdílený enum `App\Enum\CrossingStyle`

Legacy `longline.styl` má stejný volný slovník jako highline (`one way`, `OS fm`, `fm`, `OS`, `OS, fm`, `one w`), mapuje ho **stejné `CrossingStyle::fromLegacy()`** (doplněn alias `one w → OW`). Import nepotkal žádný neznámý styl. Leash-only styly (`swami`/`solo`/`kotník`) v longline datech nejsou a v UI pickeru se filtrují přes `CrossingStyle::appliesToLongline()`. (Kvůli tomuhle sdílení byl `HighlineCrossingStyle` přejmenován na `CrossingStyle`.)

#### Závislosti

- Vyžaduje, aby běžel **po** `app:import:users` (kvůli FK `User`)
- Sdílí `App\Legacy\UserMergeMap` stejně jako crossings import (`userMergeMap->find($legacyUzivatel)`)

---

### ❌ First ascents (`prvni_prechody_hl`) — NEIMPORTUJEME (rozhodnuto 2026-06-12)

124 záznamů „prvních přechodů" highline. **Tabulku importovat nebudeme.**

#### Proč ne

- **Historie už je zachovaná na lajně.** `Highline.firstAscentBy` (z legacy `highline.autor`) + `Highline.firstAscentDate` (z `highline.datum`) se importují u každé lajny — naplněné **225/254 (autor)** a **241/254 (datum)**. To pokrývá „kdo lajnu postavil / poprvé přešel + kdy", což je pro zachování historie dost.
- **Per-osobní tabulka je bordel a nestojí za vlastní entitu.** 63 lajn / 124 řádků, ~2 na lajnu = **týmové prvovýstupy** (jedna lajna až 8 lidí). Není to čisté „kdo přešel poprvé".
- **Data jsou nekonzistentní:** 49 orphanů (NULL `uzivatel_id`, jen text); některé řádky mají v jednom `nick` poli **celý tým** (`„Halfi, Lukš, Mišo, Malaf"`, `„Kwjet, Anče, Danny, Luky"`, `„Danny,Saša,Alex,K. Uhlík,Pída"`); stejný člověk je jednou linkovaný, jindy ne (`Lukš` 179 vs 0, `Pida`/`Píďa`). Tabulka nemá ani datum (jen `id, nick, styl, uzivatel_id, highline_id`).
- Do **deníčkové** appky (osobní zápisy přechodů) se týmový prvovýstup konceptuálně nehodí.

#### Důsledek

Žádný `app:import:first-ascents`, žádná entita `HighlineFirstAscent`. Kdyby se to někdy chtělo, surová data v legacy zůstávají. Prezentace historie = `firstAscentBy` / `firstAscentDate` na detailu lajny.

---

### ⏭️ Mimo scope

- ~~**Longline crossings**~~ — **HOTOVO 2026-06-13**, viz sekce „Longline crossings" výš
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
docker compose exec -T php bin/console app:import:highline-crossings --truncate
docker compose exec -T php bin/console app:import:longline-crossings --truncate
```

Zopakování od nuly = ruční reset DB před tím (a pokud je legacy MySQL prázdná, nejdřív `make loadLegacyDump`):

```bash
docker compose exec -T php bin/console doctrine:database:drop --force --if-exists
docker compose exec -T php bin/console doctrine:database:create
docker compose exec -T php bin/console doctrine:migrations:migrate -n
```
