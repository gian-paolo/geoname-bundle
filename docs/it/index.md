# Documentazione GeonameBundle

Benvenuti nella documentazione ufficiale di **GeonameBundle**, un bundle Symfony ad alte prestazioni per l'integrazione dei dati di [GeoNames](https://www.geonames.org/).

## 📖 Guide

- [**Installazione e Setup**](setup.md): Come installare il bundle e configurare le tue entità.
- [**Sincronizzazione Dati**](import.md): Importazione dei codici amministrativi e mantenimento dei dati aggiornati con il sync quotidiano.
- [**Ricerca e Consultazione**](search.md): Guida completa all'uso di `GeonameSearchService` per ricerche testuali, geografiche e navigazione gerarchica.

---

## 🚀 Perché GeonameBundle?

Questo bundle nasce dall'esigenza di gestire milioni di record in modo efficiente all'interno dell'ecosistema Symfony. Le scelte tecniche chiave includono:

- **Sincronizzazione Incrementale**: Scarica solo le modifiche del giorno precedente.
- **Bulk SQL**: Riduzione al minimo dei roundtrip verso il database grazie a query DBAL ottimizzate.
- **Chiavi Composte**: Mappatura naturale della gerarchia per join amministrativi ultra-rapidi.
- **Ricerca Ibrida**: Combina la velocità degli indici B-Tree con la potenza del Full-Text.
