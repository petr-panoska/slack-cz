# Roadmap

Strategické věci do budoucna, které nejsou „udělej teď" (to je v `todo.md`), ale
potřebují rozhodnutí / přípravu / sledování. Každá sekce je psaná tak, aby se k ní
dalo vrátit po měsících bez rozběhu — fakta, zdroje, a konkrétní další krok.

---

## Symfony 8.x upgrade

**Stav k 2026-06-01:** běžíme na **Symfony 7.4.13 LTS** (čerstvě upgradnuto z 7.3,
viz commit `4bb9f0d`). PHP: **prod 8.3** (Hetzner, native), **dev + CI 8.4**.

### TL;DR rozhodnutí
Zůstat na **7.4 LTS** je legitimní (supported do 2028/2029, nulová údržba). Skok na
8.x je **proveditelný a Symfony ho i oficiálně doporučuje**, ale pro tenhle projekt
má reálnou cenu (prod PHP 8.4 + `doctrine/dbal` 4 kaskáda + ověření bundlů +
deprecation cleanup) a **žádnou killer feature**, kterou by appka potřebovala.
→ **Default: počkat a skočit rovnou na 8.4 LTS (11/2027), LTS→LTS.** Dřív jen pokud
přijde konkrétní důvod (feature / dependency to vynutí).

### Jak to s 8.x doopravdy je (nepřekrucovat)
- **8.0 = 7.4 − deprecations.** Stejný feature set, 8.0 jen vyhazuje deprecation
  vrstvy. Není to „velký scary major", je to úklid.
- **Oficiální cesta:** (1) na 7.4 ✅ → (2) vyčistit **všechny** deprecations →
  (3) skok na 8.0. Nejde přeskočit, dokud kód používá deprecated API.
- **Symfony oficiálně doporučuje jet regular verze (8.x), ne kempit na LTS** — kvůli
  featurám + průběžnému čištění deprecations po malých krocích. „Zůstaň na LTS" je
  konzervativní volba, ne oficiální linie.

### Verze, support, PHP
| Verze | Vyšlo | Bugfix | Security | PHP |
|---|---|---|---|---|
| 7.4 LTS | 11/2025 | do 11/2028 | do 11/2029 | ≥ 8.2 |
| 8.0 | 27.11.2025 | ~7/2026 | ~7/2026 | ≥ 8.4 |
| 8.1 | ~5/2026 | krátký cyklus | krátký cyklus | ≥ 8.4.1 |
| **8.4 LTS** (cíl) | ~11/2027 | ~3 roky | ~4 roky | ? |

Regular verze = treadmill: 8.0→8.1→8.2→8.3→8.4 každého půl roku, jinak vypadneš ze
supportu. Proto pro low-maintenance projekt dává smysl LTS→LTS.

### Cena pro TENHLE projekt (konkrétně)
1. **Prod PHP 8.3 → 8.4** — infra zásah: `scripts/setup-server.sh` (apt `php8.3-*`
   → `php8.4-*`), `scripts/deploy.sh` (`reload php8.4-fpm`), `scripts/check-server-env.sh`
   (min verze + FPM socket `/run/php/php8.4-fpm.sock`), `docs/deploy.md`. Ubuntu 24.04
   ships 8.3 → nutný `ondrej/php` PPA. 8.1 vyžaduje 8.4.1.
2. **`doctrine/dbal ^3 → ^4`** — vynucená kaskáda (`symfony/doctrine-messenger` 8.x
   chce `dbal ^4.3`). **DBAL 4 má vlastní BC breaky** → netriviální.
3. **Bundle 8.x podpora** — hlavní tření (potvrzeno jolicode reportem; oni museli
   forkovat bundly bez `^8`). Kandidáti na lag v našem stacku: `liip/imagine-bundle`,
   `vich/uploader-bundle`, doctrine stack (`doctrine-bundle`, `orm`,
   `doctrine-migrations-bundle`). **Toto je nutné empiricky ověřit — viz dry-run níž.**
4. **Deprecation cleanup v našem kódu** — checklist níž.

### Deprecation checklist (náš kód, dle UPGRADE-8.0)
Tyhle reálně potkáme; část jde uklidit hned na 7.4 jako bezriziková příprava:
- [ ] **`UserInterface::eraseCredentials()` odstraněno** → náš `User` entity to skoro
      jistě implementuje (maker to generuje). Migrace na `__serialize()`.
- [ ] **Console commandy** (`app:user:*`, `app:import:*`, `app:admin:grant`,
      `app:edit:sync-from-history`) — `Command::getDefaultName()/getDefaultDescription()`
      pryč → `#[AsCommand]` attribute (ověřit, jestli už ho používají).
- [ ] **`vich/uploader` `@Uploadable` annotation → attribute** (deprecation už vidět
      na 7.4, viz `src/Entity/HighlinePhoto.php`).
