# Line edit / verification

Trust model + proposal queue subsystém pro editaci lajn (`LineEdit`). Otevřené pro autenticated usery, ale s curation gate-em na verified lajny.

## Trust model

| Stav lajny | Owner edit | Cizí user edit | Admin edit | Owner delete | Admin delete |
|---|---|---|---|---|---|
| `unverified` (čerstvě přidaná + **všech 254 legacy**) | direct ✓ | proposal ⇒ queue¹ | direct ✓ | direct ✓ | direct ✓ |
| `verified` (jen admin-schválené) | proposal ⇒ queue | proposal ⇒ queue | direct ✓ | ✗ | direct ✓ |

¹ Edit (návrh) může poslat **kdokoli přihlášený** — jde do admin fronty; direct edit jen owner/admin. Mazání je přísnější: cizí user nemaže (jen owner unverified / admin).

**Vzkaz:** kdokoliv logged-in si může vytvořit „svou lajnu" a libovolně na ní dělat změny. Admin pak rozhodne, jestli ji verifikuje (= povýší do shared sady, kde každá změna jde přes review) nebo ji nechá osobní.

> **⚠️ Legacy 254 jsou `unverified` (záměrně).** Migrace `Version20260510113004` sice obsahuje `UPDATE highline SET is_verified = TRUE WHERE legacy_id IS NOT NULL`, ale je to **no-op**: migrace běží nad *prázdnou* DB **před** importem (`make migrate` → `make legacyImport`), takže UPDATE potká 0 řádků a import pak lajny vytvoří s defaultem `false`. Ponecháno tak schválně — admin musí legacy data postupně pročistit (= verifikovat). **Důsledek:** legacy lajny mají `createdBy = null` (nikoho), takže je **napřímo edituje/maže jen admin**. Běžný přihlášený uživatel ale **může poslat návrh úpravy** (jde do admin fronty) — to platí pro každého, kdo není owner/admin, na jakékoli lajně. Direct edit/delete je gated na owner/admin (viz `edit()` — `canEditDirect = isAdmin || (isOwner && !isVerified)`).

**„navrženo" štítek** na detailu lajny (`not isVerified`) se renderuje **jen adminovi** (`is_granted('ROLE_ADMIN')`) — je to signál pro verifikační workflow (de facto adminův cleanup checklist), ne info pro běžného návštěvníka. Jinak by visel na všech neověřených lajnách včetně 254 legacy.

## Entity

### `Line` (rozšířená, viz `src/Entity/Line.php`)

```
+ isVerified  bool      DEFAULT false   — gate flag
+ createdBy   ?User     ON DELETE SET NULL — autor submisse, NULL pro 254 legacy řádků
```

Helper: `Line::isOwnedBy(?User): bool` — null `createdBy` → vždy `false` (legacy nejsou ničí).

### `LineEdit` (`src/Entity/LineEdit.php`)

Unified record změn. Slouží zároveň jako audit log i jako pending queue.

```
id, line FK, proposedBy ?User, snapshot json, status enum, createdAt, reviewedBy ?User, reviewedAt ?datetime
```

`status` (enum `App\Enum\LineEditStatus`):

- **`applied`** — změna už je na lajně. Vytváří se při direct edit (owner of unverified, admin) a při schválení proposalu.
- **`pending`** — návrh ve frontě, čeká na admina.
- **`rejected`** — admin návrh zamítl. Řádek se nemaže (audit).

Index `idx_line_edit_status` — admin queue často filtruje `WHERE status='pending'`.

`snapshot` = celá množina form-bound polí jako JSON. Pro `applied` rows = post-change stav, pro `pending`/`rejected` = navrhované hodnoty (které se ještě neaplikovaly). Derived sloupec `length` (haversine z point1/point2) je v snapshotu taky uložený. (Lajna nemá žádné separátní `latitude`/`longitude` — lokace = oba kotvící body; viz `docs/architecture.md` § Mapa.)

