# Querying Data

The `GeonameSearchService` is the primary entry point for accessing your imported geographical data. It is optimized for speed and supports complex filtering.

## 🛠 Basic Usage

Inject the service into your controller or command:

```php
use Pallari\GeonameBundle\Service\GeonameSearchService;

public function __construct(private GeonameSearchService $searchService) {}
```

## 🔍 Search Toponyms

The `search()` method uses a hybrid strategy: prefix matching with standard indexes and (optional) Full-Text search for synonyms and alternate names.

```php
$results = $searchService->search('Torino', [
    'countries' => ['IT'],           // Filter by country
    'feature_classes' => ['P'],      // Only populated places
    'with_admin_names' => true,      // Join labels for Region/Province
    'limit' => 10,
    'order_by' => 'population_desc'  // Default: largest cities first
]);
```

### Response Format
Each result is an associative array containing:
- `geonameid`: The unique ID.
- `name`: Localized name.
- `latitude`, `longitude`: GPS coordinates.
- `population`: Population count.
- `admin1_name` ... `admin5_name`: Resolved labels (if `with_admin_names` is true).

### Using Presets
To optimize performance, you can choose which columns to return:
- `GeonameSearchService::PRESET_MINIMAL`: Only ID and Name.
- `GeonameSearchService::PRESET_GEO`: ID, Name, Lat, Lon.
- `GeonameSearchService::PRESET_FULL` (Default): All columns.

```php
$results = $searchService->search('Tor', ['select' => GeonameSearchService::PRESET_GEO]);
```

## 📍 Geospatial Queries

### Find Nearest
Find places near a specific GPS point (Reverse Geocoding).

```php
$nearby = $searchService->findNearest(45.07, 7.68, [
    'limit' => 5,
    'feature_classes' => ['P']
]);
// Results include a 'distance' field in KM
```

### Bounding Box
Find all places within a rectangular area (useful for map views).

```php
$results = $searchService->findInBoundingBox($north, $east, $south, $west);
```

## 🌳 Hierarchy & Navigation

### Get Breadcrumbs
Returns the full administrative chain for a given ID.

```php
$crumbs = $searchService->getBreadcrumbs(3165524);
// Result: [
//   ['name' => 'Italy', 'geonameid' => 3175395],
//   ['name' => 'Piedmont', 'geonameid' => 3170831],
//   ['name' => 'Turin', 'geonameid' => 3165524]
// ]
```

### Get Descendants
Find everything inside a specific administrative unit.

```php
// Get all municipalities (ADM3) in Piedmont region
$places = $searchService->getDescendantsByParentId(3170831, [
    'feature_codes' => ['ADM3']
]);
```

## ⚙️ Advanced: Full-Text Search
To enable advanced Full-Text search:
1. Set `search.use_fulltext: true` in your config.
2. Run the `pallari:geoname:install` command and accept the database optimization step.
3. In your code, you can now use `'order_by' => 'relevance'` in the search options.
