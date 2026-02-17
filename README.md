# GeonameBundle

[![Symfony](https://img.shields.io/badge/Symfony-%5E6.4%20%7C%20%5E7.0%20%7C%20%5E8.0-000000?style=flat-square&logo=symfony)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4?style=flat-square&logo=php)](https://php.net)
[![Latest Stable Version](https://img.shields.io/packagist/v/pallari/geoname-bundle.svg?style=flat-square)](https://packagist.org/packages/pallari/geoname-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/pallari/geoname-bundle.svg?style=flat-square)](https://packagist.org/packages/pallari/geoname-bundle)
[![Tests](https://github.com/gian-paolo/geoname-bundle/actions/workflows/tests.yml/badge.svg)](https://github.com/gian-paolo/geoname-bundle/actions)

🇮🇹 [Leggi la documentazione in Italiano](./README.it.md)

**GeonameBundle** is a high-performance solution for integrating and maintaining up-to-date geographical data from [GeoNames](https://www.geonames.org/) in your Symfony applications.
Unlike other bundles, it is designed to handle **millions of records** with minimal memory consumption and an **incremental daily sync** system that avoids reloading the entire database every time.

---

## 🚀 Key Features

- **Interactive Installer**: Complete setup in seconds with `pallari:geoname:install`.
- **Smart Synchronization**: Downloads and applies only daily changes and deletions from GeoNames.
- **Bulk SQL Performance**: Uses optimized SQL queries (`bulkInsert` and `bulkUpdate`), reducing the number of queries by 99%.
- **Composite Hierarchy**: Uses composite primary keys for administrative levels (Admin1-5) for ultra-fast joins without redundancy.
- **ASCII Optimized**: Uses native ASCII character sets for codes and normalized names, reducing index size and increasing comparison speed.
- **Hybrid Strategy**: Automatically splits data between "new" and "existing" for transparent and safe management.
- **Configurable Detail**: Choose full detail for specific countries (e.g., Italy) and only major cities for the rest of the world.
- **Multilingual**: Optional support for alternate names (translations).
- **Universal**: Compatible with **MySQL, MariaDB, and PostgreSQL**.

---

## 🏗 Database Compatibility

This bundle is designed to be database-agnostic. It avoids proprietary SQL syntax (like `UPSERT` or `ON CONFLICT`) and uses standard SQL-92 via Doctrine DBAL. 

Whether you are using **MySQL**, **MariaDB**, or **PostgreSQL**, you will get the same high performance and data integrity.

---

## 🛠 Installation & Quick Setup

1. **Install the package**:
```bash
composer require pallari/geoname-bundle
```

2. **Run the Interactive Installer**:
This command will guide you through creating entities, updating the database, and running your first sync:
```bash
php bin/console pallari:geoname:install
```

---

## 📝 Configuration (Reference)

GeoNames provides a vast amount of data. With this bundle, you decide what to keep in your database. Create the `config/packages/pallari_geoname.yaml` file:

```yaml
pallari_geoname:
    entities:
        geoname: 'App\Entity\GeoName'      # Your entity for cities
        country: 'App\Entity\GeoCountry'    # Enabled countries status
        language: 'App\Entity\GeoLanguage'  # Enabled languages for search
        import: 'App\Entity\GeoImport'     # Import logs
        admin1: 'App\Entity\GeoAdmin1'      # Region names
        admin2: 'App\Entity\GeoAdmin2'      # Province names
        admin3: 'App\Entity\GeoAdmin3'      # Municipality names (e.g. Comuni)
        admin4: 'App\Entity\GeoAdmin4'      # Local district names
        admin5: 'App\Entity\GeoAdmin5'      # Local neighborhood names
    
    # Enable alternate names sync
    alternate_names:
        enabled: true

    # Performance: Enable Full-Text Search
    search:
        use_fulltext: true

    # Optional: Enable Admin5 codes import (separate global file)
    admin5:
        enabled: true

    # Optional: Customize table names or add a prefix
    table_prefix: 'geoname_'
    tables:
        geoname: 'geoname' # Table will be 'geoname_geoname'
```

### Entity Setup (Action Required)
To keep the bundle lightweight and customizable, **you must create your own entity classes** in your `src/Entity/` directory. These classes will extend the bundle's abstract classes.

**1. Create the classes:**
Create these files in `src/Entity/`. Each class must extend the corresponding abstract class from the bundle:

| Your Entity File | Class to Extend |
| :--- | :--- |
| `GeoName.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoName` |
| `GeoCountry.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoCountry` |
| `GeoLanguage.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoLanguage` |
| `GeoImport.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoImport` |
| `GeoAdmin1.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoAdmin1` |
| `GeoAdmin2.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoAdmin2` |
| `GeoAdmin3.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoAdmin3` |
| `GeoAdmin4.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoAdmin4` |
| `GeoAdmin5.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoAdmin5` |
| `GeoAlternateName.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoAlternateName` |
| `GeoHierarchy.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoHierarchy` |

**Example for a city (`src/Entity/GeoName.php`):**
```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pallari\GeonameBundle\Entity\AbstractGeoName;

#[ORM\Entity]
class GeoName extends AbstractGeoName {
    // You can add your own custom fields or relationships here!
}
```

**2. Update your Database:**
```bash
php bin/console doctrine:schema:update --force
```

**3. Populate initial settings:**
The bundle only syncs countries and languages that are **enabled** in your database. The interactive installer will ask for these, or you can use the manual "all" keyword (hidden) to enable every country globally.

```sql
-- Enable Italy and USA
INSERT INTO geocountry (code, name, is_enabled) VALUES ('IT', 'Italy', 1), ('US', 'United States', 1);

-- Enable Italian and English for search/translations
INSERT INTO geolanguage (code, name, is_enabled) VALUES ('it', 'Italian', 1), ('en', 'English', 1);
```

---

## 📖 Usage

### 1. Import Administrative Codes
Administrative codes (e.g., "IT.09") need their labels (e.g., "Piemonte"). Run this command once:
```bash
php bin/console pallari:geoname:import-admin-codes
```

### 2. Synchronize Data (Import & Daily Sync)
This is the command you'll use most often. It only processes countries that are **enabled** in your database (set `is_enabled = 1` in your `geocountry` table).

The first time it will download full data, then it will only download updates from the previous day:
```bash
php bin/console pallari:geoname:sync
```
*Tip: Add this command to your **Crontab** to run every morning at 06:00 UTC.*

---

## 🗺️ Administrative Hierarchies

GeoNames organizes data into 5 administrative levels (Admin1 to Admin5). The meaning of these levels varies by country. Here are some common examples:

| Country | Admin1 (ADM1) | Admin2 (ADM2) | Admin3 (ADM3) | Admin4 (ADM4) | Admin5 (ADM5) |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Italy (IT)** | Regions | Provinces | Municipalities | Districts (rare) | - |
| **USA (US)** | States | Counties | - | - | - |
| **France (FR)** | Regions | Departments | Arrondissements | Communes | Cantons |
| **Germany (DE)** | States (Länder) | Adm. Districts | Districts (Kreise) | Municipalities | - |
| **Spain (ES)** | Auton. Communities | Provinces | Municipalities | - | - |
| **UK (GB)** | Countries | Counties | - | - | - |

> **Note on Admin5**: Support for Admin5 is disabled by default as it is only used in a few countries (like France or Belgium) for very granular subdivisions. If you need it, make sure to enable it in your configuration and run the Admin5 import.

---

## 🔍 Querying Data

The bundle provides a high-performance `GeonameSearchService` to find cities and places with their administrative context.

### 1. General Search
Search for toponyms using a hybrid strategy (LIKE + Full-Text).

```php
use Pallari\GeonameBundle\Service\GeonameSearchService;

$results = $searchService->search('Torino', [
    'countries' => ['IT'],
    'with_admin_names' => true, // Joins labels for regions/provinces
    'limit' => 10,
    'order_by' => 'population_desc', // Default: largest cities first
    'select' => GeonameSearchService::PRESET_GEO // Or specific columns array
]);
```

### 2. Get by ID
Retrieve a single record by its `geonameid`.

```php
$city = $searchService->getById(3165524, true); 
// Returns array with names, coordinates and admin labels (if true)
```

### 3. Hierarchy Navigation
Get children or descendants of a specific administrative unit.

```php
// Example: Get all cities (ADM3) in the province of Turin (TO)
$cities = $searchService->getChildren('IT', [
    'admin1_code' => '09',
    'admin2_code' => 'TO'
], ['feature_codes' => ['ADM3']]);

// Example: Get ALL descendants of a parent by its ID
// This automatically resolves the administrative chain
$allPlaces = $searchService->getDescendantsByParentId(3170831); // Piedmont Region ID
```

---

## ⚙️ Technical Details

### "Hybrid" Update Strategy
Instead of using a risky `UPSERT` (like `ON DUPLICATE KEY UPDATE`), the bundle follows a 3-phase process for every block of 1000 rows:
1. **Check**: Queries the DB to identify which IDs already exist.
2. **Split**: Divides the block into two lists: `$toInsert` and `$toUpdate`.
3. **Execute**: Performs a single multi-row `INSERT` and a multi-row `UPDATE` (using `CASE WHEN` syntax).
**Result**: Only 3 total queries per 1000 records, maintaining full integrity and transparency.

### Full-Text Search
If `search.use_fulltext` is enabled, the bundle automatically uses:
- `MATCH AGAINST` for MySQL/MariaDB.
- `to_tsvector` for PostgreSQL.
- **Note**: For SQLite (common in testing), the bundle will automatically fallback to standard `LIKE` searches to ensure compatibility.

### Composite Join Example
How to join tables to get a full hierarchical address:
```sql
SELECT c.name as city, a1.name as region, a2.name as province
FROM geo_name c
LEFT JOIN geo_admin1 a1 ON a1.country_code = c.country_code AND a1.admin1_code = c.admin1_code
LEFT JOIN geo_admin2 a2 ON a2.country_code = c.country_code AND a2.admin1_code = c.admin1_code AND a2.admin2_code = c.admin2_code
WHERE c.name LIKE 'Torino%' AND c.is_deleted = 0;
```

---

## ⚖️ Disclaimer

This bundle is an independent, open-source project and is **not affiliated, associated, authorized, endorsed by, or in any way officially connected** with [GeoNames.org](https://www.geonames.org/). 

The data provided by the synchronization commands is owned and maintained by GeoNames. It is typically released under the [Creative Commons Attribution 4.0 License](https://creativecommons.org/licenses/by/4.0/). Please respect their [Terms and Conditions](https://www.geonames.org/about.html) and consider supporting them if you find their data useful.

---

## 📄 License
This project is released under the MIT License. Developed with ❤️ by **Gian-Paolo Pallari**.
