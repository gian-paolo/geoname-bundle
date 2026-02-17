# Installazione e Setup

Configura GeonameBundle nel tuo progetto Symfony.

## 1. Installazione del pacchetto

Aggiungi il bundle al tuo progetto tramite Composer:

```bash
composer require pallari/geoname-bundle
```

## 2. Installatore Interattivo (Consigliato)

Il modo più semplice per configurare il bundle è usare il comando interattivo:

```bash
php bin/console pallari:geoname:install
```

Questo comando ti guiderà attraverso:
- Creazione del file di configurazione.
- Generazione delle classi entità necessarie nel tuo progetto.
- Aggiornamento dello schema del database.
- Abilitazione dei primi paesi e lingue.
- Ottimizzazione del database per la ricerca.

## 3. Configurazione Manuale

Se preferisci la configurazione manuale, crea `config/packages/pallari_geoname.yaml`:

```yaml
pallari_geoname:
    entities:
        geoname: 'App\Entity\GeoName'
        country: 'App\Entity\GeoCountry'
        # ... altre entità (vedi riferimento)
    
    search:
        use_fulltext: true
        max_results: 100

    alternate_names:
        enabled: true

    table_prefix: 'geoname_'
```

## 4. Classi Entità

Per mantenere il bundle flessibile, devi creare delle entità concrete che estendono le classi astratte del bundle.

**Esempio: `src/Entity/GeoName.php`**
```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pallari\GeonameBundle\Entity\AbstractGeoName;

#[ORM\Entity]
class GeoName extends AbstractGeoName {}
```

Ripeti questa operazione per tutte le 11 entità richieste (Country, Language, Import, Admin1-5, AlternateName, Hierarchy).

## 5. Ottimizzazione delle Prestazioni

Se hai abilitato la ricerca Full-Text, l'installatore aggiunge indici speciali al tuo database. Se hai saltato questo passaggio, puoi eseguirlo in seguito rilanciando l'installatore.

> **Nota su Admin5**: Il supporto per Admin5 è disabilitato di default poiché è utilizzato solo in pochi paesi (come Nepal, Repubblica Dominicana, Germania, Francia, Italia o Uganda) per suddivisioni molto capillari. Se ne hai bisogno, abilitalo nella configurazione ed esegui l'importazione specifica per Admin5.

**Supporto Database**:
- **MySQL/MariaDB**: Utilizza indici `FULLTEXT`.
- **PostgreSQL**: Utilizza indici `GIN` con `to_tsvector`.
- **SQLite**: Fallback automatico a `LIKE` (non sono necessari indici specializzati).
