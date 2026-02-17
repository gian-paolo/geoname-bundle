# GeonameBundle

[![Symfony](https://img.shields.io/badge/Symfony-%5E6.4%20%7C%20%5E7.0%20%7C%20%5E8.0-000000?style=flat-square&logo=symfony)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4?style=flat-square&logo=php)](https://php.net)
[![Latest Stable Version](https://img.shields.io/packagist/v/pallari/geoname-bundle.svg?style=flat-square)](https://packagist.org/packages/pallari/geoname-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/pallari/geoname-bundle.svg?style=flat-square)](https://packagist.org/packages/pallari/geoname-bundle)
[![Tests](https://github.com/gian-paolo/geoname-bundle/actions/workflows/tests.yml/badge.svg)](https://github.com/gian-paolo/geoname-bundle/actions)

🇮🇹 [Leggi la documentazione in Italiano](./README.it.md)

**GeonameBundle** is a high-performance solution for integrating and maintaining up-to-date geographical data from [GeoNames](https://www.geonames.org/) in your Symfony applications.
Designed to handle **millions of records** with minimal memory consumption and an **incremental daily sync** system.

---

## 🚀 Key Features

- **Interactive Installer**: Complete setup in seconds with `pallari:geoname:install`.
- **Smart Synchronization**: Downloads only daily changes and deletions.
- **Bulk SQL Performance**: Optimized DBAL queries reducing database roundtrips by 99%.
- **Universal**: Compatible with **MySQL, MariaDB, and PostgreSQL**.
- **Advanced Search**: Hybrid strategy combining prefix LIKE and Full-Text search.
- **Geospatial & Hierarchy**: Native support for breadcrumbs, proximity search, and map bounding boxes.

---

## 📖 Documentation

- [**Installation & Setup**](docs/setup.md): Quick start and configuration.
- [**Data Synchronization**](docs/import.md): Importing admin codes and daily updates.
- [**Querying Data**](docs/search.md): Using the `GeonameSearchService` for search, GPS, and navigation.

---

## 🛠 Quick Installation

```bash
composer require pallari/geoname-bundle
php bin/console pallari:geoname:install
```

---

## ⚖️ Disclaimer

This bundle is an independent, open-source project and is **not affiliated** with [GeoNames.org](https://www.geonames.org/). Data is typically released under the [Creative Commons Attribution 4.0 License](https://creativecommons.org/licenses/by/4.0/).

---

## 📄 License
MIT License. Developed with ❤️ by **Gian-Paolo Pallari**.
