# Deploy / produkce

Staging instance nové Symfony aplikace běží na **<https://beta.slack.cz>**. Cutover na `slack.cz` ještě neproběhl — legacy PHP app stále běží na starém boxu (`154.43.62.26`).

Kompletní audit setup procesu je na produkčním serveru v `/root/deploy.log` (`ssh deploy@... && sudo cat /root/deploy.log`). Tenhle dokument je high-level reference; deploy.log je ground truth co se reálně spustilo.

## Infrastruktura na první pohled

Tři prostředí:

| Prostředí | Kde | Co tam je | Spravované jak |
|---|---|---|---|
| **Dev** | tvůj laptop, Docker Compose | Apache + PHP-FPM + Postgres 16 + MySQL legacy + Adminer + Mailpit. Bind-mount repa. | `docker compose` + `make dc*` targety, viz `dev.md` |
| **CI** | GitHub Actions (`.github/workflows/deploy.yml`) | ephemeral Ubuntu runner. Triggered push do `main` + `workflow_dispatch`. Joby: `preflight` → `deploy`. | YAML inlinuje `ssh ... 'bash -s' < scripts/<X>.sh`, žádný copy-paste z `deploy.sh` |
| **Prod (beta.slack.cz)** | Hetzner CX22 (`178.105.81.158`), Ubuntu 24.04 | **Native** PHP 8.3 + Postgres 16 + Caddy + ufw + fail2ban. Žádný Docker. App v `/var/www/slack-cz` jako `deploy` user. | Skripty v `scripts/`, viz tabulka níž |

Pět skriptů drží celou prod stranu. **Skript = spec.** Když přidáš novou závislost (PHP extension, writable dir, env klíč), updatuj patřičný skript ve stejném commitu jako kód — další deploy padne fail-fast, dokud server nedoinstaluješ.

| Skript | Kdy se pouští | Co dělá |
|---|---|---|
| **`scripts/setup-server.sh`** | 1× při fresh louce (`ssh root@HOST`), pak občas update-za-chodu (`ssh deploy@HOST`) | Idempotentní provisioning: apt packages (PHP+ext, Postgres, Caddy přes vlastní apt repo), `deploy` user + NOPASSWD sudoers, `git clone`, Postgres role/DB/.env.local atomicky, ACL pro `www-data` na writable dirs, enable+start systemd. **Nikdy** nepřepisuje `.env.local`, nedropuje DB, nezasahuje do Caddyfile. Spustí se přes `make setupServer`. |
| **`scripts/check-server-env.sh`** | Před každým deployem (lokál i CI), volitelně manuálně před `git push` | Preflight gate. Verifikuje git stav (server HEAD vs lokál), PHP+extensions, GD WebP, sys binárky, služby, FS perms `www-data`, `.env.local` klíče, Postgres connect, pending migrace. Exit 1 = deploy zhasne. Spustí se přes `make checkServerEnv`. |
| **`scripts/check-caddy.sh`** | Vedle `check-server-env.sh` před každým deployem (lokál i CI) | Drift gate. SSH `sudo cat /etc/caddy/Caddyfile`, diff vs `infra/Caddyfile`. Exit 1 = drift, deploy zhasne. Spustí se přes `make checkCaddy`. |
| **`scripts/deploy-caddy.sh`** | Manuálně kdykoliv změníš `infra/Caddyfile` | scp do `/tmp` → `sudo caddy validate` → atomic cp do `/etc/caddy/Caddyfile` → `sudo systemctl restart caddy` (NE reload, viz Gotchas) + smoke test. Spustí se přes `make deployCaddy`. **NIKDY** se nevolá z `make deploy` ani z CI — Caddy restart je side-effect, který má vlastní rozhodovací bod. |
| **`scripts/deploy.sh`** | Při každém deployi (lokál `make deploy` i CI `deploy` job) | `git pull`, `mkdir -p` writable dirs, `composer install --no-dev`, `doctrine:migrations:migrate`, `asset-map:compile`, `cache:clear` (×2 — composer hook + explicit), `cache:pool:clear cache.app`, `systemctl reload php8.3-fpm`. |
| **`scripts/sync-beta-restore.sh`** | Po `make syncBetaFromLocal` (přes SSH stdin) | Destruktivní psql restore lokálního dumpu na betač + cache:clear. Pouze staging fáze. |

