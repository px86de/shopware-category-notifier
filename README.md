# Shopware Category Notifier Plugin

Ein Shopware 6.7 Plugin, das es Besuchern ermöglicht, sich für E-Mail-Benachrichtigungen bei neuen Produkten in bestimmten Kategorien anzumelden.

## Thanks for your Support <3 
[!["Buy Me A Coffee"](https://www.buymeacoffee.com/assets/img/custom_images/orange_img.png)](https://buymeacoffee.com/busaku)


## Features

✅ **Frontend-Anmeldeformular** auf Kategorieseiten
✅ **Double-Opt-In** Bestätigung per E-Mail
✅ **Automatische Benachrichtigungen** bei neuen Produkten
✅ **Benachrichtigungen bei Kategorie-Zuordnungen** zu bestehenden Produkten
✅ **Administration-Modul** zur Verwaltung der Abonnements
✅ **Mehrsprachig** (Deutsch/Englisch)
✅ **Datenschutzkonform** mit Abmeldefunktion
✅ **Anpassbare E-Mail-Templates** im Admin
✅ **Shopware 6.7** kompatibel

## Installation

1. Plugin installieren:
   ```bash
   composer require px86/category-notifier
   bin/console plugin:refresh
   bin/console plugin:install --activate Px86CategoryNotifier
   ```
2. Datenbank-Migration ausführen (geschieht automatisch)
3. Storefront Assets bauen:
   ```bash
   bin/console assets:install
   bin/build-storefront.sh
   ```
4. Administration Assets bauen:
   ```bash
   bin/build-administration.sh
   ```

## Konfiguration

**Einstellungen → Erweiterungen → Meine Erweiterungen → Px86CategoryNotifier → ... → Konfiguration**

### Grundeinstellungen

- **Anzeigemodus**: Lege fest, in welchen Kategorien das Formular angezeigt werden soll
  - In allen Kategorien
  - Nur in ausgewählten Kategorien
  - Alle außer ausgewählte Kategorien

### Formular-Darstellung

- **Formular-Position**: Wähle ob das Formular über oder unter den Produkten angezeigt wird

## Texte anpassen

### Formular-Texte (Storefront)

Die Texte im Storefront-Formular können in den Snippet-Dateien angepasst werden:

**Deutsch:**
```
src/Resources/snippet/de_DE/messages.de-DE.json
```

**Englisch:**
```
src/Resources/snippet/en_GB/messages.en-GB.json
```

Verfügbare Snippet-Keys:
- `px86-category-notifier.subscription.title` - Formular-Überschrift
- `px86-category-notifier.subscription.description` - Formular-Beschreibung
- `px86-category-notifier.subscription.email` - Label für E-Mail-Feld
- `px86-category-notifier.subscription.firstName` - Label für Vorname
- `px86-category-notifier.subscription.lastName` - Label für Nachname
- `px86-category-notifier.subscription.submit` - Button-Text
- `px86-category-notifier.subscription.success` - Erfolgs-Meldung
- `px86-category-notifier.subscription.error.*` - Fehler-Meldungen

**Nach Änderungen Cache leeren:**
```bash
bin/console cache:clear
```

### E-Mail-Templates

Die E-Mail-Templates können direkt im Admin bearbeitet werden:

**Einstellungen → E-Mail-Vorlagen**

Suche nach "Kategorie" oder filtere nach dem Plugin:
- **Kategorie-Benachrichtigung: Bestätigung** - Double-Opt-In E-Mail
- **Kategorie-Benachrichtigung: Neues Produkt** - Produktbenachrichtigung

## Technische Details

### Datenbank-Schema

Tabelle: `px86_category_notifier_subscription`
- Speichert E-Mail, Kategorie-ID, Name, Status
- Foreign Keys zu `category` und `salutation`
- Indizes für Performance-Optimierung

### Event-System

- Lauscht auf `ProductEvents::PRODUCT_WRITTEN_EVENT`
- Erkennt neue Produkte automatisch
- Versendet Benachrichtigungen an alle bestätigten Abonnenten

### API-Endpunkte

- `POST /category-notifier/subscribe` - Neues Abonnement
- `GET /category-notifier/confirm/{token}` - Bestätigung
- `POST /category-notifier/unsubscribe` - Abmeldung

## Anpassungen

### E-Mail-Templates

Templates befinden sich in `Service/NotificationService.php` und können angepasst werden.

### Design

Styling in `Resources/app/storefront/src/scss/base.scss` anpassen.

### Übersetzungen

Snippets unter `Resources/snippet/` für weitere Sprachen hinzufügen.

## Shopware Store Richtlinien

Dieses Plugin folgt den Shopware Store Qualitätsrichtlinien:
- ✅ PSR-4 Autoloading
- ✅ Shopware 6.7 API-Kompatibilität
- ✅ Mehrsprachigkeit
- ✅ Admin-Interface
- ✅ Proper Migration-System
- ✅ Service-Container Pattern
- ✅ Event-Subscriber Pattern

## Support

Bei Fragen oder Problemen wenden Sie sich bitte an info@px86.de.

## Changelog

### Version 1.0.0
- Initiales Release
- Frontend-Anmeldeformular
- Double-Opt-In Bestätigung
- Automatische Benachrichtigungen
- Administration-Modul
