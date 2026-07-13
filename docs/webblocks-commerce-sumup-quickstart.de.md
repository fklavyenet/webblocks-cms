# SumUp mit WebBlocks Commerce verbinden

Mit dieser Anleitung richtest du eine erste SumUp-Sandbox-Zahlung ein, ohne echtes Geld zu
bewegen. Du musst kein Zahlungsformular bauen: Kundinnen und Kunden geben ihre Kartendaten auf der
von SumUp gehosteten Zahlungsseite ein.

Sprache: [English](webblocks-commerce-sumup-quickstart.md) · **Deutsch** ·
[Türkçe](webblocks-commerce-sumup-quickstart.tr.md)

## Das brauchst du

- ein installiertes und aktiviertes WebBlocks-Commerce-Plugin
- Zugang zum SumUp Dashboard
- Zugriff auf die geschützten Umgebungsvariablen des CMS-Servers oder Unterstützung durch deine
  Hosting-Administration
- eine öffentlich erreichbare HTTPS-Adresse für den Shop
- ungefähr zehn Minuten Zeit

Sandbox-Zahlungen sind Simulationen. Es wird kein echtes Geld bewegt.

## Bevor du beginnst

Die Zugangsdaten werden derzeit als geschützte Server-Variablen hinterlegt. Trage den API-Schlüssel
niemals in eine CMS-Seite, einen Block, ein Produkt oder ein öffentliches Einstellungsfeld ein.

Wenn du den Server nicht selbst verwaltest, gib deiner Hosting-Administration die vier Namen aus
Schritt 4. Übermittle den API-Schlüssel nur über den Secret Manager des Hostings oder einen anderen
freigegebenen sicheren Kanal — nicht per normaler E-Mail, Chat, Screenshot oder Support-Ticket.

## Schritt 1 — SumUp-Sandbox-Händlerkonto erstellen