Flow při běžné změně kódu:

```
git commit + push → CI:preflight (check-server-env.sh + check-caddy.sh) → CI:deploy (deploy.sh)
                                       │
                                       ↓ pokud kterýkoli fail
                                  deploy se nezačne, server zůstává na předchozí verzi
```

Flow při změně závislostí (nová PHP ext, writable dir, env klíč):

```
1. update spec v skriptu (setup-server.sh PKGS list / check-server-env.sh REQUIRED_*)
2. update kód
3. commit oboje spolu
4. ssh deploy@HOST → make setupServer (nebo přes Makefile target setupServer)
5. push → CI preflight projde → deploy projde
```

Cesta zpět ke konkrétním detailům je v sekcích dál v tomhle dokumentu (Server, Stack, Aplikace, Caddy, Operace, Gotchas).

## Server

| | |
|---|---|
| Provider | Hetzner Cloud |
| Tarif | CX22 (x86_64, 2 vCPU, 4 GB RAM, 40 GB NVMe), ~€4.5/měs + IPv4 ~€0.7/měs |
| IPv4 | `178.105.81.158` |
| IPv6 | `2a01:4f8:1c18:6966::1/64` |
| OS | Ubuntu 24.04.3 LTS (kernel 6.8.0-111-generic) |
| Hostname | `slack-cz-prod` |
| Timezone | Europe/Prague |

## Přístup

```bash
ssh -i ~/.ssh/slack_cz_prod deploy@178.105.81.158
```

| | |
|---|---|
| User | `deploy` (uid 1000, sudoer s NOPASSWD via `/etc/sudoers.d/deploy`) |
| SSH key | ed25519 (lokálně `~/.ssh/slack_cz_prod`, na serveru v `/home/deploy/.ssh/authorized_keys`) |
| Root SSH | **zakázán** (`PermitRootLogin no` v `/etc/ssh/sshd_config`) |
| Password auth | **zakázán** (`PasswordAuthentication no`) |
| Backup sshd config | `/etc/ssh/sshd_config.pre-lockdown.bak` (na revert) |

> ⚠ Klíč `~/.ssh/slack_cz_prod` je **bez passphrase** (dedicated deploy key, ne osobní). Drž ho v bezpečí — ztráta = vykop někoho přes Hetzner Console (KVM web shell) → znovu nahrát pubkey do `/home/deploy/.ssh/authorized_keys`.

## Bezpečnost

| Opatření | Stav |
|---|---|
| `ufw` firewall | aktivní, povolené `22/tcp`, `80/tcp`, `443/tcp` |
| `fail2ban` | aktivní, default jail `sshd` (5 fails / 10 min → 10 min ban) |
| Automatické security upgrades | dosud manuální, plánovat `unattended-upgrades` |

## Stack (native, žádný Docker)

| Vrstva | Komponenta | Verze | Endpoint |
|---|---|---|---|
| Reverse proxy + auto-HTTPS | Caddy | 2.11.2 | `:80`, `:443` |
| App | PHP-FPM | 8.3.6 | unix socket `/run/php/php8.3-fpm.sock` |
| DB | PostgreSQL | 16.13 | `127.0.0.1:5432` (jen lokálně) |
| Composer | apt package | 2.7.1 | — |

PHP extenze: `pgsql`, `mbstring`, `xml`, `curl`, `zip`, `intl`, `opcache`, `readline`, `mysql` (+ `mysqli`/`pdo_mysql` — Doctrine registruje `old` connection na boot, nevyužívá ji), `gd`, `exif` (foto galerie — origin EXIF strip + thumby přes liip_imagine).

