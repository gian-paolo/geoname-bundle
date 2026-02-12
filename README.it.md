# GeonameBundle

[![Symfony](https://img.shields.io/badge/Symfony-%5E6.4%20%7C%20%5E7.0%20%7C%20%5E8.0-000000?style=flat-square&logo=symfony)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4?style=flat-square&logo=php)](https://php.net)
[![Version](https://img.shields.io/badge/version-0.2.0-orange?style=flat-square)](https://github.com/gian-paolo/geoname-bundle)

**GeonameBundle** è una soluzione sperimentale ad alte prestazioni per integrare e mantenere aggiornati i dati geografici di [GeoNames](https://www.geonames.org/) nelle tue applicazioni Symfony.
A differenza di altri bundle, questo è progettato per gestire **milioni di record** con un consumo di memoria minimo e un sistema di **aggiornamento quotidiano incrementale** (sync) che evita di dover ricaricare l'intero database ogni volta.

---

## 🚀 Caratteristiche Principali

- **Sincronizzazione Intelligente**: Scarica e applica solo le modifiche e le eliminazioni giornaliere di GeoNames.
- **Performance SQL Bulk**: Utilizza query SQL ottimizzate (`bulkInsert` e `bulkUpdate`) riducendo il numero di query del 99%.
- **Strategia Ibrida**: Divide automaticamente i dati tra "nuovi" e "esistenti" per una gestione trasparente e sicura.
- **Dettaglio Configurabile**: Puoi scegliere di avere il dettaglio completo per certi paesi (es. Italia) e solo le città principali per il resto del mondo.
- **Multilingua**: Supporto opzionale per i nomi alternativi (traduzioni).
- **Universale**: Compatibile con **MySQL, MariaDB e PostgreSQL**.

---

## 🏗 Compatibilità Database

Questo bundle è progettato per essere agnostico rispetto al database. Evita sintassi SQL proprietarie (come `UPSERT` o `ON CONFLICT`) e utilizza SQL-92 standard tramite Doctrine DBAL.

Sia che tu usi **MySQL**, **MariaDB** o **PostgreSQL**, otterrai le stesse prestazioni elevate e la stessa integrità dei dati.

---

## 🛠 Installazione

```bash
composer require pallari/geoname-bundle
```

---

## 📝 Configurazione (Per Neofiti)

GeoNames fornisce moltissimi dati. Con questo bundle decidi tu cosa tenere nel database. Crea il file `config/packages/pallari_geoname.yaml`:

```yaml
pallari_geoname:
    entities:
        geoname: 'App\Entity\GeoName'      # La tua entità per le città
        country: 'App\Entity\GeoCountry'    # Stato dei paesi abilitati
        language: 'App\Entity\GeoLanguage'  # Lingue abilitate per la ricerca
        import: 'App\Entity\DataImport'     # Log delle importazioni
        admin1: 'App\Entity\GeoAdmin1'      # Nomi Regioni
        admin2: 'App\Entity\GeoAdmin2'      # Nomi Province
        admin3: 'App\Entity\GeoAdmin3'      # Nomi Comuni (ADM3)
        admin4: 'App\Entity\GeoAdmin4'      # Nomi Località (ADM4)
    
    # Abilita la sincronizzazione dei nomi alternativi
    alternate_names:
        enabled: true

    # Performance: Abilita la ricerca Full-Text
    search:
        use_fulltext: true

    # Opzionale: Abilita importazione codici Admin5 (file globale separato)
    admin5:
        enabled: true

    # Opzionale: Personalizza i nomi delle tabelle o aggiungi un prefisso
    table_prefix: 'geo_'
    tables:
        geoname: 'my_geoname' # Sovrascrive il nome della tabella geoname (il prefisso verrà aggiunto se impostato)
```

### Setup delle Entità (Azione Richiesta)
Per mantenere il bundle leggero e flessibile, **devi creare le tue classi entità** nella cartella `src/Entity/` del tuo progetto. Queste classi estenderanno le classi astratte del bundle.

**1. Crea le classi:**
Crea questi file in `src/Entity/`. Ogni classe deve estendere la corrispondente classe astratta del bundle:

| Tuo File Entità | Classe da Estendere |
| :--- | :--- |
| `GeoName.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoName` |
| `GeoCountry.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoCountry` |
| `GeoLanguage.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoLanguage` |
| `DataImport.php` | `\Pallari\GeonameBundle\Entity\AbstractDataImport` |
| `GeoAdmin1.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoAdmin1` |
| `GeoAdmin2.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoAdmin2` |
| `GeoAdmin3.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoAdmin3` |
| `GeoAdmin4.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoAdmin4` |
| `GeoAlternateName.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoAlternateName` |
| `GeoHierarchy.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoHierarchy` |

**Esempio per la città (`src/Entity/GeoName.php`):**
```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pallari\GeonameBundle\Entity\AbstractGeoName;

#[ORM\Entity]
class GeoName extends AbstractGeoName {
    // Qui puoi aggiungere i tuoi campi personalizzati o relazioni!
}
```

**2. Aggiorna il Database:**
```bash
php bin/console doctrine:schema:update --force
```

**3. Popola le impostazioni iniziali:**
Il bundle elabora solo i paesi e le lingue che sono **abilitati** nel database. Devi prima popolare queste tabelle:

```sql
-- Abilita Italia e USA
INSERT INTO geocountry (code, name, is_enabled) VALUES ('IT', 'Italia', 1), ('US', 'Stati Uniti', 1);

-- Abilita Italiano e Inglese per la ricerca/traduzioni
INSERT INTO geolanguage (code, name, is_enabled) VALUES ('it', 'Italiano', 1), ('en', 'Inglese', 1);
```

---

## 📖 Come si usa

### 1. Importa i nomi di Regioni e Province
I codici amministrativi (es. "IT.09") sono inutili se non sappiamo che significano "Piemonte". Esegui questo comando una volta:
```bash
php bin/console pallari:geoname:import-admin-codes
```

### 2. Sincronizza i dati (Import & Daily Sync)
Questo è il comando che userai più spesso. Il bundle elabora solo i paesi che sono **abilitati** nel database (imposta `is_enabled = 1` nella tua tabella `geocountry`).

La prima volta scaricherà i dati completi, poi scaricherà solo gli aggiornamenti del giorno precedente:
```bash
php bin/console pallari:geoname:sync
```
*Suggerimento: metti questo comando nel tuo **Crontab** per farlo girare ogni mattina alle 06:00.*

---

## 🔍 Ricerca dei Dati

Il bundle offre un servizio `GeonameSearchService` ad alte prestazioni per trovare città e località con il loro contesto amministrativo (regioni, province).

```php
use Gpp\GeonameBundle\Service\GeonameSearchService;

public function miaRicerca(GeonameSearchService $searchService)
{
    $results = $searchService->search('Torino', [
        'countries' => ['IT'],
        'with_admin_names' => true, // Include i nomi di regione e provincia
        'limit' => 5
    ]);
    
    // I risultati includono: geonameid, name, latitude, longitude, 
    // population, admin1_name, admin1_id, admin2_name, admin2_id,
    // admin3_name, admin3_id, admin4_name, admin4_id
}
```

---

## ⚙️ Dettagli Tecnici (Per Esperti)

### Strategia di Aggiornamento "Hybrid"
Invece di usare un rischioso `UPSERT` (come `ON DUPLICATE KEY UPDATE`), il bundle segue un processo in 3 fasi per ogni blocco di 1000 righe:
1. **Check**: Interroga il DB per identificare quali ID esistono già.
2. **Split**: Divide il blocco in due liste: `$toInsert` e `$toUpdate`.
3. **Execute**: Esegue una singola `INSERT` multi-riga e una `UPDATE` multi-riga (usando la sintassi `CASE WHEN`).
**Risultato**: Solo 3 query totali per ogni 1000 record, mantenendo integrità e trasparenza totale.

### Indici Consigliati
Per ricerche istantanee, aggiungi questi indici alle tue entità concrete:

```php
// Indice standard per ricerche per nome e paese
#[ORM\Index(columns: ['name', 'country_code'], name: 'idx_search')]

// Indice FULLTEXT per ricerche "fuzzy" (solo MySQL/MariaDB)
// Include alternate_names per trovare città con nomi comuni diversi
#[ORM\Index(columns: ['name', 'asciiname', 'alternatenames'], name: 'idx_fulltext', flags: ['fulltext'])]
```

### Ricerca Full-Text
Se `search.use_fulltext` è abilitato, il bundle usa automaticamente:
- `MATCH AGAINST` per MySQL/MariaDB.
- `to_tsvector` per PostgreSQL.
- **Nota**: Per SQLite (comune nei test), il bundle userà automaticamente il fallback alla ricerca `LIKE` standard per garantire la compatibilità.

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

## ⚖️ Disclaimer (Dichiarazione di non responsabilità)

Questo bundle è un progetto open-source indipendente e **non è affiliato, associato, autorizzato, approvato o in alcun modo ufficialmente collegato** con [GeoNames.org](https://www.geonames.org/).

I dati forniti dai comandi di sincronizzazione appartengono e sono gestiti da GeoNames. Si prega di rispettare i loro [Termini e Condizioni](https://www.geonames.org/export/credits.html) e di considerare l'idea di sostenerli se trovate i loro dati utili.

---

## 📄 Licenza
Questo progetto è rilasciato sotto licenza MIT. Sviluppato con ❤️ da **Gian-Paolo Pallari**.
