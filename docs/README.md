# slack-cz — interní dokumentace

Tento adresář dokumentuje rozhodnutí, architekturu a plány vývoje.
Je míněn primárně pro vývojáře projektu (CAS).

## Obsah

- [`architecture.md`](architecture.md) — co je postavené, struktura kódu, klíčová architekturní rozhodnutí
- [`migration.md`](migration.md) — strategie importu legacy dat ze staré MySQL DB do nové Postgres
- [`crossing-styles.md`](crossing-styles.md) — taxonomie stylů přechodů highline, mapování legacy → nový enum
- [`line-edits.md`](line-edits.md) — trust model + proposal queue pro highline edity (verifikace, audit log, ROLE_ADMIN)
- [`audio-player.md`](audio-player.md) — slackvibes 📻 přehrávač (states, storage, controller internals)
- [`dev.md`](dev.md) — operační cheat sheet (Docker, console, DB, cache, smoke testy)
- [`deploy.md`](deploy.md) — produkce (Hetzner VPS, Caddy, `make deploy`, `.github/workflows/deploy.yml`)
- [`roadmap.md`](roadmap.md) — strategická rozhodnutí do budoucna (Symfony 8.x upgrade assessment)
- [`todo.md`](todo.md) — otevřené úkoly napříč projektem

## Kde hledat co

| Mám problém / chci… | Kouknout sem |
|---|---|
| Spustit Docker, console, smazat cache, smoke test endpointu | `dev.md` |
| Pochopit, proč se legacy data čtou přes DBAL (ne ORM) | `architecture.md` § *Důležité* |
| Pustit legacy import (fresh / re-run) | `dev.md` § *Migrace + import* nebo `migration.md` § *Summary commands* |
| Rozumět merge strategii pro duplicitní emaily | `migration.md` § *Users — DONE* |
| Mapovat legacy `styl` string na enum | `crossing-styles.md` |
| Pochopit verifikační / proposal flow highline editů (trust model, audit log) | `line-edits.md` |
| Rozhodnout se o upgradu na Symfony 8.x (cesta, cena, deprecations) | `roadmap.md` |
| Ladit YouTube feed / quota / cache | `architecture.md` § *Feed* |
| Pochopit foto upload (WebP/HEIC, datum+GPS) nebo legacy foto import | `architecture.md` § *Foto galerie* + `migration.md` § *Line photos* |
| Pochopit `/galerie` (kronika po letech, justified grid, width/height sloupce) | `architecture.md` § *Hotové features → Galerie* + `todo.md` § *Galerie* |
| Přidat/upravit styly (Sass partialy, breakpoint mixiny, brand theme) | `architecture.md` § *Frontend* |
| Cokoli kolem `slackvibes` audio playeru | `audio-player.md` |
| Pochopit `App\Markdown\Section\*` (jak `/docs` a `/wiki` jedou ze stejného kódu) | `architecture.md` § *Markdown sections* |
| Deployovat (`make deploy`, GH Actions) | `deploy.md` § *Deploy* |
| Vědět, co se má ještě udělat | `todo.md` |

## Konvence

- Veškerý kód v `src/` je v EN (entity fields, methods, comments). UI a obsah pro uživatele v CS.
- Každá změna importní logiky musí zachovat **re-runnable** princip — viz `migration.md`.
- Pokud něco v dokumentaci zastará, oprav ji ve stejném commitu jako kód.
