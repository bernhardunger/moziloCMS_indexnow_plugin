# Projektkontext: indexnow Plugin für moziloCMS

## Umgebung

- **CMS**: moziloCMS 3.0.x
- **PHP**: 8.x
- **Hosting**: IONOS Shared Hosting (kein SSH, kein Composer, kein intl-Extension)
- **Produktivsite**: `https://www.steuerkanzlei-hader.de`
- **Repository**: `https://github.com/bernhardunger/moziloCMS_indexnow_plugin`
- **Workflow**: GitHub Flow (Feature-Branches + PR auf `main`)
- **Lokale Entwicklung**: Laragon (Windows, Unterordner-Setup), VSCode mit PHP Intelephense

---

## Plugin-Übersicht

Das Plugin `indexnow` ist ein Companion-Plugin zu `_seo_urls` und übermittelt alle
URLs der `sitemap.xml` via IndexNow API an Bing, Yandex u.a.

Die Sitemap wird per HTTP abgerufen – dadurch werden die Slug-URLs, die `_seo_urls`
on-the-fly in die Sitemap schreibt, automatisch korrekt übernommen.

Das Admin-Panel ist direkt über den Button **„Admin-Panel öffnen"** in der
Plugin-Konfiguration im moziloCMS-Backend erreichbar.

### Kernfunktionen
- Sitemap-Abruf per HTTP (`file_get_contents` + Stream-Context)
- URL-Extraktion aus `<loc>`-Tags mit Host-Filter und Deduplizierung
- Batch-POST an IndexNow API (`https://api.indexnow.org/indexnow`)
- Auto-Detect Host via `HTTP_HOST` (Validierung analog zu `_seo_urls::getSafeOrigin`)
- Auto-Ableitung Sitemap-URL aus Host (`https://{host}/sitemap.xml`)
- Konfigurierbarer Endpunkt mit Default via `const DEFAULT_ENDPOINT`
- Debug-Modus: URL-Liste + JSON-Payload im Browser, kein echter API-Call
- CSRF-Schutz (One-Time-Token via Session, `hash_equals`)
- Effektive Werte (Host, Sitemap, Endpunkt) in Config-Descriptions sichtbar

### Architektur (Methoden)
```php
// moziloCMS Plugin-API
getContent($value)           // 'admin_panel' oder '' → renderAdminPanel()
getConfig()                  // Konfigurationsfelder inkl. --admin~~ und effektiver Werte
getInfo()                    // Plugin-Beschreibung für Backend
getDefaultSettings()         // Vorbelegung bei Erstinstallation (endpoint)

// Effektive Konfigurationswerte
getEffectiveHost()           // Config → HTTP_HOST Fallback
getEffectiveSitemapUrl()     // Config → https://{host}/sitemap.xml Fallback
getEffectiveEndpoint()       // Config → DEFAULT_ENDPOINT Fallback
isDebugMode()                // debug_mode === 'true'

// Kern-Logik
runSubmission()              // Validierung → Sitemap → URLs → IndexNow oder Debug
buildDebugOutput()           // Formatierte Debug-Ausgabe (URL-Liste + JSON-Payload)
fetchUrl($url)               // HTTP GET via file_get_contents + Stream-Context
extractUrls($xml, $host)     // <loc>-Extraktion, Host-Filter, Deduplizierung
sendToIndexNow(...)          // HTTP POST, Statuscode auswerten
parseHttpStatus($headers)    // HTTP-Statuscode aus $http_response_header
interpretIndexNowResponse()  // Statuscode → menschenlesbare Meldung

// Admin-Panel (Orchestrierung)
renderAdminPanel()           // POST-Handling + Orchestrierung, kein HTML
buildPanelHtml(...)          // Reines HTML-Template, keine Logik

// Admin-Panel (Teilblöcke)
getAdminStyles()             // CSS-Block
buildWarnings()              // Debug-Banner + Warnungen + Key-Datei-Hinweis
buildConfigTable()           // Host/Sitemap/Endpunkt-Tabelle mit Herkunftshinweis
buildResultHtml()            // Ergebnis-Block nach Submit (success | error)
renderNotice($type, $msg)    // warning | success | error | debug Block

// Sicherheit
generateCsrfToken()          // bin2hex(random_bytes(16)), gespeichert in Session
validateCsrfToken()          // hash_equals(), One-Time-Token
getSetting($key)             // sicheres Auslesen aus $this->settings
```