- [ ] **`liip_imagine.twig.mode: lazy`** v configu (umlčí Liip deprecation).
- [ ] **security:** `hide_user_not_found` → `expose_security_errors` (`'none'`/`'all'`/
      `'account_status'`) — ověřit `config/packages/security.yaml`.
- [x] **Stateless CSRF** (`framework.csrf_protection.check_header: true`) — **už máme**
      (přišlo s 7.4 recepty, `config/packages/ux_turbo.yaml`), to je rovnou 8.x směr.
- [ ] **Validator constraints: array options → named arguments** (`symfony/validator` 7.3
      deprecation) — **POTVRZENO** smoke testem: render `RegistrationForm` spouští 3 přímé
      deprecations (`IsTrue`, `NotBlank`, `Length`). Skoro jistě i v dalších formech
      (`HighlineForm`, `HighlineCrossingForm`, …). Projít všechny `#[Assert\*([...])]`
      s array syntaxí → přepsat na named args. Blokuje rozšíření smoke testů o form-routy.

Detekce: `php bin/phpunit --display-deprecations`. Máme zatím jen **3 smoke testy**
DB-free rout (`tests/Controller/PublicPagesSmokeTest.php`) — ty už ale jednu vrstvu
deprecations odhalily (viz checklist výš), takže každý další smoke test na form-rendering
routu funguje jako detektor. Plus ručně přes `bin/console debug:container --deprecations`
+ projít web/profiler. Direct = náš kód, Indirect = bundly (report maintainerům / čekat
na jejich `^8` release; `phpunit.dist.xml` má `ignoreIndirectDeprecations="true"`, takže
CI failuje jen na našich přímých).

### BC breaky 8.1 navíc (nad 8.0)
`HttpFoundation::getInt()/getBoolean()` vyhodí výjimku místo `0/false`; `DomCrawler`
vynucuje `LIBXML_NONET`; nové deprecations (DI tagged locators, `Bundle::registerCommands()`).
Nic killer pro nás, ale 8.1 = další BC dávka → další důvod cílit rovnou na 8.4 LTS.

### ⚠️ Otevřená empirická neznámá: poskládá to composer?
Tohle je rozhodující a **zatím NEpotvrzené** (dry-run byl rozdělaný, neproběhl celý).
Než se cokoliv rozhodne, pustit full-stack resolution test (nedestruktivní, vrací
composer.json zpět):

```bash
cp composer.json /tmp/composer.json.real
sed -i 's/7\.4\.\*/8.1.*/g' composer.json                       # nebo 8.0.*
sed -i 's#"doctrine/dbal": "\^3"#"doctrine/dbal": "^4"#' composer.json
sed -i 's#"liip/imagine-bundle": "\^2\.15"#"liip/imagine-bundle": "^2.15 || ^3.0"#' composer.json
sed -i 's#"vich/uploader-bundle": "\^2\.9"#"vich/uploader-bundle": "^2.9 || ^3.0"#' composer.json
docker compose run --rm php composer update -W --dry-run --ignore-platform-reqs 2>&1 | head -60
cp /tmp/composer.json.real composer.json && rm /tmp/composer.json.real   # RESTORE
```
Výstup řekne, jestli to leze (dry-run plán) nebo na kterém balíčku to padá
(„requires symfony/… ^7.0"). **Pozn.:** dřívější test, co držel `dbal ^3` a bundly
napevno, dával falešný „blocked" — proto se musí bumpnout celý stack najednou.

### Trigger pro přehodnocení (ne kalendář)
1. `liip/imagine-bundle` + `vich/uploader-bundle` + doctrine stack mají `^8` release
   (ověřit dry-runem výš), **a zároveň**
2. ideálně až vyjde **8.4 LTS (~11/2027)** → skočit LTS→LTS, ne na krátké 8.1.
3. Nebo dřív, pokud konkrétní 8.x feature / dependency to vynutí.

### Zdroje (k 2026-06-01)
- <https://symfony.com/8> — feature přehled, PHP 8.4 requirement
- <https://symfony.com/blog/preparing-for-symfony-7-4-and-symfony-8-0> — „8.0 = 7.4 −
  deprecations", upgrade cesta, LTS tabulka, oficiální doporučení jet regular
- <https://symfony.com/blog/symfony-8-0-0-released> — release 27.11.2025
- <https://jolicode.com/blog/our-experience-upgrading-a-project-to-symfony-8> —
  real-world: hlavní tření = bundly bez `^8`, řešili forky; kód = routing
  annotations→attributes, array constraints→named args
- <https://github.com/symfony/symfony/blob/8.1/UPGRADE-8.0.md> — BC breaky 8.0
- <https://github.com/symfony/symfony/blob/8.1/UPGRADE-8.1.md> — BC breaky 8.1
