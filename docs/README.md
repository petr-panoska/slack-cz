# slack-cz — interní dokumentace

Tento adresář dokumentuje rozhodnutí, architekturu a plány vývoje.
Je míněn primárně pro vývojáře projektu (CAS).

## Obsah

- [`architecture.md`](architecture.md) — co je postavené, struktura kódu, klíčová architekturní rozhodnutí
- [`migration.md`](migration.md) — strategie importu legacy dat ze staré MySQL DB do nové Postgres
- [`crossing-styles.md`](crossing-styles.md) — taxonomie stylů přechodů highline, mapování legacy → nový enum
- [`audio-player.md`](audio-player.md) — slackvibes 📻 přehrávač (states, storage, controller internals)
- [`dev.md`](dev.md) — operační cheat sheet (Docker, console, DB, cache, smoke testy)
- [`todo.md`](todo.md) — otevřené úkoly napříč projektem

## Kde hledat co

| Mám problém / chci… | Kouknout sem |
|---|---|
| Spustit Docker, console, smazat cache, smoke test endpointu | `dev.md` |
| Pochopit, proč je `App\Old\Entity\*` mimo `src/Entity/` | `architecture.md` § *Důležité* |
| Pustit legacy import (fresh / re-run) | `dev.md` § *Migrace + import* nebo `migration.md` § *Summary commands* |
| Rozumět merge strategii pro duplicitní emaily | `migration.md` § *Users — DONE* |
| Mapovat legacy `styl` string na enum | `crossing-styles.md` |
| Ladit YouTube feed / quota / cache | `architecture.md` § *Feed* |
| Cokoli kolem `slackvibes` audio playeru | `audio-player.md` |
| Vědět, co se má ještě udělat | `todo.md` |

## Konvence

- Veškerý kód v `src/` je v EN (entity fields, methods, comments). UI a obsah pro uživatele v CS.
- Každá změna importní logiky musí zachovat **re-runnable** princip — viz `migration.md`.
- Pokud něco v dokumentaci zastará, oprav ji ve stejném commitu jako kód.
