# moziloCMS IndexNow Plugin

Ein eigenständiges Plugin für **moziloCMS 3.0.x** zur automatisierten URL-Übermittlung an die [IndexNow API](https://www.indexnow.org/) (Bing, Yandex u.a.).

Companion-Plugin zu [_seo_urls](https://github.com/bernhardunger/moziloCMS_seo_plugin) – Slug-URLs werden via HTTP-Sitemap-Abruf automatisch korrekt übermittelt.

---

## Funktionen

| Feature | Beschreibung |
|---|---|
| **Sitemap-Abruf per HTTP** | Liest die `sitemap.xml` live per HTTP – Slug-URLs des `_seo_urls` Plugins werden automatisch übernommen |
| **Auto-Detect Host** | Hostname wird aus `HTTP_HOST` ermittelt wenn das Config-Feld leer bleibt |
| **Auto-Detect Sitemap** | Sitemap-URL wird aus dem Host abgeleitet (`https://{host}/sitemap.xml`) wenn nicht konfiguriert |
| **IndexNow POST** | Alle URLs in einer einzigen Batch-Anfrage übermittelt |
| **Admin-Panel** | Submit-Button direkt auf einer CMS-Seite einbettbar |
| **Status-Feedback** | HTTP-Statuscode und Ergebnismeldung direkt im Panel |
| **Debug-Modus** | Zeigt URL-Liste und JSON-Payload im Browser – kein echter API-Call |
| **CSRF-Schutz** | One-Time-Token via Session für den Submit-Button |

---

## Voraussetzungen

- moziloCMS 3.0.x
- PHP 8.x
- `allow_url_fopen = On` (für `file_get_contents` HTTP-Abruf)
- API-Key-Datei im Webroot erreichbar (siehe Einrichtung)

---

## Installation

1. Ordner `indexnow/` in das `plugins/`-Verzeichnis des CMS hochladen
2. Plugin im moziloCMS-Backend aktivieren
3. API-Key konfigurieren (siehe Einrichtung)
4. Eine CMS-Seite anlegen (z.B. „IndexNow") und den Plugin-Tag in den Seiteninhalt einfügen:
   ```
   {PLUGIN(indexnow|admin_panel)}
   ```
5. Seite im CMS mit einem Passwortschutz absichern

---

## Einrichtung

### 1. API-Key generieren

Den API-Key über das offizielle Bing-Tool generieren – es erstellt den Key und die fertige Key-Datei zum Download:

**→ [https://www.bing.com/indexnow/getstarted](https://www.bing.com/indexnow/getstarted)**

### 2. Key-Datei in den Webroot legen

Eine Datei `{key}.txt` mit dem Key als **einzigem Inhalt** (kein Zeilenumbruch, kein Leerzeichen) in den Webroot hochladen:

```
https://www.example.com/a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4.txt
```

Inhalt der Datei:
```
a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4
```

> Das Bing-Tool unter dem Link oben generiert diese Datei fertig zum Download.

### 3. Plugin konfigurieren

Im moziloCMS-Backend unter **Plugins → indexnow → Einstellungen**:

| Feld | Beschreibung | Pflicht |
|---|---|---|
| `api_key` | Der generierte IndexNow API-Key | ✅ |
| `host` | Hostname ohne Schema (z.B. `www.example.com`) | leer = Auto-Detect |
| `sitemap_url` | Vollständige URL der `sitemap.xml` | leer = Auto-Ableitung |
| `endpoint` | IndexNow Endpunkt | leer = Standard |
| `debug_mode` | Debug-Modus aktivieren | – |

**Minimalfall:** Nur den `api_key` eintragen – Host und Sitemap-URL werden automatisch erkannt.

### Endpunkt

Der Standard-Endpunkt `https://api.indexnow.org/indexnow` reicht vollständig aus – IndexNow verteilt die URLs intern an alle teilnehmenden Suchmaschinen (Bing, Yandex u.a.). Das Feld in den Plugin-Einstellungen muss daher in der Regel leer bleiben.

---

## Verwendung

### Admin-Panel einbinden

Das Plugin rendert sein Admin-Panel als normalen HTML-Block. Um den Submit-Button zugänglich zu machen:

1. Im moziloCMS-Backend eine neue Seite anlegen, z.B. unter der Kategorie „Admin"
2. In den Seiteninhalt einfügen:
   ```
   {PLUGIN(indexnow|admin_panel)}
   ```
3. Die Seite im CMS mit einem Passwortschutz versehen – moziloCMS bietet dafür eine eingebaute Funktion unter den Seiteneigenschaften

Das Panel zeigt beim Aufruf:

- Aktive Konfiguration (Host, Sitemap, Endpunkt) mit Herkunftshinweis (`konfiguriert` / `auto-erkannt` / `Standard`)
- Warnungen bei fehlender Konfiguration
- Pfad der erwarteten Key-Datei
- Ergebnis der letzten Übermittlung mit HTTP-Statuscode

### Debug-Modus

Debug-Modus in den Plugin-Einstellungen aktivieren → der Submit-Button sendet **nichts** an IndexNow, sondern gibt im Panel aus:

- Alle extrahierten URLs (gefiltert auf den eigenen Host)
- Den vollständigen JSON-Payload der an IndexNow gesendet würde

---

## Zusammenspiel mit _seo_urls

Das Plugin liest die `sitemap.xml` **per HTTP-Abruf** – dadurch werden die Slug-URLs, die `_seo_urls` on-the-fly in die Sitemap schreibt, automatisch korrekt übernommen. Es ist keine manuelle Konfiguration der Slug-URLs nötig.

---

## HTTP-Statuscodes

| Code | Bedeutung |
|---|---|
| `200 OK` | URLs erfolgreich übermittelt |
| `202 Accepted` | Übermittlung angenommen, Verarbeitung asynchron |
| `400 Bad Request` | Ungültige Anfrage – Key oder URL-Format prüfen |
| `403 Forbidden` | Key-Datei nicht erreichbar oder falscher Inhalt |
| `422 Unprocessable Entity` | URLs gehören nicht zum konfigurierten Host |
| `429 Too Many Requests` | Rate Limit – später erneut versuchen |

---

## Entwicklung

```
moziloCMS_indexnow_plugin/
└── indexnow/
    └── index.php   ← Plugin-Hauptdatei
```

**Tech-Stack & Constraints:**
- PHP 8.x, moziloCMS 3.0.x Plugin-API
- Kein Composer, keine externen Abhängigkeiten
- Deployment per FTP (IONOS Shared Hosting)

**Codestil:**
- Inline-Dokumentation auf Deutsch
- Versionierung via `const VERSION` (einzige Pflegestelle)
- Input-Validierung für alle Einstellungen
- GitHub Flow: `main` = stabil, Feature-Branches für neue Features

---

## Versionshistorie

### v1.0.0
- Erstveröffentlichung
- Sitemap-Abruf per HTTP, URL-Extraktion, IndexNow POST
- Auto-Detect Host via `HTTP_HOST`
- Auto-Ableitung Sitemap-URL aus Host
- Konfigurierbarer Endpunkt mit Default `https://api.indexnow.org/indexnow`
- Admin-Panel als einbettbarer CMS-Seiteninhalt
- Debug-Modus mit URL-Liste und JSON-Payload-Preview
- CSRF-Schutz (One-Time-Token)

---

## Lizenz

GPL – siehe [LICENSE](LICENSE)

## Autor

Bernhard Unger – [github.com/bernhardunger](https://github.com/bernhardunger)
