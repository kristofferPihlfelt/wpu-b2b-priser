# WPU B2B-priser

Gratis plugin från [wordpressutveckling.se](https://wordpressutveckling.se/). Koden förklaras rad för rad i guiden: **[https://wordpressutveckling.se/b2b-prisplugin-woocommerce/](https://wordpressutveckling.se/b2b-prisplugin-woocommerce/)**

Byggt av [Kristoffer Pihlfelt](https://pihlfelt.se) på [Pihlfelt & Vänner](https://pihlfelt.se). Licens: GPL-2.0-or-later.

- **Bidragsgivare:** Pihlfelt & Vänner
- **Kräver WordPress:** 6.7
- **Kräver PHP:** 8.2
- **Kräver:** WooCommerce
- **Version:** 1.0.0
- **Licens:** GPL-2.0-or-later

## Vad det gör

Lägger till kundgruppen B2B-kund (en roll med samma rättigheter som Kund), ett fält för B2B-pris på varje produkt och variation, en procentrabatt för gruppen och valet att visa priser exklusive moms för B2B-kunder. Priset filtreras i WooCommerce prisgetters, så rabatten räknas en gång och gäller i butik, varukorg och kassa. Variabla produkter får rätt prisintervall och egen prishash.

## Installation

1. Kontrollera att WooCommerce är aktiverat.
2. Gå till Tillägg > Lägg till nytt > Ladda upp tillägg och välj zip-filen. Aktivera.
3. Ställ in Rabatt i procent och Visa priser exkl. moms under WooCommerce > Inställningar > Produkter.
4. Ge kunderna rollen B2B-kund under Användare. Sätt fasta B2B-priser under produktens flik Allmänt eller per variation.
5. Provhandla som B2B-kund i en testkopia innan du släpper in riktiga kunder.

## Källa

Koden förklaras rad för rad i guiden "Bygg ett B2B-prisplugin för WooCommerce från start till slut":
https://wordpressutveckling.se/b2b-prisplugin-woocommerce/

Den senaste versionen finns alltid på samma adress:
https://wordpressutveckling.se/ladda-ner/wpu-b2b-priser/

Avinstallerar du via WordPress flyttas B2B-kunderna tillbaka till rollen Kund och rollen tas bort.

## Ändringslogg

### 1.0.0
* Första versionen, samma kod som i guiden.