> **Žádný MySQL na prod.** Legacy MySQL je jen v dev pro import. Pro produkci se data převedou lokálně do Postgresu a pošlou jako `pg_dump` na server.

## Aplikace

| | |
|---|---|
| Path | `/var/www/slack-cz` |
| Owner | `deploy:deploy` |
| Repo | `https://github.com/petr-panoska/slack-cz.git` (public, HTTPS clone) |
| Branch | `main` |
| `.env.local` | mode `640`, owner `deploy:www-data` (PHP-FPM jako `www-data` musí číst) |
| `var/cache`, `var/log` | ACL pro `www-data` (`rwX` recursive + default), plus `deploy` (kvůli composer scriptům) |
| `public/uploads/`, `public/media/cache/` | ACL pro `www-data` (`rwX` recursive + default). Uploads = origin fotky (`vich/uploader`), cache = liip_imagine on-demand thumby. Caddy `file_server` je servíruje staticky. |

`.env.local` obsahuje (na serveru, nikde jinde):
```
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<random hex 32>
DATABASE_URL=postgresql://slack_cz:<random>@127.0.0.1:5432/slack_cz?serverVersion=16&charset=utf8
OLD_DATABASE_URL=mysql://nobody:nobody@127.0.0.1:3306/none?serverVersion=8.0
MAILER_DSN=null://null
DEFAULT_URI=https://beta.slack.cz
```

> `DEFAULT_URI` čte `framework.router.default_uri` (viz `config/packages/routing.yaml`) — bez něj by CLI commandy (např. `app:user:reset-password`) generovaly URL na `http://localhost`. Po cutoveru na apex změnit na `https://slack.cz`.
>
> **Pozn. k názvu:** do Symfony 7.3 se proměnná jmenovala `APP_URL` (vlastní název), od 7.4 jedeme konvenci Flex recipe `DEFAULT_URI`. Beta `.env.local` na to byla přejmenovaná 2026-06-01. Při ruční editaci hodnoty pozor na perms — viz *`.env.local` musí číst www-data*.

> ⚠ DB heslo a `APP_SECRET` jsou random vygenerované při setupu, **NIKDE jinde nezálohované**. Když se ztratí `.env.local`, je třeba znovu vytvořit Postgres roli + heslo. Až bude vault / secrets management, přesunout sem.

## Postgres

```sql
-- Role + DB (vytvořeno setupem)
CREATE ROLE slack_cz WITH LOGIN PASSWORD '<random>';
CREATE DATABASE slack_cz OWNER slack_cz;
GRANT ALL ON SCHEMA public TO slack_cz;
```

Doctrine migrations spuštěné `php bin/console doctrine:migrations:migrate` během deploy. **Žádná legacy data zatím** — schema je prázdné kromě Doctrine struktury.

Připojení z deploy usera:
```bash
sudo -u postgres psql -d slack_cz
```

## Caddy

**Source of truth: [`infra/Caddyfile`](../infra/Caddyfile) v repu.** Server kopii v `/etc/caddy/Caddyfile` udržujeme synchronně přes `make deployCaddy`. Drift detekuje `make checkCaddy` (volá se taky automaticky jako preflight v `make deploy` — fail-fast na neshodu).

| Akce | Příkaz | Co dělá |
|---|---|---|
| Drift check | `make checkCaddy` | SSH `sudo cat /etc/caddy/Caddyfile`, diff vs `infra/Caddyfile`. Exit 1 = drift. |
| Push verze z repa | `make deployCaddy` | scp → `sudo caddy validate` → atomic cp → `sudo systemctl restart caddy` + smoke test. |
| Stáhnout server verzi | `ssh deploy@beta 'sudo cat /etc/caddy/Caddyfile' > infra/Caddyfile` | Kdyby někdo edit-nul ručně na serveru a chceš to zpětně commitnout (raději ne). |

Workflow: změnu Caddy konfigurace dělej v `infra/Caddyfile`, commitni do repa, pusť `make deployCaddy`. Nikdy needituj `/etc/caddy/Caddyfile` přímo na serveru — drift check ti to při příštím deploy zachytí, ale udržíš tím repo jako jediný kanonický stav.

