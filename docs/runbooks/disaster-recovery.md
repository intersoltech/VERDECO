# Verdeco Cost Control — Disaster Recovery & Operational Runbook (Native / No-Docker)

This operational runbook provides step-by-step procedures for backup, point-in-time restore, disaster recovery, queue worker supervision, and cache rebuilding for the Verdeco Cost Control application operating directly on native host environments (**no Docker or containerization**).

---

## 1. System Architecture & Native Prerequisites

- **Application Runtime**: PHP 8.3 CLI + PHP-FPM with extensions: `bcmath`, `pdo_mysql`, `redis`, `gd`, `zip`, `intl`, `mbstring`.
- **Database Server**: MySQL 8.0+ running as a native operating system service.
- **Cache & Message Broker**: Redis 7+ running as a native Windows service or standalone daemon on `127.0.0.1:6379`.
- **Web Server**: Nginx or Microsoft IIS / Apache reverse-proxying PHP-FPM.

---

## 2. Automated Native Database Backups

### 2.1 Daily Consistent Backup Script (MySQL)
Run nightly via Windows Task Scheduler or cron to produce an atomic, non-locking database snapshot:

#### Windows PowerShell Script (`scripts/backup-database.ps1`)
```powershell
$BackupDir = "D:\backups\verdeco"
$Date = Get-Date -Format "yyyyMMdd_HHmmss"
$BackupFile = "$BackupDir\verdeco_db_$Date.sql"
$ZipFile = "$BackupDir\verdeco_db_$Date.zip"

if (!(Test-Path $BackupDir)) {
    New-Item -ItemType Directory -Path $BackupDir | Out-Null
}

# Dump with single transaction to avoid locking production batches
& "mysqldump" --defaults-file="C:\ProgramData\MySQL\MySQL Server 8.0\my.cnf" `
    --single-transaction `
    --quick `
    --routines `
    --triggers `
    --hex-blob `
    --databases verdeco > $BackupFile

# Compress
Compress-Archive -Path $BackupFile -DestinationPath $ZipFile
Remove-Item $BackupFile

# Rotate backups older than 30 days
Get-ChildItem -Path $BackupDir -Filter "verdeco_db_*.zip" | `
    Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-30) } | `
    Remove-Item
```

---

## 3. Disaster Recovery & Database Restoration

In the event of a host disk failure, data corruption, or catastrophic incident:

### Step 3.1: Prepare Clean Target Database
```sql
-- Connect to MySQL via native client
mysql -u root -p

DROP DATABASE IF EXISTS verdeco;
CREATE DATABASE verdeco CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### Step 3.2: Restore from Latest Snapshot
```powershell
# Unzip backup archive
Expand-Archive -Path D:\backups\verdeco\verdeco_db_latest.zip -DestinationPath D:\backups\restore\

# Restore database
mysql -u root -p verdeco < D:\backups\restore\verdeco_db_latest.sql
```

### Step 3.3: Run Database Migrations
Verify schema migrations are up to date:
```powershell
cd D:\VERDECO
php artisan migrate --force
```

---

## 4. Cache & Rollup Recovery (Redis)

If Redis cache is flushed, corrupted, or restarted:

### Step 4.1: Flush Stale Cache
```powershell
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Step 4.2: Idempotent Rebuild of All Rollup Facts
The Verdeco architecture separates raw operational source tables (`production_batches`, `cost_incidents`, `downtime_events`) from analytical read-models (`fact_daily_line_costs`, `fact_daily_plant_kpis`, `fact_monthly_variances`).

Rebuild all rollup summaries from raw records:
```powershell
# Rebuild all historical plants and years cleanly
php artisan rollups:rebuild --fresh
```
This command safely truncates the fact tables, re-evaluates all historical batches, and rebuilds daily and monthly variance cubes without any downtime.

---

## 5. Queue Workers & Background Scheduler Setup

### 5.1 Background Queue Worker (Rollups & Alerts)
To process the `'rollups'` and `'default'` queues continuously on Windows without Docker:

Use **NSSM (Non-Sucking Service Manager)** to install the queue worker as a self-healing Windows Service:
```powershell
# Download NSSM and run:
nssm install VerdecoQueueWorker "C:\php\php.exe" "D:\VERDECO\artisan queue:work --queue=rollups,default --sleep=3 --tries=3 --backoff=5,15,30 --max-time=3600"
nssm set VerdecoQueueWorker AppDirectory "D:\VERDECO"
nssm set VerdecoQueueWorker Start SERVICE_AUTO_START
nssm start VerdecoQueueWorker
```

### 5.2 Background Scheduler (Cron)
Configure a recurring Windows Task Scheduler task running every minute:
- **Program**: `C:\php\php.exe`
- **Arguments**: `D:\VERDECO\artisan schedule:run`
- **Trigger**: Daily, repeating every 1 minute indefinitely.

This executes the scheduled jobs defined in `routes/console.php`:
- Hourly: `alerts:evaluate --sync`
- Daily at 06:00: `alerts:digest`
- Weekly on Mondays at 07:00: Group alert digest

---

## 6. System Health Verification

After recovery or maintenance, verify that all systems are operational:
```powershell
# 1. Check HTTP health endpoint
curl -i http://localhost/up

# 2. Check operations JSON summary
curl -i -H "Authorization: Bearer <ADMIN_TOKEN>" http://localhost/api/v1/operations/summary

# 3. Run full automated test suite to verify data integrity
php artisan test
```
All checks should return HTTP `200 OK` and green test passes.