1. Melde dich im [SumUp Dashboard](https://me.sumup.com/) an.
2. Öffne **Developer Settings**.
3. Öffne den Tab **Sandboxes**.
4. Erstelle ein Sandbox-Händlerkonto, falls noch keines existiert.
5. Wähle das Sandbox-Konto über den Kontowechsler aus.

Das ausgewählte Konto muss deutlich als Sandbox gekennzeichnet sein. Verwende für diesen Test kein
Live-Händlerkonto. Fehlt die Sandbox-Option, folge dem Registrierungslink in SumUps offizieller
[Testanleitung](https://developer.sumup.com/online-payments/testing).

## Schritt 2 — Sandbox Merchant ID kopieren

Wenn das Sandbox-Konto ausgewählt ist, zeigt SumUp oben links den Kontonamen und die
**Merchant ID**. WebBlocks Commerce bezeichnet diesen Wert als Merchant Code. Er sieht ungefähr
wie `MXXXXXXX` aus.

Kopiere diesen Wert für Schritt 4. Verwende nicht die Merchant ID des Live-Kontos.

## Schritt 3 — Test-API-Schlüssel erstellen

Lass das Sandbox-Konto ausgewählt und gehe dann wie folgt vor:

1. Öffne dein Profil und **Settings**.
2. Gehe zu **For Developers → Toolkit**.
3. Öffne **API Keys**.
4. Wähle **Create** und vergib einen eindeutigen Namen, zum Beispiel
   `WebBlocks Commerce sandbox`.
5. Kopiere oder lade den geheimen Schlüssel herunter, sobald SumUp ihn anzeigt.

Verwende nicht den **SumUp Public Key**. Benötigt wird der geheime serverseitige API-Schlüssel. Ein
Testschlüssel beginnt normalerweise mit `sk_test_`. SumUp zeigt das vollständige Secret später
nicht erneut an; speichere es deshalb sofort in einem sicheren Secret Manager.

## Schritt 4 — CMS-Server konfigurieren

Lege im Hosting unter Umgebungsvariablen oder Secrets diese Werte an:

```env
WEBBLOCKS_COMMERCE_GATEWAY=sumup
WEBBLOCKS_COMMERCE_SUMUP_MODE=sandbox
WEBBLOCKS_COMMERCE_SUMUP_API_KEY=hier-den-sk_test-schluessel-eintragen
WEBBLOCKS_COMMERCE_SUMUP_MERCHANT_CODE=hier-die-sandbox-merchant-id-eintragen
```

Verwendet die Installation eine Laravel-`.env`-Datei, trage die Werte dort ein und leere danach
den Konfigurations-Cache:

```bash
php artisan config:clear
```

Wenn dein Deployment die Konfiguration normalerweise cached, baue den Cache anschließend über den
üblichen Deployment-Ablauf neu auf. Starte langlebige PHP-Worker neu, wenn das Hosting dies
verlangt.

Der Modus `sandbox` macht die beabsichtigte Umgebung in der Diagnose sichtbar. SumUp verwendet
einen gemeinsamen API-Host; API-Schlüssel und Merchant ID müssen daher selbst zum Sandbox-Konto
gehören.

## Schritt 5 — Bereitschaft in Commerce prüfen

1. Melde dich in der CMS-Administration an.
2. Öffne **Commerce → Commerce Settings** oder
   `/webadmin/plugins/webblocks-commerce/settings`.
3. Prüfe:
   - Gateway: `sumup`
   - SumUp-Modus: `sandbox`
   - API-Schlüssel: konfiguriert
   - Merchant Code: konfiguriert
   - Checkout: bereit
   - Plugin-Schema: bereit

Die Einstellungsseite zeigt absichtlich nur „konfiguriert“ oder „fehlt“ und niemals den
API-Schlüssel. Ist das Schema nicht bereit, öffne **System → Plugins → WebBlocks Commerce** und
führe zuerst das Plugin-Setup beziehungsweise die Migrationen aus.

## Schritt 6 — Testprodukt erstellen

1. Öffne **Commerce → Products**.
2. Erstelle oder bearbeite ein Produkt.
3. Trage Titel, Slug, Preis, Währung und Steuerklasse ein.
4. Verwende für den ersten Test `EUR`, sofern das Sandbox-Konto keine andere Währung nutzt.
5. Setze den Status auf **Active**.
6. Speichere das Produkt.

Ein Entwurf oder archiviertes Produkt kann nicht gekauft werden. Bei aktivierter Bestandsführung
muss mindestens ein Stück verfügbar sein.

## Schritt 7 — Nativen Commerce-Block einfügen

1. Öffne die gewünschte CMS-Seite im Page Builder.
2. Füge einen **Commerce Buy Button** in einen normalen Slot ein.
3. Wähle das aktive Produkt.
4. Stelle Beschriftung, Ausrichtung und Preisanzeige ein.
5. Prüfe die Vorschau und veröffentliche über den normalen CMS-Ablauf.

Verwende keinen Trusted-HTML-Block und füge keine SumUp-Checkout-URL in den Inhalt ein. Die URL
wird pro Bestellung erstellt und läuft nach ungefähr 30 Minuten ab.

## Schritt 8 — Erfolgreiche Sandbox-Zahlung durchführen

1. Öffne die öffentliche Produktseite oder die Seite mit dem Commerce-Button.
2. Lege das Produkt in den Warenkorb.
3. Prüfe Menge, MwSt., Währung und Gesamtbetrag.
4. Wähle **Weiter zur sicheren Bezahlung**.
5. Verwende auf der SumUp-Seite diese offizielle Testkarte:

```text
Kartennummer: 4200 0000 0000 0091
Ablaufdatum: ein zukünftiges Datum, zum Beispiel 12/30
CVV: beliebige drei Ziffern, zum Beispiel 123
Karteninhaber: beliebiger Name
```

6. Schließe die Zahlung ab und kehre zum Shop zurück.
7. Öffne in der CMS-Administration **Commerce → Orders**.
8. Prüfe, dass die Bestellung zu `paid` und der Zahlungsversuch zu `succeeded` wechselt.

Die Rückkehrseite im Browser ist kein Zahlungsnachweis. WebBlocks Commerce markiert die Bestellung
erst als bezahlt, nachdem es die SumUp-Benachrichtigung empfangen, den Checkout erneut von SumUp
abgerufen und Händler, Referenz, Betrag, Währung, Endstatus und Transaktion geprüft hat.

## Kein manuelles SumUp-Webhook-Setup nötig

Für diese Integration musst du im SumUp Dashboard keine Webhook-URL anlegen. WebBlocks Commerce
sendet beim Erstellen jedes Checkouts automatisch diese `return_url`:

```text
https://dein-shop.example/commerce/webhooks/sumup
```

Der Shop muss öffentlich über HTTPS erreichbar sein. Firewall, Wartungsseite, HTTP-Passwort oder
Proxy-Regeln dürfen SumUps POST-Anfrage nicht blockieren.

## Optional: fehlgeschlagene Zahlung testen

SumUp simuliert bei bestimmten Sandbox-Summen eine Ablehnung. Erstelle dafür ein separates
temporäres Produkt mit einem endgültigen Gesamtbetrag von genau `11.00 EUR`. Prüfe, dass die
Bestellung nicht als bezahlt markiert und reservierter Bestand nach dem Fehler freigegeben wird.
Ändere dafür nicht den Preis eines echten Produkts.

## Auf Live-Zahlungen umstellen

Erst nach einem vollständigen erfolgreichen Sandbox-Test:

1. Wähle im SumUp Dashboard das echte Händlerkonto.
2. Schließe die von SumUp verlangte Unternehmens- und Auszahlungskontrolle ab.
3. Kopiere die Live Merchant ID.
4. Erstelle einen separaten Live-API-Schlüssel; er beginnt normalerweise mit `sk_live_`.
5. Ersetze die Serverwerte und setze `WEBBLOCKS_COMMERCE_SUMUP_MODE=live`.
6. Aktualisiere die Anwendungskonfiguration über den normalen Deployment-Ablauf.
7. Prüfe erneut **Commerce Settings**.
8. Führe eine vertretbare Kleinbetragszahlung aus und prüfe Bestellung sowie Auszahlung.

Mische niemals einen Testschlüssel mit einer Live Merchant ID und verwende den Sandbox-Schlüssel
nicht in Produktion.

## Wenn etwas nicht funktioniert

- **API-Schlüssel fehlt:** Schreibweise der Variablen und Aktualisierung des Konfigurations-Caches
  prüfen.
- **Checkout schlägt trotz „bereit“ fehl:** Schlüssel und Merchant ID müssen zum selben
  Sandbox-Konto gehören; nicht den Public Key verwenden.
- **Bestellung bleibt pending:** Prüfen, ob `/commerce/webhooks/sumup` öffentlich per HTTPS und POST
  erreichbar ist und nicht von Firewall oder Wartungsmodus blockiert wird.
- **Checkout abgelaufen:** Bestellung erneut aus dem Warenkorb starten; alte Checkout-URLs nicht
  wiederverwenden.

## Sicherheitsregeln

- API-Schlüssel niemals in CMS-Inhalte, Browser-Code, Repository, Screenshots oder Logs schreiben.
- Einen echten API-Schlüssel niemals in einen Chat einfügen.
- Sandbox- und Live-Zugangsdaten strikt trennen.
- Einen möglicherweise offengelegten Schlüssel sofort widerrufen und ersetzen.
- Für Versand oder Leistung immer den CMS-Bestellstatus verwenden, nicht die Browser-Erfolgsseite.

Technische Details stehen im
[WebBlocks Commerce Operator Guide](webblocks-commerce-operator-guide.md).

Offizielle SumUp-Quellen:

- [Online-Zahlungen testen](https://developer.sumup.com/online-payments/testing)
- [API-Schlüssel erstellen und schützen](https://developer.sumup.com/tools/authorization/api-keys)
- [Hosted Checkout](https://developer.sumup.com/online-payments/checkouts/hosted-checkout)
- [Checkout-Webhooks](https://developer.sumup.com/online-payments/webhooks)