| | |
|---|---|
| Cert pro `beta.slack.cz` | Let's Encrypt E8, valid 90 dní (auto-renew) |
| Logy | `journalctl -u caddy` (default systemd journal, custom log files se nepoužívají kvůli apparmor restrikcím) |
| Restart | `sudo systemctl restart caddy` (NE `reload` — pokud se zasekne, viz "Gotchas") |

## DNS

Cloudflare drží zónu `slack.cz` (NS: `gemma.ns.cloudflare.com`, `thaddeus.ns.cloudflare.com`).

Aktuálně přidané záznamy pro novou app:

| Type | Name | Value | Proxy |
|---|---|---|---|
| A | `beta` | `178.105.81.158` | DNS-only (gray cloud) |
| AAAA | `beta` | `2a01:4f8:1c18:6966::1` | DNS-only |

**Proč DNS-only (gray cloud):** Caddy auto-HTTPS přes Let's Encrypt potřebuje, aby HTTP-01 / TLS-ALPN-01 challenge dorazila přímo na origin. Když je Cloudflare proxy ON (orange cloud), CF zachytí request a Caddy cert nedostane. Pro produkci s CF proxy je řešení Cloudflare Origin Certificate nebo DNS-01 challenge přes CF API token — řešíme až později.

Po cutoveru: přidat `slack.cz` (A + AAAA) → `178.105.81.158` + AAAA, ze stejných důvodů zatím gray cloud.

## Operace

### SSH dovnitř

```bash
ssh -i ~/.ssh/slack_cz_prod deploy@178.105.81.158
```

### Audit log setupu

```bash
sudo cat /root/deploy.log
```

Každý setup krok je tam s timestamp, vstupy, výstupy. Když se chceš podívat, "kde jsme přesně udělali co", jdi sem.

### Symfony console (jako deploy user, prod env)

```bash
cd /var/www/slack-cz
APP_ENV=prod php bin/console <cmd>
```

Pro úkony co potřebují čtení `.env.local` jako `www-data` (test perms):
```bash
sudo -u www-data bash -c "cd /var/www/slack-cz && APP_ENV=prod php bin/console <cmd>"
```

### Reset hesla / aktivace účtu, když nechodí maily

Dokud nebude nasazený externí SMTP relay (viz Cutover TODO níž), mailer na betě je `null://null` — registrační maily i password-reset maily se tiše zahazují. Workaround přes konzoli:

```bash
ssh -i ~/.ssh/slack_cz_prod deploy@178.105.81.158
cd /var/www/slack-cz

# 1) Najdi usera (filtr substringem, nebo --unverified pro neaktivované)
APP_ENV=prod php bin/console app:user:list -s pepa
APP_ENV=prod php bin/console app:user:list --unverified

# 2) Vygeneruj password-reset URL (email nebo id). URL pošli userovi ručně
#    (Signal, Messenger, SMS, ...). Token je jednorázový a má lifetime z bundlu.
APP_ENV=prod php bin/console app:user:reset-password panda@example.com
APP_ENV=prod php bin/console app:user:reset-password 42
```

URL se generuje s absolute scheme + host přes `framework.router.default_uri` (= `DEFAULT_URI` v `.env.local`). Po cutoveru na `slack.cz` přepiš `DEFAULT_URI` v `/var/www/slack-cz/.env.local` na `https://slack.cz` a `sudo systemctl reload php8.3-fpm`. Po editaci ověř perms (`640 deploy:www-data` — viz *`.env.local` musí číst www-data*).

### Sync dat z lokálu na betu

Když chceš dostat aktuální stav lokální Postgres DB (highlines, users, crossings, ...) na `beta.slack.cz`:

```bash
make syncBetaFromLocal
```

Co target dělá:

1. `pg_dump` z kontejneru `slack-cz-database-1` (plain SQL, `--clean --if-exists --no-owner --no-privileges`) do `/tmp/slack-cz.sql`.
2. `scp` přes `~/.ssh/slack_cz_prod` na `deploy@178.105.81.158:/tmp/slack-cz.sql`.
3. Na serveru spustí `scripts/sync-beta-restore.sh` (přes SSH stdin), který:
   - vytáhne `DATABASE_URL` z `/var/www/slack-cz/.env.local`,
   - **strippne query string** (`?serverVersion=...&charset=...`) — Doctrine formát, který `psql` neumí,
   - `psql -v ON_ERROR_STOP=1 -f /tmp/slack-cz.sql` jako role `slack_cz` (správný ownership),
   - `APP_ENV=prod cache:clear --no-warmup` jako `www-data`,
   - vypíše row counts pro sanity check,
   - smaže `/tmp/slack-cz.sql` na serveru.
4. Lokální `/tmp/slack-cz.sql` smaže taky.

> ⚠ **Destruktivní na betě.** Dump má `DROP TABLE IF EXISTS` pro všechny app tabulky → kompletní replace. Dokud je beta jen staging bez vlastních dat, je to OK. Až bude na betě reálný traffic / uživatelské změny, nahradit za `INSERT ... ON CONFLICT` flow nebo migraci diff.

> ⚠ Drží to **stejnou Doctrine migration version** na lokále i betě (`doctrine_migration_versions` se kopíruje). Po sync běž rovnou `make` deploy nebo manuální `composer install + migrate -n` jen když na lokále přibyla nová migrace.

### Deploy

```bash
make deploy
```

Co target dělá:
1. SSH na `deploy@178.105.81.158` přes `~/.ssh/slack_cz_prod`.
2. Spustí `scripts/deploy.sh` (přes `bash -s` stdin):
   - `git pull --ff-only origin main`
   - `composer install --no-dev --optimize-autoloader`
   - `APP_ENV=prod doctrine:migrations:migrate -n`
   - `APP_ENV=prod asset-map:compile`
   - `APP_ENV=prod cache:clear`
   - `APP_ENV=prod cache:pool:clear cache.app` (vyhodí docs/wiki LKG fallback)
   - `sudo systemctl reload php8.3-fpm` (refresh opcache)
3. Smoke test: HTTP status pro `/`, `/mapa`, `/wiki`, `/docs`, `/o-projektu`.

**Předpoklad**: commit + push do `origin/main` máš lokálně hotový — `make deploy` jen tahá na serveru.

#### Ruční deploy (kdyby SSH script selhal)

```bash
ssh -i ~/.ssh/slack_cz_prod deploy@178.105.81.158
cd /var/www/slack-cz
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
APP_ENV=prod php bin/console doctrine:migrations:migrate -n
APP_ENV=prod php bin/console asset-map:compile
APP_ENV=prod php bin/console cache:clear
sudo systemctl reload php8.3-fpm
```

> ⚠ Pořadí matters: `composer install` spustí přes post-install hooks `cache:clear` + `assets:install` + `importmap:install` (viz Gotchas). Náš následný `asset-map:compile` proto MUSÍ jít až po něm — jinak by `manifest.json` odkazoval na staré hashe. Druhý explicitní `cache:clear` není redundantní: vyčistí cache, kterou si composer hook právě naplnil starými hashe.

> `--ff-only` chrání před tím, abys neúmyslně nemergeoval, kdyby na betě někdo udělal lokální commit (typicky když SSH-režíruješ nějaký quick fix).

> CI workflow `.github/workflows/deploy.yml` zrcadlí přesně tenhle flow — `preflight` job spustí `scripts/check-server-env.sh` přes SSH, na něm `needs:` má `deploy` job, který spustí `scripts/deploy.sh` přes SSH `bash -s`. Žádné inline duplikace.

### Post-deploy smoke

Rychlý sanity check po deployi (běží lokálně, target je beta):

