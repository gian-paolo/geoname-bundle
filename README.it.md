# GeonameBundle

[![Symfony](https://img.shields.io/badge/Symfony-%5E6.4%20%7C%20%5E7.0%20%7C%20%5E8.0-000000?style=flat-square&logo=symfony)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4?style=flat-square&logo=php)](https://php.net)
[![Latest Stable Version](https://img.shields.io/packagist/v/pallari/geoname-bundle.svg?style=flat-square)](https://packagist.org/packages/pallari/geoname-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/pallari/geoname-bundle.svg?style=flat-square)](https://packagist.org/packages/pallari/geoname-bundle)
[![Tests](https://github.com/gian-paolo/geoname-bundle/actions/workflows/tests.yml/badge.svg)](https://github.com/gian-paolo/geoname-bundle/actions)

**GeonameBundle** è una soluzione ad alte prestazioni per integrare e mantenere aggiornati i dati geografici di [GeoNames](https://www.geonames.org/) nelle tue applicazioni Symfony.
A differenza di altri bundle, questo è progettato per gestire **milioni di record** con un consumo di memoria minimo e un sistema di **aggiornamento quotidiano incrementale** (sync) che evita di dover ricaricare l'intero database ogni volta.

---

## 🚀 Caratteristiche Principali

- **Installatore Interattivo**: Configurazione completa in pochi secondi con `pallari:geoname:install`.
- **Sincronizzazione Intelligente**: Scarica e applica solo le modifiche e le eliminazioni giornaliere di GeoNames.
- **Performance SQL Bulk**: Utilizza query SQL ottimizzate (`bulkInsert` e `bulkUpdate`) riducendo il numero di query del 99%.
- **Gerarchia Composta**: Utilizza chiavi primarie composte per i livelli amministrativi (Admin1-5) per join ultra-rapidi senza ridondanza.
- **Ottimizzazione ASCII**: Utilizza set di caratteri ASCII nativi per codici e nomi normalizzati, riducendo la dimensione degli indici e aumentando la velocità di confronto.
- **Strategia Ibrida**: Divide automaticamente i dati tra "nuovi" e "esistenti" per una gestione trasparente e sicura.
- **Dettaglio Configurabile**: Puoi scegliere di avere il dettaglio completo per certi paesi (es. Italia) e solo le città principali per il resto del mondo.
- **Multilingua**: Supporto opzionale per i nomi alternativi (traduzioni).
- **Universale**: Compatibile con **MySQL, MariaDB e PostgreSQL**.

---

## 🏗 Compatibilità Database

Questo bundle è progettato per essere agnostico rispetto al database. Evita sintassi SQL proprietarie (come `UPSERT` o `ON CONFLICT`) e utilizza SQL-92 standard tramite Doctrine DBAL.

Sia che tu usi **MySQL**, **MariaDB** o **PostgreSQL**, otterrai le stesse prestazioni elevate e la stessa integrità dei dati.

---

## 🛠 Installazione e Setup Rapido

1. **Installa il pacchetto**:
```bash
composer require pallari/geoname-bundle
```

2. **Lancia l'Installatore Interattivo**:
Questo comando ti guiderà nella creazione delle entità, aggiornerà il database e lancerà la prima sincronizzazione:
```bash
php bin/console pallari:geoname:install
```

---

## 📝 Configurazione (Riferimento)

GeoNames fornisce moltissimi dati. Con questo bundle decidi tu cosa tenere nel database. Crea il file `config/packages/pallari_geoname.yaml`:

```yaml
pallari_geoname:
    entities:
        geoname: 'App\Entity\GeoName'      # La tua entità per le città
        country: 'App\Entity\GeoCountry'    # Stato dei paesi abilitati
        language: 'App\Entity\GeoLanguage'  # Lingue abilitate per la ricerca
        import: 'App\Entity\GeoImport'     # Log delle importazioni
        admin1: 'App\Entity\GeoAdmin1'      # Nomi Regioni
        admin2: 'App\Entity\GeoAdmin2'      # Nomi Province
        admin3: 'App\Entity\GeoAdmin3'      # Nomi Comuni (ADM3)
        admin4: 'App\Entity\GeoAdmin4'      # Nomi Località (ADM4)
        admin5: 'App\Entity\GeoAdmin5'      # Nomi Quartieri/Zone (ADM5)
    
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
    table_prefix: 'geoname_'
    tables:
        geoname: 'geoname' # Risulterà in 'geoname_geoname'
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
| `GeoImport.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoImport` |
| `GeoAdmin1.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoAdmin1` |
| `GeoAdmin2.php" | `\Pallari\GeonameBundle\Entity\AbstractGeoAdmin2` |
| `GeoAdmin3.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoAdmin3` |
| `GeoAdmin4.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoAdmin4` |
| `GeoAdmin5.php` | `\Pallari\GeonameBundle\Entity\AbstractGeoAdmin5` |
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
Il bundle elabora solo i paesi e le lingue che sono **abilitati** nel database. L'installatore interattivo ti guiderà nella scelta (per codici singoli, per interi continenti o usando l'opzione "all" per abilitare tutto il mondo).

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

## 🗺️ Mappatura Livelli Amministrativi

GeoNames organizza i dati in 5 livelli amministrativi (da Admin1 a Admin5). Il significato di questi livelli varia drasticamente da paese a paese. Ecco alcuni esempi comuni:

| Paese | Admin1 (ADM1) | Admin2 (ADM2) | Admin3 (ADM3) | Admin4 (ADM4) | Admin5 (ADM5) |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Italia (IT)** | Regioni | Province | Comuni | Località/Frazioni (raro) | - |
| **USA (US)** | Stati | Contee | - | - | - |
| **Francia (FR)** | Regioni | Dipartimenti | Arrondissement | Comuni | Cantoni |
| **Germania (DE)** | Stati (Länder) | Distretti Amm. | Circondari (Kreise) | Comuni | - |
| **Spagna (ES)** | Comunità Auton. | Province | Comuni | - | - |
| **Regno Unito (GB)** | Nazioni (Inghilterra...) | Contee | - | - | - |

> **Nota su Admin5**: Il supporto per Admin5 è disabilitato di default poiché è utilizzato solo in pochissimi paesi (come Francia o Belgio) per suddivisioni molto capillari. Se ne hai bisogno, abilitalo nella configurazione ed esegui l'importazione specifica per Admin5.

---

## 🔍 Ricerca e Consultazione Dati

Il bundle offre un servizio `GeonameSearchService` ad alte prestazioni per trovare città e località con il loro contesto amministrativo.

### 1. Ricerca Generale
Cerca toponimi usando una strategia ibrida (LIKE + Full-Text) ottimizzata.

```php
use Pallari\GeonameBundle\Service\GeonameSearchService;

$results = $searchService->search('Torino', [
    'countries' => ['IT'],
    'with_admin_names' => true, // Include i nomi di regione/provincia/comune
    'limit' => 10,
    'order_by' => 'population_desc', // Default: città più popolose per prime
    'select' => GeonameSearchService::PRESET_GEO // Solo id, nome e coordinate
]);
```

### 2. Recupero per ID
Ottieni i dati di un singolo toponimo tramite il suo `geonameid`.

```php
$city = $searchService->getById(3165524, true); 
// Restituisce un array con nomi, coordinate e contesti amministrativi
```

### 3. Navigazione Gerarchica
Recupera i "figli" o tutti i "discendenti" di un'unità amministrativa.

```php
// Ottieni tutti i comuni (ADM3) della provincia di Torino (TO)
$comuni = $searchService->getChildren('IT', [
    'admin1_code' => '09',
    'admin2_code' => 'TO'
], ['feature_codes' => ['ADM3']]);

// Ottieni TUTTI i discendenti di un genitore tramite il suo ID
// Il bundle risolve automaticamente la catena amministrativa
$tuttoPiemonte = $searchService->getDescendantsByParentId(3170831); // ID Regione Piemonte

// Ottieni le breadcrumbs (catena ordinata: Stato > Regione > Provincia > ...)
$crumbs = $searchService->getBreadcrumbs(3165524); // ID città di Torino
// Ritorna: [['name' => 'Italia', ...], ['name' => 'Piemonte', ...], ['name' => 'Torino', ...]]
```

### 4. Ricerca Geografica
Trova località tramite coordinate GPS.

```php
// Trova i toponimi più vicini a un punto GPS (Reverse Geocoding)
$vicini = $searchService->findNearest(45.07, 7.68, [
    'limit' => 5,
    'feature_classes' => ['P'] // Solo centri abitati
]);

// Trova tutte le località dentro un'area della mappa (Bounding Box)
$risultati = $searchService->findInBoundingBox(46.0, 8.0, 44.0, 7.0);
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
#[ORM\Index(columns: ['name', 'asciiName', 'alternatenames'], name: 'idx_fulltext', flags: ['fulltext'])]
```

### Ricerca Full-Text
Se `search.use_fulltext` è abilitato, il bundle usa automaticamente:
- `MATCH AGAINST` per MySQL/MariaDB.
- `to_tsvector` per PostgreSQL.
- **Note**: For SQLite (comune nei test), il bundle userà automaticamente il fallback alla ricerca `LIKE` standard per garantire la compatibilità.

### Esempio Query SQL
Come unire le tabelle per ottenere un indirizzo completo:
```sql
SELECT c.name as citta, a1.name as regione, a2.name as provincia
FROM geo_name c
LEFT JOIN geo_admin1 a1 ON a1.country_code = c.country_code AND a1.admin1_code = c.admin1_code
LEFT JOIN geo_admin2 a2 ON a2.country_code = c.country_code AND a2.admin1_code = c.admin1_code AND a2.admin2_code = c.admin2_code
WHERE c.name LIKE 'Torino%' AND c.is_deleted = 0;
```

---

## ⚖️ Disclaimer (Dichiarazione di non responsabilità)

Questo bundle è un progetto open-source indipendente e **non è affiliato, associato, autorizzato, approvato o in alcun modo ufficialmente collegato** con [GeoNames.org](https://www.geonames.org/).

I dati forniti dai comandi di sincronizzazione appartengono e sono gestiti da GeoNames. Sono solitamente rilasciati sotto la licenza [Creative Commons Attribution 4.0 License](https://creativecommons.org/licenses/by/4.0/). Si prega di rispettare i loro [Termini e Condizioni](https://www.geonames.org/about.html) e di considerare l'idea di sostenerli se trovate i loro dati utili.

---

## 📄 Licenza
Questo progetto è rilasciato sotto licenza MIT. Sviluppato con ❤️ da **Gian-Paolo Pallari**.
