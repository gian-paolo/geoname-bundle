# Data Synchronization

Maintaining up-to-date data is essential. GeonameBundle provides a robust, incremental synchronization system.

## 1. Initial Setup: Administrative Codes

Administrative codes (like `IT.09`) are linked to their display names (like `Piemonte`) in separate tables. You only need to run this once after installation:

```bash
php bin/console pallari:geoname:import-admin-codes
```

This command downloads the official labels from GeoNames and populates the `geo_admin1` to `geo_admin5` tables.

## 2. Enabling Countries

The bundle only processes data for countries that are **enabled** in your database. You can manage this via the `geo_country` table:

```sql
-- Enable Italy
UPDATE geoname_country SET is_enabled = 1 WHERE code = 'IT';
```

## 3. Daily Synchronization

The command `pallari:geoname:sync` is the core of the synchronization system.

```bash
php bin/console pallari:geoname:sync
```

### How it works:
- **First Run**: Performs a FULL download of the `.zip` file for each enabled country.
- **Subsequent Runs**: Automatically detects the last import date. If data is fresh (last 24h), it only downloads the **daily updates** (modifications and deletions) from GeoNames.
- **Performance**: Uses bulk SQL operations to handle thousands of records in seconds.

### Automation (Crontab)
To keep your data perfectly in sync, it is highly recommended to schedule this command every day. GeoNames usually publishes updates around 04:00 UTC.

```cron
# Run geoname sync daily at 06:00 UTC
0 6 * * * php /path/to/your/project/bin/console pallari:geoname:sync --env=prod
```

## 📊 Monitoring Imports

Every synchronization session is logged in the `geo_import` table. You can check:
- `status`: `running`, `completed`, or `failed`.
- `records_processed`: Total number of items updated or inserted.
- `error_message`: In case of failure, contains the stack trace or SQL error.

---

## 🗺️ Administrative Hierarchies

GeoNames organizes data into 5 administrative levels (Admin1 to Admin5). The meaning of these levels varies by country. Here are some examples of countries where granular levels are used:

| Country | Admin1 (ADM1) | Admin2 (ADM2) | Admin3 (ADM3) | Admin4 (ADM4) | Admin5 (ADM5) |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Italy (IT)** | Regions | Provinces | Municipalities | - | Districts (big cities) |
| **Nepal (NP)** | Provinces | Districts | Municipalities | Wards | Sub-Wards |
| **Dom. Rep. (DO)** | Regions | Provinces | Municipalities | Munic. Districts | Neighborhoods |
| **France (FR)** | Regions | Departments | Arrondissements | Communes | Cantons |
| **Germany (DE)** | States (Länder) | Adm. Districts | Districts (Kreise) | Municipalities | - |
| **USA (US)** | States | Counties | - | - | - |
| **Uganda (UG)** | Regions | Districts | Counties | Sub-counties | Parishes |