```bash
for path in / /mapa /login /register /reset-password /profile /o-projektu; do
  printf '%s  %s\n' "$(curl -s -o /dev/null -w '%{http_code}' https://beta.slack.cz$path)" "$path"
done

# /profile by mělo 302 (anon redirect na /login). Zbytek 200.

# Ověř, že nový CSS hash je v HTML a obsahuje aktuální classes:
CSS=$(curl -s https://beta.slack.cz/login | grep -oE 'assets/styles/app-[a-zA-Z0-9_-]+\.css' | head -1)
echo "CSS asset: $CSS"
curl -s "https://beta.slack.cz/$CSS" | grep -cE 'auth-page|panel'   # nenulové = nové styly živé
```

### Logy aplikace

```bash
# Symfony prod log (vytvoří se při prvním logu)
tail -f /var/www/slack-cz/var/log/prod-*.log

# Caddy (HTTP přístupy, cert events)
sudo journalctl -u caddy -f

# PHP-FPM
sudo tail -f /var/log/php8.3-fpm.log

# fail2ban
sudo journalctl -u fail2ban -n 50
```

### Restart služeb

```bash
sudo systemctl restart php8.3-fpm
sudo systemctl restart caddy
sudo systemctl restart postgresql
sudo systemctl restart fail2ban
```

## Cutover na slack.cz — TODO

Než se traffic přepne ze starého `154.43.62.26` na nový VPS:

1. **Doimport legacy dat** — lokálně dotáhnout MySQL → Postgres user/highline/crossing import (částečně hotovo dle `migration.md`), pak `pg_dump` z lokálu, `pg_restore` na produkci.
2. **MAILER_DSN** — externí SMTP relay (Brevo / Mailgun / Postmark / ...), ne vlastní mailserver. Hetzner blokuje port 25 outbound default.
3. **DNS swap pro `slack.cz`** v Cloudflare:
   - Změnit A z legacy IP na `178.105.81.158`
   - Přidat AAAA na `2a01:4f8:1c18:6966::1`
   - Gray cloud (DNS-only) kvůli Caddy auto-HTTPS
4. **Caddyfile pro `slack.cz`** — v `infra/Caddyfile` přidat blok analogický k `beta.slack.cz` (sdílejí `@photos` matcher). Pokud chceme `www.slack.cz` redirect na apex, přidat redir block. Pak `make deployCaddy`.
5. **YouTube API key + GitHub PAT** — dostat reálné hodnoty do `.env.local` (`YOUTUBE_API_KEY`, `DOCS_GITHUB_TOKEN`).
6. **(volitelné) `unattended-upgrades`** pro automatické security patche.
7. **(volitelné) snapshot/backup** přes Hetzner Cloud — pravidelné snapshoty disku stojí ~20 % ceny serveru.

## Gotchas (z reálného setupu, nech tu, ať to nezblbneš znovu)

### Caddy reload se zasekne, když config selhal

Když ti `systemctl reload caddy` vrátí timeout (zejména po prvním deploy), pravděpodobně předchozí `ExecReload` selhal s "permission denied" nebo podobně, a systemd visí v `reloading` stavu. Recovery:

```bash
sudo systemctl reset-failed caddy
sudo systemctl restart caddy
```

Restart (ne reload) zabije starý proces a startuje fresh.

### `.env.local` musí číst www-data

Default mode 600 / owner `deploy:deploy` způsobí 500 — PHP-FPM jako `www-data` nemůže `.env.local` přečíst. Musí být:

```bash
sudo chown deploy:www-data /var/www/slack-cz/.env.local
sudo chmod 640 /var/www/slack-cz/.env.local
```

Pozor na **in-place editaci**: `sed -i` (i `> .env.local` redirect) soubor přepíše nově, čímž zahodí `640 deploy:www-data` a vrátí default owner/mode. Po každé takové úpravě perms znovu nastav (viz výš). Preflight (`check-server-env.sh`) to jinak odchytí jako `.env.local NOT readable by www-data` a deploy zablokuje.

### DNS records dřív než Caddy site block

