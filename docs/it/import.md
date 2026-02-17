# Sincronizzazione Dati

Mantenere i dati aggiornati è fondamentale. GeonameBundle offre un sistema di sincronizzazione incrementale robusto ed efficiente.

## 1. Setup Iniziale: Codici Amministrativi

I codici amministrativi (come `IT.09`) sono collegati ai loro nomi visualizzabili (come `Piemonte`) in tabelle separate. È necessario eseguire questo comando una sola volta dopo l'installazione:

```bash
php bin/console pallari:geoname:import-admin-codes
```

Questo comando scarica le etichette ufficiali da GeoNames e popola le tabelle da `geo_admin1` a `geo_admin5`.

## 2. Abilitazione Paesi

Il bundle elabora solo i dati per i paesi che sono **abilitati** nel database. Puoi gestire questa impostazione tramite la tabella `geo_country`:

```sql
-- Abilita l'Italia
UPDATE geoname_country SET is_enabled = 1 WHERE code = 'IT';
```

## 3. Sincronizzazione Quotidiana

Il comando `pallari:geoname:sync` è il cuore del sistema di aggiornamento.

```bash
php bin/console pallari:geoname:sync
```

### Come funziona:
- **Prima Esecuzione**: Esegue un download COMPLETO del file `.zip` per ogni paese abilitato.
- **Esecuzioni Successive**: Rileva automaticamente la data dell'ultimo import. Se i dati sono recenti (ultime 24 ore), scarica solo gli **aggiornamenti quotidiani** (modifiche ed eliminazioni) da GeoNames.
- **Performance**: Utilizza operazioni SQL bulk per gestire migliaia di record in pochi secondi.

### Automazione (Crontab)
Per mantenere i dati perfettamente sincronizzati, è caldamente consigliato pianificare questo comando ogni giorno. GeoNames solitamente pubblica gli aggiornamenti intorno alle 04:00 UTC.

```cron
# Esegue il sync di geoname ogni giorno alle 06:00 UTC
0 6 * * * php /path/to/your/project/bin/console pallari:geoname:sync --env=prod
```

## 📊 Monitoraggio Importazioni

Ogni sessione di sincronizzazione viene registrata nella tabella `geo_import`. Puoi controllare:
- `status`: `running`, `completed` o `failed`.
- `records_processed`: Numero totale di elementi aggiornati o inseriti.
- `error_message`: In caso di errore, contiene lo stack trace o l'errore SQL.

---

## 🗺️ Mappatura Livelli Amministrativi

GeoNames organizza i dati in 5 livelli amministrativi (da Admin1 a Admin5). Il significato di questi livelli varia drasticamente da paese a paese. Ecco alcuni esempi di paesi che utilizzano livelli granulari:

| Paese | Admin1 (ADM1) | Admin2 (ADM2) | Admin3 (ADM3) | Admin4 (ADM4) | Admin5 (ADM5) |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Italia (IT)** | Regioni | Province | Comuni | - | Municipi/Zone |
| **Nepal (NP)** | Province | Distretti | Municipalità | Ward | Sotto-Ward |
| **Rep. Dom. (DO)** | Regioni | Province | Comuni | Distretti Munic. | Quartieri |
| **Francia (FR)** | Regioni | Dipartimenti | Arrondissement | Comuni | Cantoni |
| **Germania (DE)** | Stati (Länder) | Distretti Amm. | Circondari (Kreise) | Comuni | - |
| **USA (US)** | Stati | Contee | - | - | - |
| **Uganda (UG)** | Regioni | Distretti | Contee | Sotto-contee | Parrocchie |