## Routes

| Path | Name | Auth | Co dělá |
|---|---|---|---|
| `GET\|POST /lajna/pridat` | `app_line_new` | `ROLE_USER` | nová lajna; submit ⇒ `unverified` + `createdBy=user` + audit `APPLIED` |
| `GET\|POST /lajna/{slug}/uprava` | `app_line_edit` | `ROLE_USER` | direct edit (owner of unverified / admin) NEBO proposal (verified + non-admin) |
| `POST /lajna/{slug}/smazat` | `app_line_delete` | `ROLE_USER` | owner-of-unverified nebo admin |
| `POST /lajna/{slug}/overeni` | `app_line_verify` | `ROLE_ADMIN` | flag `isVerified = true` + sloučí dosavadní edity do jediné creation revize |
| `GET /admin/navrhy` | `app_admin_proposals` | `ROLE_ADMIN` | queue pending proposals + diff tabulky |
| `POST /admin/navrhy/{id}/schvalit` | `app_admin_proposal_approve` | `ROLE_ADMIN` | apply snapshot + flag `APPLIED` |
| `POST /admin/navrhy/{id}/zamitnout` | `app_admin_proposal_reject` | `ROLE_ADMIN` | flag `REJECTED` |
| `GET /lajna/{slug}/historie` | `app_line_history` | public | audit log (APPLIED + REJECTED) chronologicky |
| `POST /lajna/{slug}/historie/{editId}/smazat` | `app_line_history_delete` | `ROLE_ADMIN` | smaže jeden záznam historie (kromě prvního, chronologicky); stav lajny se nemění |

