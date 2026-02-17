# Installation & Setup

Get GeonameBundle up and running in your Symfony project.

## 1. Install the Package

Add the bundle to your project via Composer:

```bash
composer require pallari/geoname-bundle
```

## 2. The Interactive Installer (Recommended)

The easiest way to set up the bundle is using the interactive command:

```bash
php bin/console pallari:geoname:install
```

This command will guide you through:
- Creating the configuration file.
- Generating the necessary entity classes in your project.
- Updating the database schema.
- Enabling initial countries and languages.
- Optimizing the database for search.

## 3. Manual Configuration

If you prefer manual setup, create `config/packages/pallari_geoname.yaml`:

```yaml
pallari_geoname:
    entities:
        geoname: 'App\Entity\GeoName'
        country: 'App\Entity\GeoCountry'
        # ... other entities (see reference)
    
    search:
        use_fulltext: true
        max_results: 100

    alternate_names:
        enabled: true

    table_prefix: 'geoname_'
```

## 4. Entity Classes

To keep the bundle flexible, you must create concrete entities that extend the bundle's abstract classes.

**Example: `src/Entity/GeoName.php`**
```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pallari\GeonameBundle\Entity\AbstractGeoName;

#[ORM\Entity]
class GeoName extends AbstractGeoName {}
```

Repeat this for all 11 required entities (Country, Language, Import, Admin1-5, AlternateName, Hierarchy).

## 5. Performance Optimization

If you enabled Full-Text search, the installer adds special indexes to your database. If you skipped this step, you can run it later by relaunching the installer.

**Database Support**:
- **MySQL/MariaDB**: Uses `FULLTEXT` indexes.
- **PostgreSQL**: Uses `GIN` indexes with `to_tsvector`.
- **SQLite**: Automatic fallback to `LIKE` (no specialized indexes needed).
