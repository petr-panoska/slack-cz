# Deploy / produkce

Staging instance nové Symfony aplikace běží na **<https://beta.slack.cz>**. Cutover na `slack.cz` ještě neproběhl — legacy PHP app stále běží na starém boxu (`154.43.62.26`).

Kompletní audit setup procesu je na produkčním serveru v `/root/deploy.log` (`ssh deploy@... && sudo cat /root/deploy.log`). Tenhle dokument je high-level reference; deploy.log je ground truth co se reálně spustilo.

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

PHP extenze: `pgsql`, `mbstring`, `xml`, `curl`, `zip`, `intl`, `opcache`, `readline`, `mysql` (+ `mysqli`/`pdo_mysql` — Doctrine registruje `old` connection na boot, nevyužívá ji).

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

`.env.local` obsahuje (na serveru, nikde jinde):
```
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=<random hex 32>
DATABASE_URL=postgresql://slack_cz:<random>@127.0.0.1:5432/slack_cz?serverVersion=16&charset=utf8
OLD_DATABASE_URL=mysql://nobody:nobody@127.0.0.1:3306/none?serverVersion=8.0
MAILER_DSN=null://null
```

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

`/etc/caddy/Caddyfile` (zálohovaný default je v `Caddyfile.default.bak`):

```caddyfile
{
    email panda09823@gmail.com
}

# Bare IP (HTTP only — LE neumí vystavit cert pro IP)
http://178.105.81.158, http://[2a01:4f8:1c18:6966::1] {
    root * /var/www/slack-cz/public
    encode gzip zstd
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
}

# Doménový přístup s auto-HTTPS přes Let's Encrypt
beta.slack.cz {
    root * /var/www/slack-cz/public
    encode gzip zstd
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
}
```

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

### Manuální deploy (před tím, než bude .github/workflows/deploy.yml přepsaný)

```bash
ssh -i ~/.ssh/slack_cz_prod deploy@178.105.81.158
cd /var/www/slack-cz
git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
APP_ENV=prod php bin/console doctrine:migrations:migrate -n
APP_ENV=prod php bin/console asset-map:compile
APP_ENV=prod php bin/console cache:clear
sudo systemctl reload php8.3-fpm
```

> Současný `.github/workflows/deploy.yml` cílí na **staré** `154.43.62.26` (legacy box) a dělá jen `git pull && composer install` — pro nový server nestačí ani vzdáleně. Přepsat při cutoveru.

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
3. **`.github/workflows/deploy.yml`** — přepsat na nový VPS s plným flow:
   - Změnit host na `178.105.81.158`, user na `deploy`
   - Přidat kroky `composer install --no-dev`, `doctrine:migrations:migrate -n`, `asset-map:compile`, `cache:clear`, `systemctl reload php8.3-fpm`
   - Secret `deploy_key` v GitHub Actions = obsah `~/.ssh/slack_cz_prod` private key
4. **DNS swap pro `slack.cz`** v Cloudflare:
   - Změnit A z legacy IP na `178.105.81.158`
   - Přidat AAAA na `2a01:4f8:1c18:6966::1`
   - Gray cloud (DNS-only) kvůli Caddy auto-HTTPS
5. **Caddyfile pro `slack.cz`** — přidat blok analogický k `beta.slack.cz`. Pokud chceme `www.slack.cz` redirect na apex, přidat redir block.
6. **YouTube API key + GitHub PAT** — dostat reálné hodnoty do `.env.local` (`YOUTUBE_API_KEY`, `DOCS_GITHUB_TOKEN`).
7. **(volitelné) `unattended-upgrades`** pro automatické security patche.
8. **(volitelné) snapshot/backup** přes Hetzner Cloud — pravidelné snapshoty disku stojí ~20 % ceny serveru.

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

### Doctrine `old` EM vyžaduje `php-mysql`

I když na prod nepoužíváme MySQL, `config/packages/doctrine.yaml` registruje `old` connection na boot. Bez `php8.3-mysql` extension by se Doctrine ani nezavedlo. Setup ho instaluje, `OLD_DATABASE_URL` v `.env.local` ukazuje do prázdna (`mysql://nobody:nobody@127.0.0.1:3306/none`), connection se nikdy neotevře.

### Composer post-install scripts spouštějí cache:clear v prod

Po `composer install --no-dev` Symfony auto-spustí `cache:clear`, `assets:install`, `importmap:install`. To je důvod proč `var/cache` musí být writable PHP procesem ještě před prvním `bin/console cache:clear`.

## Reference

- [Hetzner Cloud Docs](https://docs.hetzner.com/cloud/)
- [Caddy Docs](https://caddyserver.com/docs/)
- [Symfony Deployment best practices](https://symfony.com/doc/current/deployment.html)