### Klassenkonstanten
```php
const VERSION          // Plugin-Version (einzige Pflegestelle)
const DEFAULT_ENDPOINT // https://api.indexnow.org/indexnow (einzige Pflegestelle)
```

### moziloCMS Plugin-API – gelernte Erkenntnisse
- `getPluginContent()` der Basisklasse ruft intern `getContent()` auf → nur `getContent()` überschreiben
- `getInfo()[1]` muss `'2.0 / 3.0'` enthalten (enthält der String keine `'2'`, wird Plugin als 1.x markiert)
- `--admin~~` in `getConfig()` ist zwingend für `?pluginadmin=` Routing; ohne `description`+`buttontext` wird kein Button gerendert
- `getDefaultSettings()` wird beim ersten Anlegen der `plugin.conf.php` aufgerufen
- Klassenname muss exakt dem Ordnernamen entsprechen (kein Underscore-Prefix nötig außer für Ladereihenfolge)
- Config-Descriptions werden als `<label>` (text) bzw. `<span>` (checkbox) gerendert – HTML in checkbox-Descriptions erlaubt
- `--admin~~` wird immer vor allen anderen Feldern gerendert (hardcoded in `plugins.php`)

### Ablauf pro Submit
1. `getContent('')` → `renderAdminPanel()` – POST prüfen, CSRF validieren
2. `runSubmission()` – alle Eingaben validieren
3. `fetchUrl($sitemapUrl)` – Sitemap per HTTP abrufen
4. `extractUrls($xml, $host)` – URLs filtern und deduplizieren
5a. Debug-Modus: `buildDebugOutput()` → formatierte Ausgabe im Panel
5b. Produktiv: `sendToIndexNow()` → POST an IndexNow, Statuscode auswerten

---

## Codestil & Konventionen

- Sicherheit: Input immer validieren/sanitizen, kein direktes `$_GET`/`$_POST` ohne Prüfung
- Inline-Dokumentation auf **Deutsch**
- Versionierung via `const VERSION` in der Klasse (einzige Pflegestelle)
- Keine externen Abhängigkeiten, kein Composer
- Kein cURL – `file_get_contents` mit Stream-Context (IONOS Shared Hosting)
- HTTP-Statuscodes aus `$http_response_header` (globale PHP-Variable nach `file_get_contents`)

---

## Lokale Entwicklung

Laragon nutzt Unterordner-Setup (z.B. `localhost/projektname/`). Für lokale Tests:

| Feld | Lokaler Wert |
|---|---|
| `host` | Produktiv-Hostname (z.B. `www.example.com`) |
| `sitemap_url` | Produktiv-Sitemap (z.B. `https://www.example.com/sitemap.xml`) |
| `debug_mode` | aktiviert |

Damit wird die echte Produktiv-Sitemap abgerufen und der JSON-Payload angezeigt –
ohne Übermittlung an IndexNow. Auf Produktion beide Felder leer lassen.

---

## Versionshistorie

### v1.0.0 (aktuell, deployed)
- Erstveröffentlichung
- Sitemap-Abruf per HTTP, URL-Extraktion, IndexNow POST
- Auto-Detect Host via `HTTP_HOST`
- Auto-Ableitung Sitemap-URL aus Host
- Konfigurierbarer Endpunkt mit Default `https://api.indexnow.org/indexnow`
- Admin-Panel direkt im moziloCMS-Backend via `--admin~~`
- Effektive Werte in Config-Descriptions sichtbar
- Debug-Modus mit URL-Liste und JSON-Payload-Preview
- CSRF-Schutz (One-Time-Token)
- `getDefaultSettings()` für Endpoint-Vorbelegung bei Erstinstallation
- Host-Validierung mit Pfad-Stripping via `parse_url`
- Refactoring: `renderAdminPanel()` in `buildPanelHtml()`, `getAdminStyles()`,
  `buildWarnings()`, `buildConfigTable()`, `buildResultHtml()` aufgeteilt
- PHPUnit 12 Testsuite (46 Tests)
