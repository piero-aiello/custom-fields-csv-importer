=== Custom Fields CSV Importer ===
Contributors: pieroaiello  
Tags: custom fields, csv, importer, acf, post meta  
Requires at least: 5.6  
Tested up to: 6.5  
Requires PHP: 7.4  
Stable tag: 1.0.0  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

Importa campi personalizzati (meta fields) da file CSV per uno o più Custom Post Types (CPT) esistenti.

== Description ==

**Custom Fields CSV Importer** ti consente di aggiornare rapidamente i campi personalizzati (`postmeta`) di post, pagine o qualsiasi Custom Post Type esistente tramite un file CSV.

**Funzionalità principali:**
- Compatibile con campi standard WordPress (`postmeta`)
- Supporta l'importazione batch (ottimizzata a blocchi di 100 righe)
- Selezione di uno o più post type
- Modalità di anteprima con prime 10 righe e bottone “Visualizza tutte”
- Esportazione dei risultati in CSV (aggiornati / non trovati)
- Interfaccia user-friendly in stile admin WordPress

**Limitazioni attuali (versione beta):**
- Al momento vengono gestiti solo valori di tipo stringa. Campi complessi come array o serialized ACF non sono ancora supportati.

== Installation ==

1. Scarica il plugin come file `.zip`
2. Vai in *Plugin > Aggiungi nuovo > Carica plugin* e seleziona il file `.zip`
3. Attiva il plugin
4. Troverai la voce **Custom Fields CSV Importer** nel menu principale dell'admin WordPress

== Frequently Asked Questions ==

= Quali colonne deve avere il CSV? =

Il file deve contenere almeno 3 colonne:  
- `ID` del post esistente  
- `meta_key` (il nome del campo personalizzato)  
- `meta_value` (il valore da salvare)

= Funziona con campi ACF? =

Sì, se il valore è una stringa. Per array o strutture complesse ACF serializzate, verrà aggiunto il supporto in una futura versione.

= Dove vengono salvati i file CSV di log? =

I file CSV con risultati dell’import vengono salvati in `wp-content/uploads/cfci/`.

== Screenshots ==

1. Schermata iniziale con selezione CPT e caricamento CSV
2. Modalità anteprima CSV con prime righe visualizzate
3. Conferma dell'importazione con link per scaricare i risultati

== Changelog ==

= 1.0.0 =
* Versione iniziale del plugin: importazione campi personalizzati via CSV + anteprima + esportazione risultati

== Upgrade Notice ==

= 1.0.0 =
Versione stabile iniziale.
