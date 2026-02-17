# Ricerca e Consultazione Dati

Il servizio `GeonameSearchService` è il punto di ingresso principale per accedere ai dati geografici importati. È ottimizzato per la velocità e supporta filtri complessi.

## 🛠 Utilizzo Base

Inietta il servizio nel tuo controller o comando:

```php
use Pallari\GeonameBundle\Service\GeonameSearchService;

public function __construct(private GeonameSearchService $searchService) {}
```

## 🔍 Ricerca Toponimi

Il metodo `search()` utilizza una strategia ibrida: corrispondenza dei prefissi tramite indici standard e (opzionale) ricerca Full-Text per sinonimi e nomi alternativi.

```php
$risultati = $searchService->search('Torino', [
    'countries' => ['IT'],           // Filtra per nazione
    'feature_classes' => ['P'],      // Solo centri abitati
    'with_admin_names' => true,      // Include i nomi di Regione/Provincia
    'limit' => 10,
    'order_by' => 'population_desc'  // Default: città più grandi per prime
]);
```

### Formato della Risposta
Ogni risultato è un array associativo contenente:
- `geonameid`: L'ID univoco.
- `name`: Nome localizzato.
- `latitude`, `longitude`: Coordinate GPS.
- `population`: Numero di abitanti.
- `admin1_name` ... `admin5_name`: Nomi risolti (se `with_admin_names` è true).

### Utilizzo dei Preset
Per ottimizzare le prestazioni, puoi scegliere quali colonne restituire:
- `GeonameSearchService::PRESET_MINIMAL`: Solo ID e Nome.
- `GeonameSearchService::PRESET_GEO`: ID, Nome, Lat, Lon.
- `GeonameSearchService::PRESET_FULL` (Default): Tutte le colonne disponibili.

```php
$risultati = $searchService->search('Tor', ['select' => GeonameSearchService::PRESET_GEO]);
```

## 📍 Query Geografiche

### Trova i più vicini
Trova località vicine a un punto GPS specifico (Reverse Geocoding).

```php
$vicini = $searchService->findNearest(45.07, 7.68, [
    'limit' => 5,
    'feature_classes' => ['P']
]);
// I risultati includono un campo 'distance' in KM
```

### Bounding Box
Trova tutte le località dentro un'area rettangolare (utile per viste su mappa).

```php
$risultati = $searchService->findInBoundingBox($north, $east, $south, $west);
```

## 🌳 Gerarchia e Navigazione

### Ottieni Breadcrumbs
Restituisce l'intera catena amministrativa per un dato ID.

```php
$crumbs = $searchService->getBreadcrumbs(3165524);
// Risultato: [
//   ['name' => 'Italia', 'geonameid' => 3175395],
//   ['name' => 'Piemonte', 'geonameid' => 3170831],
//   ['name' => 'Torino', 'geonameid' => 3165524]
// ]
```

### Ottieni Discendenti
Trova tutto ciò che è contenuto in un'unità amministrativa specifica.

```php
// Esempio: Ottieni tutti i comuni (ADM3) della regione Piemonte
$comuni = $searchService->getDescendantsByParentId(3170831, [
    'feature_codes' => ['ADM3']
]);
```

## ⚙️ Avanzato: Ricerca Full-Text
Per abilitare la ricerca avanzata Full-Text:
1. Imposta `search.use_fulltext: true` nella tua configurazione.
2. Lancia il comando `pallari:geoname:install` e accetta lo step di ottimizzazione database.
3. Nel codice, puoi ora usare `'order_by' => 'relevance'` nelle opzioni di ricerca.
