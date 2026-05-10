# Highline edit / verification

Trust model + proposal queue subsystém pro highline data. Otevřené pro autenticated usery, ale s curaation gate-em na verified lajny.

## Trust model

| Stav lajny | Owner edit | Cizí user edit | Admin edit | Owner delete | Admin delete |
|---|---|---|---|---|---|
| `unverified` (čerstvě přidaná) | direct ✓ | ✗ access denied | direct ✓ | direct ✓ | direct ✓ |
| `verified` (legacy 254 + admin-schválené) | proposal ⇒ queue | proposal ⇒ queue | direct ✓ | ✗ | direct ✓ |

**Vzkaz:** kdokoliv logged-in si může vytvořit „svou lajnu" a libovolně na ní dělat změny. Admin pak rozhodne, jestli ji verifikuje (= povýší do shared sady, kde každá změna jde přes review) nebo ji nechá osobní.

## Entity

### `Highline` (rozšířená, viz `src/Entity/Highline.php`)

```
+ isVerified  bool      DEFAULT false   — gate flag
+ createdBy   ?User     ON DELETE SET NULL — autor submisse, NULL pro 254 legacy řádků
```

Helper: `Highline::isOwnedBy(?User): bool` — null `createdBy` → vždy `false` (legacy nejsou ničí).

### `HighlineEdit` (`src/Entity/HighlineEdit.php`)

Unified record změn. Slouží zároveň jako audit log i jako pending queue.

```
id, highline FK, proposedBy ?User, snapshot json, status enum, createdAt, reviewedBy ?User, reviewedAt ?datetime
```

`status` (enum `App\Enum\HighlineEditStatus`):

- **`applied`** — změna už je na lajně. Vytváří se při direct edit (owner of unverified, admin) a při schválení proposalu.
- **`pending`** — návrh ve frontě, čeká na admina.
- **`rejected`** — admin návrh zamítl. Řádek se nemaže (audit).

Index `idx_highline_edit_status` — admin queue často filtruje `WHERE status='pending'`.

`snapshot` = celá množina form-bound polí jako JSON. Pro `applied` rows = post-change stav, pro `pending`/`rejected` = navrhované hodnoty (které se ještě neaplikovaly). Derived sloupce (length, latitude, longitude — počítané z point1/point2) jsou v snapshotu taky uloženy.

## Routes

| Path | Name | Auth | Co dělá |
|---|---|---|---|
| `GET\|POST /highline/nova` | `app_highline_new` | `ROLE_USER` | nová lajna; submit ⇒ `unverified` + `createdBy=user` + audit `APPLIED` |
| `GET\|POST /highline/{slug}/upravit` | `app_highline_edit` | `ROLE_USER` | direct edit (owner of unverified / admin) NEBO proposal (verified + non-admin) |
| `POST /highline/{slug}/smazat` | `app_highline_delete` | `ROLE_USER` | owner-of-unverified nebo admin |
| `POST /highline/{slug}/verify` | `app_highline_verify` | `ROLE_ADMIN` | flag `isVerified = true` |
| `GET /admin/navrhy` | `app_admin_proposals` | `ROLE_ADMIN` | queue pending proposals + diff tabulky |
| `POST /admin/navrhy/{id}/schvalit` | `app_admin_proposal_approve` | `ROLE_ADMIN` | apply snapshot + flag `APPLIED` |
| `POST /admin/navrhy/{id}/zamitnout` | `app_admin_proposal_reject` | `ROLE_ADMIN` | flag `REJECTED` |

`/highline/nova` má `priority: 10` aby se nepřevrstvila s `/highline/{slug}` (slug regex `[a-z0-9-]+` matchne i „nova").

## Form (`src/Form/HighlineForm.php`)

18 polí. Required: name, type, height, point1Lat/Lng, point2Lat/Lng. Slug se generuje automaticky z `name` přes `AsciiSlugger` (collision = numerický suffix).

**Length / latitude / longitude NEJSOU v formu** — odvozují se v `HighlineCrudController::deriveGeometry()` z point1/point2 přes haversine (length v metrech, zaokrouhleno) + midpoint (lat/lng pro mapový marker).

GPS picker je Stimulus `highline_form_map_controller.js` — 2 markery s alternujícím placement na klik (1 → 2 → 1 → …), oba draggable, polyline + live distance overlay. Sync se 4 inputs oboustranně.

## Direct edit vs proposal — implementace

```php
$queueProposal = !$isAdmin && $highline->isVerified();

if ($form->isValid()) {
    $this->deriveGeometry($highline);

    if ($queueProposal) {
        $snapshot = $this->snapshot($highline);
        $em->refresh($highline);                 // discard in-memory changes
        $proposal = new HighlineEdit(...PENDING, snapshot=$snapshot);
        $em->persist($proposal); $em->flush();
    } else {
        $em->flush();                            // direct apply
        $audit = $this->buildEdit(...APPLIED, snapshot=$this->snapshot($highline));
        $em->persist($audit); $em->flush();
    }
}
```

**Klíč: `EntityManager::refresh()` po extrakci snapshotu.** Form-bound entita má v paměti změny, které DOCHCEŇ v proposal flow nepersistovat. `refresh()` znovu načte řádek z DB a tím změny zahodí.

## Admin queue UI (`/admin/navrhy`)

Pro každý pending edit se přepočítá `diff(currentSnapshot, proposedSnapshot)` — pole, kde se hodnoty liší. Render tabulkou (Pole / Před / Po), strikethrough na `before`, magenta highlight na `after`. Schválit / Zamítnout = POST formy s CSRF tokeny `proposal-approve-{id}` / `proposal-reject-{id}`.

## Audit log

Každá `APPLIED` row v `highline_edit` je trvalý záznam změny. Sjednoceno tak, aby:

- direct edit (owner of unverified, admin) → 1 APPLIED row
- approved proposal → 1 APPLIED row (původní PENDING se updatne na APPLIED, ne nová row)
- rejected proposal → 1 REJECTED row (zachovaná pro historii)

To dává uniform `findForHighline(Highline)` query která vrátí kompletní timeline změn bez ohledu na trasu jakou prošly.

**Future work:** view nad audit logem (`/highline/{slug}/historie`) + revert button — viz `todo.md`.

## ROLE_ADMIN

Granted přes console:

```bash
docker compose exec -T php bin/console app:admin:grant <email>
docker compose exec -T php bin/console app:admin:grant <email> --revoke
```

Aktuální admin: `panda09823@gmail.com` (ID 251).

`ROLE_ADMIN` automaticky implikuje `ROLE_USER` (Symfony role hierarchy default). V `User::getRoles()` se ROLE_USER připíše vždy navíc.

## Naming gotcha — `isVerified`

`User.isVerified` (email confirmation, dělá `symfonycasts/verify-email-bundle`) **vs** `Highline.isVerified` (admin-curated quality stamp) jsou dvě nesouvisející věci se stejným názvem v jiných entitách. Doctrine je nemíchá (jiný namespace), ale při review může mást — zejména pokud se v jednom souboru objeví `$user->isVerified()` i `$highline->isVerified()`. Při čtení kódu zkontroluj na čem se to volá.
