# Pravidla pro práci v tomhle repu

## Styly (SCSS) — TVRDÁ PRAVIDLA, žádné výjimky

Porušení těchto pravidel = nepoužitelný výstup, i kdyby „to fungovalo".

1. **BEM, striktně** (viz getbem.com/naming — skutečný BEM, ne „něco s pomlčkami"):
   - **Blok**: samostatná komponenta, kebab-case — `.line-feed`.
   - **Element**: část bloku, **dvojité podtržítko** — `.line-feed__header`,
     `.line-feed__item`. Elementy se nevnořují (`.line-feed__list__item` ✗ →
     `.line-feed__item` ✓); vazbu na stav řeší selektor
     `.line-feed--collapsed .line-feed__header { }`.
   - **Modifikátor** (včetně stavů): **dvojitá pomlčka** — `.line-feed--collapsed`,
     `.line-feed__item--active`. Vždy VEDLE základní třídy, nikdy místo ní.
     Žádné `is-*` / `has-*` — stav = boolean modifikátor.
   - Jen třídové selektory (žádné tagy/ID).
   - **NIKDY nepoužít třídy cizího bloku v markupu jiné komponenty** — sdílený
     vzhled = mixin, nebo existující utility třída.
   - Starší kód v repu má ještě plochý zápis (`.crossing-feed-header`) — umírá
     průběžně s přepisy; nový/dotčený kód píšu ve správném BEM.
   - **Povinný self-check před dokončením**: vypsat všechny NOVĚ zavedené třídy
     (grep diffu, včetně tříd v JS stringách a Twigu!) a každou zkontrolovat
     proti vzoru `blok`, `blok__element`, `blok--modifikátor`. Plochý zápis
     jsem už dvakrát protlačil i po zavedení pravidla — třídy vznikající
     v JS šablonách kontrolovat zvlášť.
2. **Napřed číst, pak psát.** Před zásahem do souboru nastudovat jeho konvence:
   jazyk komentářů (SCSS = anglicky), tokeny (`var(--color-accent)`,
   `--color-line`, `--color-fg`, `--color-muted`), existující sdílené třídy
   (`.map-ctrl-btn` pro mapové pilulky) a breakpoint mixiny
   (`media-up`/`media-down` z `_breakpoints.scss` — žádné px v `@media`).
3. **Žádné vymýšlení vizuálu.** Hodnoty (barvy, paddingy, bordery, stíny)
   minimalisticky a z tokenů; dekorativní rozhodnutí dělá user, ne já.
   Moje přidaná hodnota je struktura a chování.
4. **Po každé iteraci úklid.** Mrtvá pravidla smazat hned; nikdy neopravovat
   override přes override. Když se návrh změní, přepsat blok, ne vrstvit.
5. **Sahám-li na starý kód, nechám ho hezčí.** Pokud se dotknu bloku, který
   pravidla porušuje, srovnat ho v rámci téže změny (po dohodě s userem,
   pokud jde o velký zásah).

Kontext: 2026-07-07 jsem styly map boxu zprasil (půjčené třídy cizího bloku,
override-špagety, mrtvá pravidla, míchání jazyků) — user to označil za komplet
na přepsání. Tohle se nesmí opakovat.

## Ostatní (zkráceně, detail v memory)

- Kód anglicky (identifikátory i SCSS komentáře), veřejné URL a UI texty česky.
  Twig komentáře česky (zavedená konvence).
- Nikdy `git add` bez vyzvání; user revidutje přes `git diff`.
- Repo je veřejné — žádné reálné PII v commitovaných souborech.
- Sass build: `make dcSassBuild` (kontejner `php` musí běžet).
