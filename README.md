# GeonameBundle

[![Symfony](https://img.shields.io/badge/Symfony-%5E6.4%20%7C%20%5E7.0%20%7C%20%5E8.0-000000?style=flat-square&logo=symfony)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4?style=flat-square&logo=php)](https://php.net)
[![Version](https://img.shields.io/badge/version-0.1.0--beta-orange?style=flat-square)](https://github.com/gian-paolo/geoname-bundle)

🇮🇹 [Leggi la documentazione in Italiano](./README.it.md)

**GeonameBundle** is a high-performance solution for integrating and maintaining up-to-date geographical data from [GeoNames](https://www.geonames.org/) in your Symfony applications.
Unlike other bundles, it is designed to handle **millions of records** with minimal memory consumption and an **incremental daily sync** system that avoids reloading the entire database every time.

---

## 🚀 Key Features

- **Smart Synchronization**: Downloads and applies only daily changes and deletions from GeoNames.
- **Bulk SQL Performance**: Uses optimized SQL queries (`bulkInsert` and `bulkUpdate`), reducing the number of queries by 99%.
- **Hybrid Strategy**: Automatically splits data between "new" and "existing" for transparent and safe management.
- **Configurable Detail**: Choose full detail for specific countries (e.g., Italy) and only major cities for the rest of the world.
- **Multilingual**: Optional support for alternate names (translations).
- **Universal**: Compatible with **MySQL, MariaDB, and PostgreSQL**.

---

## 🛠 Installation

```bash
composer require pallari/geoname-bundle
```

---

## 📝 Configuration

GeoNames provides a vast amount of data. With this bundle, you decide what to keep in your database. Create the `config/packages/gpp_geoname.yaml` file:

```yaml
gpp_geoname:
    entities:
        geoname: 'App\Entity\GeoName'      # Your entity for cities
        country: 'App\Entity\GeoCountry'    # Enabled countries status
        import: 'App\Entity\DataImport'     # Import logs
        admin1: 'App\Entity\GeoAdmin1'      # Region names
        admin2: 'App\Entity\GeoAdmin2'      # Province names
    
    # Optional: Import name translations
    alternate_names:
        enabled: true
        languages: ['it', 'en', 'fr']
```

### Entity Setup
To keep the bundle lightweight, you need to create your own entities that extend the bundle's abstract classes. Example for a city:

```php
// src/Entity/GeoName.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pallari\GeonameBundle\Entity\AbstractGeoName;

#[ORM\Entity]
class GeoName extends AbstractGeoName {
    // Add your own fields here (e.g., $weather, $images, etc.)
}
```

---

## 📖 Usage

### 1. Import Administrative Codes
Administrative codes (e.g., "IT.09") need their labels (e.g., "Piemonte"). Run this command once:
```bash
php bin/console gpp:geoname:import-admin-codes
```

### 2. Synchronize Data (Import & Daily Sync)
This is the command you'll use most often. The first time it will download full data, then it will only download updates from the previous day:
```bash
php bin/console gpp:geoname:sync
```
*Tip: Add this command to your **Crontab** to run every morning at 06:00 UTC.*

---

## ⚙️ Technical Details

### "Hybrid" Update Strategy
Instead of using a risky `UPSERT` (like `ON DUPLICATE KEY UPDATE`), the bundle follows a 3-phase process for every block of 1000 rows:
1. **Check**: Queries the DB to identify which IDs already exist.
2. **Split**: Divides the block into two lists: `$toInsert` and `$toUpdate`.
3. **Execute**: Performs a single multi-row `INSERT` and a multi-row `UPDATE` (using `CASE WHEN` syntax).
**Result**: Only 3 total queries per 1000 records, maintaining full integrity and transparency.

### Recommended Indexes
For instant searches, add these indexes to your concrete entities:

```php
// Standard index for name and country searches
#[ORM\Index(columns: ['name', 'country_code'], name: 'idx_search')]

// FULLTEXT index for fuzzy searches (MySQL/MariaDB only)
#[ORM\Index(columns: ['name', 'asciiname'], name: 'idx_fulltext', flags: ['fulltext'])]
```

---

## 📄 License
This project is released under the MIT License. Developed with ❤️ by **Gian-Paolo Pallari**.
