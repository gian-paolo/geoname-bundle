# GeonameBundle

[![Symfony](https://img.shields.io/badge/Symfony-%5E6.4%20%7C%20%5E7.0%20%7C%20%5E8.0-000000?style=flat-square&logo=symfony)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4?style=flat-square&logo=php)](https://php.net)
[![Latest Stable Version](https://img.shields.io/packagist/v/pallari/geoname-bundle.svg?style=flat-square)](https://packagist.org/packages/pallari/geoname-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/pallari/geoname-bundle.svg?style=flat-square)](https://packagist.org/packages/pallari/geoname-bundle)
[![Tests](https://github.com/gian-paolo/geoname-bundle/actions/workflows/tests.yml/badge.svg)](https://github.com/gian-paolo/geoname-bundle/actions)

**GeonameBundle** è una soluzione ad alte prestazioni per integrare e mantenere aggiornati i dati geografici di [GeoNames](https://www.geonames.org/) nelle tue applicazioni Symfony.
Progettato per gestire **milioni di record** con un consumo di memoria minimo e un sistema di **aggiornamento quotidiano incrementale**.

---

## 🚀 Caratteristiche Principali

- **Installatore Interattivo**: Configurazione completa in pochi secondi con `pallari:geoname:install`.
- **Sincronizzazione Intelligente**: Scarica solo le modifiche e le eliminazioni giornaliere.
- **Performance SQL Bulk**: Query DBAL ottimizzate che riducono i roundtrip del database del 99%.
- **Universale**: Compatibile con **MySQL, MariaDB e PostgreSQL**.
- **Ricerca Avanzata**: Strategia ibrida che combina LIKE e ricerca Full-Text.
- **Geospaziale e Gerarchia**: Supporto nativo per breadcrumbs, ricerca di prossimità e bounding box per mappe.

---

## 📖 Documentazione Estesa

- [**Installazione e Setup**](docs/it/setup.md): Guida rapida e configurazione entità.
- [**Sincronizzazione Dati**](docs/it/import.md): Importazione codici amministrativi e aggiornamenti quotidiani.
- [**Ricerca e Consultazione**](docs/it/search.md): Uso di `GeonameSearchService` per ricerche, GPS e navigazione.

---

## 🛠 Installazione Rapida

```bash
composer require pallari/geoname-bundle
php bin/console pallari:geoname:install
```

---

## ⚖️ Disclaimer (Dichiarazione di non responsabilità)

Questo bundle è un progetto indipendente e **non è affiliato** a [GeoNames.org](https://www.geonames.org/). I dati sono solitamente rilasciati sotto licenza [Creative Commons Attribution 4.0](https://creativecommons.org/licenses/by/4.0/).

---

## 📄 Licenza
Licenza MIT. Sviluppato con ❤️ da **Gian-Paolo Pallari**.
