# Styly highline přechodů

Legacy `highline_prechody.styl` (a stejně tak `prvni_prechody_hl.styl`) je `varchar(10)` bez kontroly hodnot, takže v něm je bordel z let 2010+.

## Distinct hodnoty v legacy DB (highline_prechody, 995 řádků)

| Legacy hodnota | Count | Význam |
|---|---|---|
| `OS fm` | 466 | OS FM — oba směry přejité OS (každý směr na první pokus, bez pádu) |
| `fm` | 265 | FM — celá lajna v kuse bez pádu (nemusí být první pokus) |
| `one way` | 124 | Přechod jedním směrem |
| `OS, fm` | 53 | **Pozor — neplatí jako duplikát `OS fm`!** Jeden směr OS, druhý směr v kuse ale ne na první pokus (FM) |
| `-` | 37 | Prázdné / nezadané |
| `OS` | 36 | OS — jeden směr, první pokus, bez pádu |
| `AF` | 8 | „All Free" (z lezeckého slangu) — celkové přelezení s odsedy/pády |
| `swami` | 5 | Slackline-specific (technické info, neřešit detail) |
| `solo` | 1 | Bez jištění (technicky info o jištění, ale ponecháno jako styl) |

## Definice (verifikováno s Kolouchem)

- **OS** = On Sight — první pokus, **jeden směr**, bez pádu
- **OS FM** = On Sight Full Man — OS na **oba směry**, oba na první pokus, bez pádu
- **OS, FM** (s čárkou!) — jeden směr OS, druhý směr ale FM (ne první pokus, jen v kuse). **Nezamlouvat s `OS FM`.**
- **FM** = Full Man — lajna přejitá v kuse bez pádu (může být n-tý pokus)
- **AF** = převzato z lezení („All Free"); přejití s odsedy / pády
- **OW** = One Way — jeden směr (nezáleží na pokusu)
- **swami** = slackline-specific, vlastní význam, **neřešit detail**
- **solo** = patří spíš k jištění, ale historicky se zaznamenalo jako styl — necháme tak
- **kotnik** = explicitně zmíněn jako možná hodnota, **v aktuální legacy DB ale není** — nech v enumu

## Cílový enum

`App\Enum\HighlineCrossingStyle` (PHP backed enum).

```php
enum HighlineCrossingStyle: string
{
    case OS_FM    = 'os_fm';     // OS FM — oba směry OS
    case OS_THEN_FM = 'os_fm_split'; // OS, FM — jeden směr OS, druhý FM
    case OS       = 'os';
    case FM       = 'fm';
    case OW       = 'ow';        // one way
    case AF       = 'af';
    case SWAMI    = 'swami';
    case SOLO     = 'solo';
    case KOTNIK   = 'kotnik';
}
```

## Mapování legacy → enum

| Legacy text | Enum | Pozn. |
|---|---|---|
| `OS fm` | `OS_FM` | |
| `OS, fm` | `OS_THEN_FM` | čárka rozlišuje! |
| `OS` | `OS` | |
| `fm` | `FM` | |
| `one way` | `OW` | |
| `AF` | `AF` | |
| `swami` | `SWAMI` | |
| `solo` | `SOLO` | |
| `-` | `null` | nezadané — ponechat jako null |
| `''` (empty) | `null` | jako výše |
| jiné neočekávané | log warning + `null` | report v importu |

V `HighlineCrossing.style` je sloupec **nullable** (`null` = neuvedeno).

## Display labels (CS)

```php
public function label(): string
{
    return match ($this) {
        self::OS_FM      => 'OS FM',
        self::OS_THEN_FM => 'OS, FM',
        self::OS         => 'OS',
        self::FM         => 'FM',
        self::OW         => 'OW (one way)',
        self::AF         => 'AF',
        self::SWAMI      => 'swami',
        self::SOLO       => 'solo',
        self::KOTNIK     => 'kotník',
    };
}
```

## Otevřené otázky

- **`OS, FM` vs `OS FM`** — během 10+ let psali přechody různí lidé různě. Není zaručené, že legacy data tu čárku používala konzistentně. Možná je vhodné při importu hodit warning u všech `OS, fm` a Kolouch / nějaký jiný admin to odsouhlasí ručně.
- **`kotnik`** — nikde v aktuálních datech není, ale Kolouch ho zmínil. Pokud po pár měsících provozu nebude použitý, můžeme z enumu vypadnout.
