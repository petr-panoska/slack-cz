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

## Konvence

- Veškerý kód v `src/` je v EN (entity fields, methods, comments). UI a obsah pro uživatele v CS.
- Každá změna importní logiky musí zachovat **re-runnable** princip — viz `migration.md`.
- Pokud něco v dokumentaci zastará, oprav ji ve stejném commitu jako kód.
