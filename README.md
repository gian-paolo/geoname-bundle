# GppGeonameBundle

[![Symfony](https://img.shields.io/badge/Symfony-%5E6.4%20%7C%20%5E7.0-000000?style=flat-square&logo=symfony)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4?style=flat-square&logo=php)](https://php.net)
[![Latest Stable Version](https://img.shields.io/packagist/v/pallari/geoname-bundle.svg?style=flat-square)](https://packagist.org/packages/pallari/geoname-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/pallari/geoname-bundle.svg?style=flat-square)](https://packagist.org/packages/pallari/geoname-bundle)
[![Tests](https://github.com/gian-paolo/geoname-bundle/actions/workflows/tests.yml/badge.svg)](https://github.com/gian-paolo/geoname-bundle/actions)

**GppGeonameBundle** è una soluzione sperimentale ad alte prestazioni per integrare e mantenere aggiornati i dati geografici di [GeoNames](https://www.geonames.org/) nelle tue applicazioni Symfony.
A differenza di altri bundle, questo è progettato per gestire **milioni di record** con un consumo di memoria minimo e un sistema di **aggiornamento quotidiano incrementale** (sync) che evita di dover ricaricare l'intero database ogni volta.

---

## 🚀 Caratteristiche Principali

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
composer require gpp/geoname-bundle
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
use Gpp\GeonameBundle\Entity\AbstractGeoName;

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

## 📖 Come si usa

### 1. Importa i nomi di Regioni e Province
I codici amministrativi (es. "IT.09") sono inutili se non sappiamo che significano "Piemonte". Esegui questo comando una volta:
```bash
php bin/console pallari:geoname:import-admin-codes
```

### 2. Synchronize Data (Import & Daily Sync)
This is the command you'll use most often. It only processes countries that are **enabled** in your database (set `is_enabled = 1` in your `geocountry` table).

The first time it will download full data, then it will only download updates from the previous day:
```bash
php bin/console pallari:geoname:sync
```
*Suggerimento: metti questo comando nel tuo **Crontab** per farlo girare ogni mattina alle 06:00.*

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

### Strategia di Aggiornamento "Hybrid"
Invece di usare un rischioso `UPSERT` (come `ON DUPLICATE KEY UPDATE`), il bundle segue un processo in 3 fasi per ogni blocco di 1000 righe:
1. **Check**: Interroga il DB per identificare quali ID esistono già.
2. **Split**: Divide il blocco in due liste: `$toInsert` e `$toUpdate`.
3. **Execute**: Esegue una singola `INSERT` multi-riga e una `UPDATE` multi-riga (usando la sintassi `CASE WHEN`).
**Risultato**: Solo 3 query totali per ogni 1000 record, mantenendo integrità e trasparenza totale.

### Full-Text Search
If `search.use_fulltext` is enabled, the bundle automatically uses:
- `MATCH AGAINST` for MySQL/MariaDB.
- `to_tsvector` for PostgreSQL.
- **Note**: For SQLite (common in testing), the bundle will automatically fallback to standard `LIKE` searches to ensure compatibility.

---

## ⚖️ Disclaimer

This bundle is an independent, open-source project and is **not affiliated, associated, authorized, endorsed by, or in any way officially connected** with [GeoNames.org](https://www.geonames.org/). 

The data provided by the synchronization commands is owned and maintained by GeoNames. It is typically released under the [Creative Commons Attribution 4.0 License](https://creativecommons.org/licenses/by/4.0/). Please respect their [Terms and Conditions](https://www.geonames.org/about.html) and consider supporting them if you find their data useful.

### Esempio Query SQL
Come unire le tabelle per ottenere un indirizzo completo:
```sql
SELECT c.name as citta, a1.name as regione, a2.name as provincia
FROM geo_name c
LEFT JOIN geo_admin1 a1 ON a1.code = CONCAT(c.country_code, '.', c.admin1_code)
LEFT JOIN geo_admin2 a2 ON a2.code = CONCAT(c.country_code, '.', c.admin1_code, '.', c.admin2_code)
WHERE c.name LIKE 'Torino%' AND c.is_deleted = 0;
```

---

## 📄 Licenza
Questo progetto è rilasciato sotto licenza MIT. Sviluppato con ❤️ da **Gian-Paolo Pallari**.