`/lajna/pridat` má `priority: 10` aby se nepřevrstvila s `/lajna/{slug}` (slug regex `[a-z0-9-]+` matchne i „pridat").

## Form (`src/Form/LineForm.php`)

18 polí. Required: name, type, height, point1Lat/Lng, point2Lat/Lng. Slug se generuje automaticky z `name` přes `AsciiSlugger` (collision = numerický suffix).

**Verified lajny mají `name` zamčený** — `disabled: true` (server-side ignoruje POSTed value) + 🔒 ikonka u labelu s tooltipem ukazujícím URL a důvod. Slug se nikdy nepřegenerovává po vytvoření, takže zámek na názvu chrání URL stabilitu (existující odkazy / bookmarky / tisk nepřestanou fungovat). Nezamčené názvy jsou jen u unverified lajn (autor je ladí před verifikací).

**Length NENÍ ve formu** — odvozuje se v `LineCrudController::deriveGeometry()` z point1/point2 přes haversine (v metrech, zaokrouhleno). Lajna nemá žádné separátní `latitude`/`longitude` (zahozené v migraci `Version20260609120000`) — lokace je daná výhradně dvěma kotvícími body; reprezentativní jeden bod = `point1`.

GPS picker je Stimulus `line_form_map_controller.js` — 2 markery s alternujícím placement na klik (1 → 2 → 1 → …), oba draggable, polyline + live distance overlay. Sync se 4 inputs oboustranně.

## Direct edit vs proposal — implementace

```php
$queueProposal = !$isAdmin && $line->isVerified();

if ($form->isValid()) {
    $this->deriveGeometry($line);

    if ($queueProposal) {
        $snapshot = $this->snapshot($line);
        $em->refresh($line);                     // discard in-memory changes
        $proposal = new LineEdit(...PENDING, snapshot=$snapshot);
        $em->persist($proposal); $em->flush();
    } else {
        $em->flush();                            // direct apply
        $audit = $this->buildEdit(...APPLIED, snapshot=$this->snapshot($line));
        $em->persist($audit); $em->flush();
    }
}
```

**Klíč: `EntityManager::refresh()` po extrakci snapshotu.** Form-bound entita má v paměti změny, které DOCHCEŇ v proposal flow nepersistovat. `refresh()` znovu načte řádek z DB a tím změny zahodí.

## Admin queue UI (`/admin/navrhy`)

Pro každý pending edit se přepočítá `diff(currentSnapshot, proposedSnapshot)` — pole, kde se hodnoty liší. Render tabulkou (Pole / Před / Po), strikethrough na `before`, magenta highlight na `after`. Schválit / Zamítnout = POST formy s CSRF tokeny `proposal-approve-{id}` / `proposal-reject-{id}`.

## Audit log

Každá `APPLIED` row v `line_edit` je trvalý záznam změny. Sjednoceno tak, aby:

- direct edit (owner of unverified, admin) → 1 APPLIED row
- approved proposal → 1 APPLIED row (původní PENDING se updatne na APPLIED, ne nová row)
- rejected proposal → 1 REJECTED row (zachovaná pro historii)

To dává uniform `findForLine(Line)` query která vrátí kompletní timeline změn bez ohledu na trasu jakou prošly.

### History view (`/lajna/{slug}/historie`)

Veřejný read-only audit log + admin stack-pop kurátoring.

Každý `LineEdit` row si u sebe nese **vlastní `beforeSnapshot`** (stav lajny PŘED tím, co tahle úprava udělala) i `snapshot` (po). Diff se počítá render-time přímo z těchto dvou polí, **ne** ze sousedního řádku, takže každá řádka přesně reflektuje *co tahle úprava změnila* nezávisle na tom, co s historií dělá admin.

- První APPLIED v pořadí má `beforeSnapshot = NULL` → render „Vytvořeno", bez diffu
- Ostatní APPLIED rows mají vlastní before+after diff
- PENDING / REJECTED rows mají `beforeSnapshot` zachycený v okamžiku vytvoření návrhu

**Mazání = stack pop**: admin smaže jen úplně poslední (chronologicky) revizi, ne libovolnou ze středu. Po pop-u se zavolá `applySnapshot(newLatestApplied)` na entitu — lajna se vrátí do stavu, který byl před smazaným editem. Audit log = zdroj pravdy pro stav lajny.

**Sloučení historie při verifikaci**: když admin lajnu verifikuje, audit se vyčistí — všechny dosavadní revize (typicky rename-y z unverified éry) se zahodí a místo nich se zapíše jediná APPLIED creation s aktuálním stavem. Důvod: po verifikaci je name + slug zamknutý, takže by stack-pop přes původní rename revize jinak vrátil lajnu do stavu se starým názvem, ale slugem už nesynchronizovaným. Sloučením začíná verified éra s čistým historickým seedem.

Proč stack-pop a ne free-deletion: smazání prostředního řádku by zanechalo nekonzistenci — sousední rows nemůžou „pohltit" změny smazaného (díky vlastnímu `beforeSnapshot`), ale stav lajny by se nemohl jednoznačně přepočítat. Stack-pop tu nejednoznačnost odstraňuje úplně.

Tlačítko „Smazat poslední revizi" se zobrazí jen u nejnovější rows v listu, a jen když existuje víc než 1 záznam (creation samotný se smazat nedá).

## ROLE_ADMIN

Granted přes console:

```bash
docker compose exec -T php bin/console app:admin:grant <email>
docker compose exec -T php bin/console app:admin:grant <email> --revoke
```

Aktuální admin: `p***@***l.com` (ID 251).

`ROLE_ADMIN` automaticky implikuje `ROLE_USER` (Symfony role hierarchy default). V `User::getRoles()` se ROLE_USER připíše vždy navíc.

## Naming gotcha — `isVerified`

`User.isVerified` (email confirmation, dělá `symfonycasts/verify-email-bundle`) **vs** `Line.isVerified` (admin-curated quality stamp) jsou dvě nesouvisející věci se stejným názvem v jiných entitách. Doctrine je nemíchá (jiný namespace), ale při review může mást — zejména pokud se v jednom souboru objeví `$user->isVerified()` i `$line->isVerified()`. Při čtení kódu zkontroluj na čem se to volá.
