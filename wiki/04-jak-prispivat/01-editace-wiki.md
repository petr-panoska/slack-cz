# Editace Wiki

Kapitoly se editují **[přímo na GitHubu](https://github.com/petr-panoska/slack-cz/tree/main/wiki)** — buď v editoru přes tlačítko 🖉, nebo pull requestem. Po mergi do `main` jsou změny live na <https://beta.slack.cz/wiki> (cache TTL 10 min).

- Filename `NN-slug.md` — `NN` určuje pořadí v sidebaru, slug jde do URL bez prefixu (`02-bezpecnost.md` → `/wiki/bezpecnost`).
- První `#` H1 v souboru = title kapitoly. Volitelný `> blockquote` hned pod ním se renderuje jako pull-quote.
- Když přidáváš novou kapitolu, šoupni ji do správné `NN-skupina/` složky a přidej řádek do root [`README.md`](../README.md) — H2 nadpis tam určuje, jak se skupina jmenuje v sidebaru.
- Obrázky můžou být inline jako reference-style base64 (`![][image1]` + `[image1]: data:image/png;base64,...` na konci souboru).