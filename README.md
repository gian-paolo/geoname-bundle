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
The bundle only syncs countries and languages that are **enabled** in your database. Populate these tables first:

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

## 🔍 Searching for Data

The bundle provides a high-performance `GeonameSearchService` to find cities and places with their administrative context (regions, provinces).

```php
use Pallari\GeonameBundle\Service\GeonameSearchService;

public function searchExample(GeonameSearchService $searchService)
{
    $results = $searchService->search('Torino', [
        'countries' => ['IT'],
        'with_admin_names' => true, // Joins region/province names
        'limit' => 5
    ]);
    
    // Results include: geonameid, name, latitude, longitude, 
    // population, admin1_name, admin1_id, admin2_name, admin2_id,
    // admin3_name, admin3_id, admin4_name, admin4_id
}
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

---

## ⚖️ Disclaimer

This bundle is an independent, open-source project and is **not affiliated, associated, authorized, endorsed by, or in any way officially connected** with [GeoNames.org](https://www.geonames.org/). 

The data provided by the synchronization commands is owned and maintained by GeoNames. It is typically released under the [Creative Commons Attribution 4.0 License](https://creativecommons.org/licenses/by/4.0/). Please respect their [Terms and Conditions](https://www.geonames.org/about.html) and consider supporting them if you find their data useful.

---

## 📄 License
This project is released under the MIT License. Developed with ❤️ by **Gian-Paolo Pallari**.
