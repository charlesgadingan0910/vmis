# VMIS Backup and Restore Runbook (P1-08)

## What is backed up
- **Database:** every table except sessions, cache and queue data (throw-away).
- **Documents:** everything on the `public` disk — OR/CR/insurance files, receipts, driver photos, maintenance and repair documents.
- One encrypted `.zip` per night (02:00) in the **backup disk**, kept for `BACKUP_KEEP_DAYS` (default 14); the newest is never deleted.
- Every file inside the zip is AES-256 encrypted with `BACKUP_PASSPHRASE`. The zip also holds a manifest with row counts and SHA-256 checksums so a restore can prove nothing is missing or damaged.

## One-time setup (Laravel Cloud)
1. Create a **separate object-storage bucket** just for backups (not the documents bucket). A Laravel Cloud app's own disk is wiped on every deploy, so backups must not live there.
2. Set these environment variables, then redeploy:
   - `BACKUP_PASSPHRASE` — a long random passphrase (20+ characters).
   - `BACKUP_DISK_DRIVER=s3`
   - `BACKUP_AWS_ACCESS_KEY_ID`, `BACKUP_AWS_SECRET_ACCESS_KEY`, `BACKUP_AWS_DEFAULT_REGION`, `BACKUP_AWS_BUCKET`, `BACKUP_AWS_ENDPOINT` (from the bucket's credentials).
   - Optional: `BACKUP_KEEP_DAYS`.
3. **Store a copy of `BACKUP_PASSPHRASE` somewhere that is NOT the server** (password manager, sealed envelope with the sponsor). Without it no backup can be opened.
4. Confirm the scheduler is running on Laravel Cloud (the nightly job and the alert sync both need it).
5. In the Laravel Cloud database settings, also check the platform's own automatic backups / point-in-time restore and note the retention period here: ________

## Check on it
```
php artisan vmis:backup-status
```
Shows the last successful backup and the stored files. Run it weekly, or after any deploy that touches storage.

## Make a backup by hand
```
php artisan vmis:backup
```
(`--no-files` backs up the database only.) Always make one before a risky migration.

## Restore rehearsal (safe — touches nothing live)
```
php artisan vmis:backup-restore            # newest backup
php artisan vmis:backup-restore vmis/vmis-backup-20261116-020000.zip
```
It unpacks the backup into a throw-away database, checks every table and document against the manifest, and prints the time taken. "Rehearsal passed" means the backup is good.

**Record each rehearsal:**

| Date | Backup file | Tables / rows / documents | Time taken | Result | Done by |
|------|-------------|---------------------------|------------|--------|---------|
|      |             |                           |            |        |         |

## Real restore (disaster only)
1. Stop users (maintenance mode: `php artisan down`).
2. Make sure the target database exists and has its tables (`php artisan migrate --force` on a fresh database).
3. Run `php artisan vmis:backup-restore <file> --live` and confirm. It **replaces** all data and documents with the backup.
4. `php artisan up`, then check: log in, open Vehicle Inventory, open a vehicle's OR/CR, run `php artisan vmis:alerts-status`.
5. Users who logged in on phones after the backup was taken may need to log in again.

## Privacy
Backups hold personnel and vehicle records and password hashes. Keep the backup bucket private, give the credentials to as few people as possible, and rotate `BACKUP_PASSPHRASE` if anyone with access leaves (new backups use the new one; keep the old one for old backups until they expire).