Když přidáš site blok do Caddyfile **před** tím, než existuje DNS pro doménu, Caddy začne hammerovat ACME challenge → public resolvery (zejména Google 8.8.8.8) si **uloží NXDOMAIN do negativní cache** podle SOA min TTL zóny (`slack.cz` má 1800s = 30 min).

Po přidání DNS pak browsery uživatelů mohou ještě hodinu vidět jen AAAA (pokud byla přidána později než A) → fail na "no IPv4 + no IPv6 routing doma".

Pravidlo: **vždy nejdřív DNS, pak Caddy**. Když se to udělá obráceně, čekat 30 min nebo přepnout DNS upstream na 1.1.1.1 / 9.9.9.9.

### `psql` neumí Doctrine `DATABASE_URL` query string

Symfony / Doctrine zapisuje DSN jako `postgresql://user:pass@host:port/db?serverVersion=16&charset=utf8`. Když ten celý URL pošleš do `psql`, vrátí:

```
psql: error: invalid URI query parameter: "serverVersion"
```

Fix: před voláním `psql` strippni vše po `?`:

```bash
DB_URL=$(grep -E '^DATABASE_URL=' .env.local | sed -E 's/^DATABASE_URL=//; s/^"//; s/"$//; s/\?.*$//')
psql "$DB_URL" -c "..."
```

Tohle dělá `scripts/sync-beta-restore.sh` — kdybys chtěl ručně něco pgčíst na betě, použij stejný pattern.

### Doctrine `old` connection vyžaduje `php-mysql`

I když na prod nepoužíváme MySQL, `config/packages/doctrine.yaml` registruje DBAL `old` connection na boot. Bez `php8.3-mysql` extension by se Doctrine ani nezavedlo. Setup ho instaluje, `OLD_DATABASE_URL` v `.env.local` ukazuje do prázdna (`mysql://nobody:nobody@127.0.0.1:3306/none`), connection se nikdy neotevře.

### `var/log` neexistuje, dokud Symfony poprvé nezaloguje

Na čistém serveru po prvním deployi je `var/cache/` (vytvořila composer post-install hook), ale `var/log/` ještě **ne** — Symfony ji vytváří lazily při prvním zápisu logu. Když si do deploy skriptu šoupneš defenzivní `chown -R deploy:www-data var/log` před prvním zalogováním, padne `chown: cannot access 'var/log': No such file or directory`.

Buď:

```bash
[ -d var/log ] && sudo chown -R deploy:www-data var/log
```

nebo nech ji vzniknout přirozeně (po prvním requestu se vytvoří jako `www-data`, což je správně — PHP-FPM tam zapisuje).

### Preflight check (`make checkServerEnv` + CI gate)

Před každým deployem (lokální `make deploy` i GH Actions push-to-main) se pouští `scripts/check-server-env.sh` jako fail-fast gate:

- Lokálně: Makefile `deploy: checkServerEnv` dependence.
- CI: `.github/workflows/deploy.yml` má samostatný `preflight` job, `deploy` job na něm `needs:`. Stejný skript, stejný SSH klíč (`DEPLOY_SSH_KEY` secret).

Skript běží na serveru přes SSH a verifikuje:

- Git stav (server HEAD vs lokální HEAD, kolik commitů pozadu, jestli přibyly nové migrace / composer.lock změny)
- PHP verze ≥ 8.3, všechny vyžadované extensions (`pgsql`, `gd`, `exif`, …) + GD má WebP support
- Systémové binárky (`composer`, `caddy`, `psql`, `setfacl`)
- Běžící služby (`caddy`, `php8.3-fpm`, `postgresql`)
- FS perms pro PHP-FPM (`www-data` umí zapsat do `var/cache`, `var/log`, `public/uploads`, `public/media/cache`)
- `.env.local` čitelná `www-data`em + obsahuje všechny očekávané klíče
- Postgres `DATABASE_URL` connect funguje
- Pending Doctrine migrace (info)

Když cokoli failne, deploy se vůbec nezačne (exit 1 → CI job fail → `deploy` job se neaktivuje, lokálně `make deploy` rovněž zhasne). Pustit samostatně před push:

