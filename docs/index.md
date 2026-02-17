# GeonameBundle Documentation

Welcome to the official documentation for **GeonameBundle**, a high-performance Symfony bundle for integrating [GeoNames](https://www.geonames.org/) data.

## 📖 Guides

- [**Installation & Setup**](setup.md): How to install the bundle and configure your entities.
- [**Data Synchronization**](import.md): Importing administrative codes and maintaining data up-to-date with daily sync.
- [**Querying Data**](search.md): Comprehensive guide on using `GeonameSearchService` for search, geospatial queries, and hierarchy navigation.

## 🇮🇹 Documentation in Italian

Potete trovare la documentazione completa in italiano nella cartella [**docs/it/**](./it/index.md).

---

## 🚀 Why GeonameBundle?

This bundle was born from the need to handle millions of records efficiently within the Symfony ecosystem. Key technical choices include:

- **Incremental Sync**: Only downloads what changed yesterday.
- **Bulk SQL**: Minimal database roundtrips using optimized DBAL queries.
- **Composite Keys**: Natural hierarchy mapping for ultra-fast administrative joins.
- **Hybrid Search**: Combining the speed of B-Tree indexes with the power of Full-Text search.
