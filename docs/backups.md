# Zálohy

## Legacy MySQL — aktivní

Legacy aplikace běží na produkčním VPS v Docker Compose projektu
`slack-cz-legacy`. Databáze `gslackczdb` je v kontejneru
`slack-cz-legacy-db-1` (MySQL 5.7) a Docker volume
`slack-cz-legacy_db_data`. Samotný volume je na stejném disku jako VPS, proto
není záloha.

Od 2026-07-21 běží denní offsite záloha do samostatného bezplatného Google
Drive účtu:

1. `mysqldump --single-transaction` vytvoří konzistentní logický dump,
2. dump se komprimuje přes `gzip`,
3. `age` ho zašifruje veřejným recovery klíčem,
4. `rclone` nahraje pouze `.sql.gz.age` soubor do
   `slack-cz-backups/legacy-db`,
5. dumpy starší než 90 dní se smažou.

Google OAuth token je jen na serveru v `/etc/slack-cz-backup/rclone.conf`
(`root:root`, mode `600`). Server zná pouze veřejný age klíč. Privátní recovery
klíč je mimo server v `~/.config/slack-cz-backup/legacy-db.agekey` (mode `600`).
Recovery kopie je uložená v rootu backup Google Drivu, mimo adresář
`slack-cz-backups`. Bez jedné z těchto kopií nelze zálohu obnovit.

### Provoz

```bash
# Stav a příští běh
systemctl list-timers slack-cz-legacy-db-backup.timer

# Poslední výsledek a log
sudo systemctl status slack-cz-legacy-db-backup.service
sudo journalctl -u slack-cz-legacy-db-backup.service -n 50 --no-pager

# Ruční spuštění
sudo systemctl start slack-cz-legacy-db-backup.service

# Vzdálené soubory
sudo rclone --config /etc/slack-cz-backup/rclone.conf \
  lsl gdrive:slack-cz-backups/legacy-db
```

Implementace v repu:

- `scripts/backup-legacy-db.sh`
- `infra/systemd/slack-cz-legacy-db-backup.service`
- `infra/systemd/slack-cz-legacy-db-backup.timer`

Timer běží denně kolem 03:17 Europe/Prague s náhodným zpožděním do 20 minut a
`Persistent=true` (zmeškaný běh po výpadku se doplní po startu).

### Obnova

Nikdy neobnovuj první pokus přímo přes živou databázi. Nejdřív stáhni vybraný
`.sql.gz.age` soubor a otestuj ho v čistém MySQL 5.7.

```bash
age --decrypt \
  --identity ~/.config/slack-cz-backup/legacy-db.agekey \
  --output gslackczdb.sql.gz \
  gslackczdb-YYYY-MM-DDTHHMMSSZ.sql.gz.age

gzip -t gslackczdb.sql.gz
gunzip -c gslackczdb.sql.gz | mysql -u root -p target_database
```

Po prvním nasazení byla provedena úplná zkouška obnovy do jednorázového MySQL
5.7 kontejneru v RAM: obnovilo se 46 tabulek a 254 řádků `highline`.

### Co tato záloha neřeší

- soubory legacy aplikace a fotografie,
- PostgreSQL a uploady nové Symfony aplikace,
- ztrátu Google účtu nebo jediného privátního recovery klíče,
- externí alert při neúspěšném timeru.

Záloha nové aplikace se doplní později stejným offsite principem (PostgreSQL
dump + `public/uploads`).