```bash
make checkServerEnv
```

To je doporučená cesta pro „pre-push manual check" — žádný git hook se neinstaluje, devloák co chce fast feedback prostě pustí target před `git push`.

**Skript = spec.** Když přidáš novou závislost (PHP extension, writable dir, env klíč) updatuj `scripts/check-server-env.sh` ve stejném commitu jako kód. Lokální verze skriptu je kanonický expected-state — server se kontroluje proti tomu, co se chystá deploynout, ne proti aktuální server-side verzi.

Skript spoléhá na to, že `deploy` user má `NOPASSWD sudoers` pro `sudo -u www-data ...` (jinak FS-perm checky se přeskočí s warningem). Setup sudoers entry:

```bash
# /etc/sudoers.d/deploy (visudo -f)
deploy ALL=(ALL) NOPASSWD: ALL
```

### Server provisioning — `scripts/setup-server.sh`

Jediný idempotentní skript pro dvě role:

1. **Fresh louka** — čerstvé Hetzner image (Ubuntu 24.04). Spustit jako root:
   ```bash
   ssh root@HOST 'bash -s' < scripts/setup-server.sh
   ```
   Nainstaluje apt packages (PHP 8.3 + ext, Postgres 16, Caddy přes vlastní apt repo, git, acl, …), vytvoří `deploy` usera + NOPASSWD sudoers, naklonuje repo do `/var/www/slack-cz`, vytvoří Postgres roli + DB + `.env.local` atomicky (random `APP_SECRET` + DB heslo, mode 640, owner `deploy:www-data`), nastaví ACL pro `www-data` na writable adresáře (`var/cache`, `var/log`, `public/uploads`, `public/media/cache`), enable + start systemd služeb.

2. **Update za chodu** — když přibyla nová závislost (PHP extension, writable dir). Spustit jako deploy:
   ```bash
   ssh deploy@HOST 'bash -s' < scripts/setup-server.sh
   ```
   Stejný skript, ale díky idempotenci no-opuje vše už hotové. Reálně se přeinstalují jen nově přidané apt packages a refresh ACL. `.env.local` se v žádném scénáři nepřepisuje.

**Co skript NEDĚLÁ úmyslně:**
- nezasahuje do `/etc/caddy/Caddyfile` — řízeno separátně přes `infra/Caddyfile` + `make deployCaddy` (viz sekce Caddy výš)
- nedropuje žádné Postgres role / DB / data
- nepřepisuje `.env.local` — pokud existuje, skipuje regeneraci `APP_SECRET`/DB hesla (jejich ztrátu chceme prevent)

**„Weird state" detekce:** pokud existuje `.env.local`, ale Postgres role chybí (nebo opačně), skript exit 1 s instrukcí ručního recovery — odmítne uhodnout heslo a generovat nový (rozbil by connect / cache invalidace).

Po fresh setupu zbývá ručně:
1. Caddy konfig — push z repa: `make deployCaddy` (předtím v `infra/Caddyfile` doplň site blok pro novou doménu, viz sekce Caddy výš)
2. SSH klíč pro `deploy` (`ssh-copy-id`) — pokud setup běžel jako root, deploy zatím SSH nemá
3. GH repo secret `DEPLOY_SSH_KEY` (privátní klíč pro deploy usera)
4. DNS records v Cloudflare na IP tohoto serveru

Pak `make checkServerEnv` + `make checkCaddy` z lokálu pro verifikaci.

### Composer post-install scripts spouštějí cache:clear v prod

Po `composer install --no-dev` Symfony auto-spustí `cache:clear`, `assets:install`, `importmap:install`. To je důvod proč `var/cache` musí být writable PHP procesem ještě před prvním `bin/console cache:clear`.

## Reference

- [Hetzner Cloud Docs](https://docs.hetzner.com/cloud/)
- [Caddy Docs](https://caddyserver.com/docs/)
- [Symfony Deployment best practices](https://symfony.com/doc/current/deployment.html)
