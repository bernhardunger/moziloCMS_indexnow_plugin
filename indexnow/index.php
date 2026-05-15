<?php
if (!defined('IS_CMS')) die();

/**
 * Plugin:   indexnow
 * @author:  B.Unger
 * @version: v1.0.0  (siehe Klassenkonstante VERSION)
 * @license: GPL
 *
 * Übermittelt alle URLs der Sitemap via IndexNow API an Bing, Yandex u.a.
 * Liest die sitemap.xml per HTTP-Abruf, damit Slug-URLs des _seo_urls Plugins
 * korrekt übernommen werden.
 *
 * Voraussetzung: API-Key-Datei unter https://{host}/{key}.txt erreichbar.
 *
 * Funktionen:
 *  - Sitemap-Abruf per HTTP (Slug-URLs von _seo_urls werden übernommen)
 *  - Host automatisch aus HTTP_HOST ermittelt wenn Config-Feld leer
 *  - Sitemap-URL automatisch aus Host abgeleitet wenn Config-Feld leer
 *  - Endpoint konfigurierbar, Standard: https://api.indexnow.org/indexnow
 *  - Debug-Modus: URL-Liste und JSON-Payload im Browser, kein echter API-Call
 *  - CSRF-Schutz für den Submit-Button
 */

class indexnow extends Plugin {

    const VERSION = 'v1.0.0';

    /**
     * Standard-Endpunkt der IndexNow API.
     * Einzige Pflegestelle falls sich der Default ändert.
     */
    const DEFAULT_ENDPOINT = 'https://api.indexnow.org/indexnow';

    // -----------------------------------------------------------------------
    // Pflichtmethoden moziloCMS Plugin-API
    // -----------------------------------------------------------------------

    function getContent($value) {
        // Wird aufgerufen via {PLUGIN(indexnow|admin_panel)} im Seiteninhalt
        // sowie via ?pluginadmin=indexnow aus dem CMS-Backend (--admin~~ Mechanismus).
        if ($value === 'admin_panel' || $value === '') {
            return $this->renderAdminPanel();
        }
        return '';
    }

    function getConfig() {
        $effectiveHost     = $this->getEffectiveHost();
        $effectiveSitemap  = $this->getEffectiveSitemapUrl();
        $hostHint          = $effectiveHost    !== '' ? '<br>Aktuell erkannt: <code>' . htmlspecialchars($effectiveHost,   ENT_QUOTES, 'UTF-8') . '</code>' : '';
        $sitemapHint       = $effectiveSitemap !== '' ? '<br>Aktuell abgeleitet: <code>' . htmlspecialchars($effectiveSitemap, ENT_QUOTES, 'UTF-8') . '</code>' : '';

        return array(
            'api_key' => array(
                'type'        => 'text',
                'description' => '* IndexNow API-Key (alphanumerisch, 8–128 Zeichen; identisch mit dem Dateinamen der Key-Datei)',
            ),
            'host' => array(
                'type'        => 'text',
                'description' => 'Hostname ohne Schema (z.B. www.example.com). Leer lassen für automatische Erkennung via HTTP_HOST.' . $hostHint,
            ),
            'sitemap_url' => array(
                'type'        => 'text',
                'description' => 'Vollständige URL der sitemap.xml. Leer lassen – wird automatisch aus dem Host abgeleitet.' . $sitemapHint,
            ),
            'endpoint' => array(
                'type'        => 'text',
                'description' => 'IndexNow Endpunkt. Leer lassen – Standard:<br><code>' . self::DEFAULT_ENDPOINT . '</code> (verteilt intern an alle teilnehmenden Suchmaschinen).',
            ),
            // --admin~~ erzeugt einen Button in der Plugin-Konfiguration im Backend.
            // Klick öffnet das Admin-Panel via ?pluginadmin=indexnow → getContent('').
            '--admin~~' => array(
                'description' => 'URLs manuell an IndexNow übermitteln.',
                'buttontext'  => 'Admin-Panel öffnen',
                'datei_admin' => 'index.php',
            ),
            'debug_mode' => array(
                'type'        => 'checkbox',
                'description' => 'Debug-Modus: Zeigt extrahierte URLs und JSON-Payload im Browser – sendet nichts an IndexNow.',
            ),
        );
    }

    /**
     * Setzt Standardwerte beim ersten Installieren des Plugins.
     * Wird von moziloCMS aufgerufen wenn plugin.conf.php neu angelegt wird.
     */
    function getDefaultSettings() {
        return array(
            'endpoint' => self::DEFAULT_ENDPOINT,
        );
    }

    function getInfo() {

        $description = '
<p>Übermittelt alle URLs der <code>sitemap.xml</code> via <b>IndexNow</b> an Bing,
Yandex und weitere unterstützte Suchmaschinen.</p>

<h4>Funktionen</h4>
<table>
  <tr><td><b>Sitemap-Abruf per HTTP</b></td><td>Slug-URLs des _seo_urls Plugins werden automatisch übernommen</td></tr>
  <tr><td><b>Auto-Detect Host</b></td><td>Hostname wird automatisch aus HTTP_HOST ermittelt wenn nicht konfiguriert</td></tr>
  <tr><td><b>Auto-Detect Sitemap</b></td><td>Sitemap-URL wird aus dem Host abgeleitet wenn nicht konfiguriert</td></tr>
  <tr><td><b>IndexNow POST</b></td><td>Alle URLs in einer einzigen Anfrage übermittelt</td></tr>
  <tr><td><b>Admin-Panel</b></td><td>Submit-Button auf einer CMS-Seite einbettbar via <code>{PLUGIN(indexnow|admin_panel)}</code></td></tr>
  <tr><td><b>Status-Feedback</b></td><td>HTTP-Statuscode und Ergebnismeldung direkt im Panel</td></tr>
  <tr><td><b>Debug-Modus</b></td><td>Checkbox in den Plugin-Einstellungen aktivieren → Submit-Button zeigt URL-Liste und JSON-Payload, sendet nichts an IndexNow</td></tr>
</table>

<h4>Einrichtung</h4>
<ol>
  <li>API-Key generieren unter <a href="https://www.bing.com/indexnow/getstarted" target="_blank">bing.com/indexnow/getstarted</a> – das Tool erstellt Key und Key-Datei fertig zum Download</li>
  <li>Key-Datei <code>{key}.txt</code> in den Webroot hochladen</li>
  <li>Nur den API-Key im Plugin konfigurieren – Host und Sitemap-URL werden automatisch erkannt</li>
  <li>Admin-Panel auf einer passwortgeschützten CMS-Seite einbinden: <code>{PLUGIN(indexnow|admin_panel)}</code></li>
</ol>

<h4>Companion-Plugin</h4>
<p>Funktioniert zusammen mit <b>_seo_urls</b> – Slug-URLs werden via HTTP-Sitemap-Abruf
automatisch korrekt übermittelt.</p>
';

        return array(
            '<b>indexnow</b> ' . self::VERSION,
            '2.0 / 3.0',
            $description,
            '',
            '',
            array('indexnow', 'seo', 'bing', 'sitemap', 'url'),
        );
    }

    // -----------------------------------------------------------------------
    // Effektive Konfigurationswerte ermitteln
    // -----------------------------------------------------------------------

    /**
     * Gibt den konfigurierten Hostnamen zurück.
     * Fällt auf den validierten HTTP_HOST zurück wenn das Config-Feld leer ist.
     * Gibt einen leeren String zurück wenn kein gültiger Host ermittelbar ist.
     */
    private function getEffectiveHost(): string {
        $configured = $this->getSetting('host');
        if ($configured !== '') {
            // Pfad-Anteile entfernen falls jemand z.B. "localhost/stb-hader" einträgt.
            // parse_url gibt bei reinen Hostnamen ohne Schema keinen 'host'-Key zurück,
            // daher Schema voranstellen und anschließend wieder entfernen.
            $parsed = parse_url('http://' . $configured);
            return isset($parsed['host']) ? $parsed['host'] : $configured;
        }

        // Auto-Detect via HTTP_HOST (Validierung analog zu _seo_urls::getSafeOrigin)
        $rawHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        if (preg_match('/^[a-zA-Z0-9.\-]+(:\d+)?$/', $rawHost)) {
            return $rawHost;
        }

        return '';
    }

    /**
     * Gibt die konfigurierte Sitemap-URL zurück.
     * Leitet sie aus dem effektiven Host ab wenn das Config-Feld leer ist.
     * Gibt einen leeren String zurück wenn kein Host ermittelbar ist.
     */
    private function getEffectiveSitemapUrl(): string {
        $configured = $this->getSetting('sitemap_url');
        if ($configured !== '') {
            return $configured;
        }

        $host = $this->getEffectiveHost();
        if ($host === '') {
            return '';
        }

        // HTTPS bevorzugen; HTTP als Fallback
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        return $scheme . '://' . $host . '/sitemap.xml';
    }

    /**
     * Gibt den konfigurierten IndexNow-Endpunkt zurück.
     * Fällt auf DEFAULT_ENDPOINT zurück wenn das Config-Feld leer ist.
     */
    private function getEffectiveEndpoint(): string {
        $configured = $this->getSetting('endpoint');
        return ($configured !== '') ? $configured : self::DEFAULT_ENDPOINT;
    }

    /**
     * Gibt true zurück wenn der Debug-Modus aktiviert ist.
     */
    private function isDebugMode(): bool {
        return $this->settings->get('debug_mode') === 'true';
    }

    // -----------------------------------------------------------------------
    // Admin-Panel rendern
    // -----------------------------------------------------------------------

    /**
     * Rendert das Admin-Panel mit Konfigurationshinweisen, Statusausgabe
     * und dem Submit-Button zur manuellen URL-Übermittlung.
     */
    private function renderAdminPanel(): string {

        $submitResult = '';
        $isDebug      = $this->isDebugMode();

        // POST-Anfrage verarbeiten (CSRF-Token prüfen)
        if (
            isset($_SERVER['REQUEST_METHOD']) &&
            strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' &&
            isset($_POST['indexnow_submit']) &&
            $this->validateCsrfToken()
        ) {
            $submitResult = $this->runSubmission();
        }

        // Effektive Werte für Anzeige im Panel ermitteln
        $apiKey     = $this->getSetting('api_key');
        $host       = $this->getEffectiveHost();
        $sitemapUrl = $this->getEffectiveSitemapUrl();
        $endpoint   = $this->getEffectiveEndpoint();
        $version    = self::VERSION;

        $csrfToken  = $this->generateCsrfToken();

        // Herkunftshinweis für jeden Wert (konfiguriert vs. automatisch ermittelt)
        $hostSource     = ($this->getSetting('host') !== '')       ? 'konfiguriert' : 'auto-erkannt';
        $sitemapSource  = ($this->getSetting('sitemap_url') !== '') ? 'konfiguriert'  : 'abgeleitet';
        $endpointSource = ($this->getSetting('endpoint') !== '')    ? 'konfiguriert'  : 'Standard';

        $hostEsc     = htmlspecialchars($host,       ENT_QUOTES, 'UTF-8');
        $sitemapEsc  = htmlspecialchars($sitemapUrl, ENT_QUOTES, 'UTF-8');
        $endpointEsc = htmlspecialchars($endpoint,   ENT_QUOTES, 'UTF-8');

        // Warnungen bei fehlender oder nicht ermittelbarer Konfiguration
        $warnings = '';
        if ($apiKey === '') {
            $warnings .= $this->renderNotice('warning', '⚠ API-Key ist nicht konfiguriert.');
        }
        if ($host === '') {
            $warnings .= $this->renderNotice('warning', '⚠ Host konnte nicht ermittelt werden. Bitte manuell konfigurieren.');
        }
        if ($sitemapUrl === '') {
            $warnings .= $this->renderNotice('warning', '⚠ Sitemap-URL konnte nicht abgeleitet werden.');
        }

        // Debug-Modus-Banner
        $debugBanner = $isDebug
            ? $this->renderNotice('debug', '🔍 Debug-Modus aktiv – es wird nichts an IndexNow gesendet.')
            : '';

        // Hinweis zur Key-Datei
        $keyFileHint = '';
        if ($apiKey !== '' && $host !== '') {
            $keyFileUrl  = 'https://' . htmlspecialchars($host,   ENT_QUOTES, 'UTF-8')
                         . '/'        . htmlspecialchars($apiKey, ENT_QUOTES, 'UTF-8') . '.txt';
            $keyFileHint = '<p class="in-hint">🔑 Key-Datei muss erreichbar sein unter: <code>' . $keyFileUrl . '</code></p>';
        }

        // Ergebnis-Block nach Submit
        $resultHtml = '';
        if ($submitResult !== '') {
            $isError    = stripos($submitResult, 'fehler') !== false;
            $resultHtml = $this->renderNotice(
                $isError ? 'error' : 'success',
                '<pre style="margin:0;white-space:pre-wrap;font-family:monospace;font-size:.82rem">'
                . htmlspecialchars($submitResult, ENT_QUOTES, 'UTF-8')
                . '</pre>'
            );
        }

        // Button deaktivieren wenn Konfiguration unvollständig
        $disabled    = ($apiKey === '' || $host === '' || $sitemapUrl === '') ? ' disabled' : '';
        $buttonTitle = $disabled ? ' title="Bitte zuerst alle Einstellungen konfigurieren."' : '';
        $buttonClass = $isDebug ? 'in-btn in-btn-debug' : 'in-btn';
        $buttonLabel = $isDebug ? '🔍 Debug: URLs anzeigen (kein API-Call)' : 'Alle URLs jetzt übermitteln';

        return <<<HTML
<style>
.in-panel            { font-family: Arial, sans-serif; max-width: 680px; margin: 1rem 0; padding: 1.25rem; border: 1px solid #d0d0d0; border-radius: 6px; background: #fafafa; }
.in-panel h3         { margin: 0 0 1rem; font-size: 1.1rem; }
.in-notice           { padding: .65rem .9rem; border-radius: 4px; margin-bottom: .6rem; font-size: .9rem; line-height: 1.5; }
.in-warning          { background: #fff3cd; color: #664d03; border: 1px solid #ffe69c; }
.in-success          { background: #d1e7dd; color: #0a3622; border: 1px solid #a3cfbb; }
.in-error            { background: #f8d7da; color: #58151c; border: 1px solid #f1aeb5; }
.in-debug            { background: #e8f4fd; color: #0c4a6e; border: 1px solid #bae0fd; }
.in-config           { font-size: .83rem; color: #444; background: #f0f0f0; border-radius: 4px; padding: .5rem .8rem; margin-bottom: .8rem; }
.in-config table     { border-collapse: collapse; width: 100%; }
.in-config td        { padding: 3px 6px; vertical-align: top; }
.in-config td:first-child { font-weight: bold; white-space: nowrap; width: 100px; }
.in-config .in-src   { color: #888; font-style: italic; font-size: .78rem; }
.in-hint             { font-size: .85rem; color: #555; margin: .3rem 0 .8rem; }
.in-hint code        { background: #e8e8e8; padding: 2px 5px; border-radius: 3px; }
.in-meta             { font-size: .8rem; color: #888; margin-top: .75rem; margin-bottom: 0; }
.in-btn              { background: #0d6efd; color: #fff; border: none; padding: .5rem 1.4rem; border-radius: 4px; cursor: pointer; font-size: .95rem; margin-top: .4rem; }
.in-btn:hover:not([disabled]) { background: #0b5ed7; }
.in-btn[disabled]    { background: #9ec4fd; cursor: not-allowed; }
.in-btn-debug        { background: #6c757d; }
.in-btn-debug:hover:not([disabled]) { background: #5a6268; }
</style>
<div class="in-panel">
  <h3>IndexNow – URL-Übermittlung</h3>

  {$debugBanner}
  {$warnings}

  <div class="in-config">
    <table>
      <tr>
        <td>Host:</td>
        <td>{$hostEsc} <span class="in-src">({$hostSource})</span></td>
      </tr>
      <tr>
        <td>Sitemap:</td>
        <td>{$sitemapEsc} <span class="in-src">({$sitemapSource})</span></td>
      </tr>
      <tr>
        <td>Endpunkt:</td>
        <td>{$endpointEsc} <span class="in-src">({$endpointSource})</span></td>
      </tr>
    </table>
  </div>

  {$keyFileHint}
  {$resultHtml}

  <form method="post" action="">
    <input type="hidden" name="indexnow_submit" value="1">
    <input type="hidden" name="indexnow_csrf"   value="{$csrfToken}">
    <button class="{$buttonClass}" type="submit"{$disabled}{$buttonTitle}>{$buttonLabel}</button>
  </form>

  <p class="in-meta">indexnow {$version}</p>
</div>
HTML;
    }

    /**
     * Rendert einen farbigen Hinweis-Block (warning | success | error | debug).
     */
    private function renderNotice(string $type, string $message): string {
        return '<div class="in-notice in-' . $type . '">' . $message . '</div>';
    }

    // -----------------------------------------------------------------------
    // Kern-Logik: Sitemap lesen → IndexNow senden (oder Debug-Ausgabe)
    // -----------------------------------------------------------------------

    /**
     * Liest alle nötigen Einstellungen, holt die Sitemap, extrahiert URLs
     * und übermittelt sie an die IndexNow API – oder gibt sie im Debug-Modus
     * als formatierten Text zurück.
     */
    private function runSubmission(): string {

        $apiKey     = $this->getSetting('api_key');
        $host       = $this->getEffectiveHost();
        $sitemapUrl = $this->getEffectiveSitemapUrl();
        $endpoint   = $this->getEffectiveEndpoint();

        // --- Eingaben validieren ---

        if ($apiKey === '') {
            return 'Fehler: API-Key nicht konfiguriert.';
        }
        if (!preg_match('/^[a-zA-Z0-9\-]{8,128}$/', $apiKey)) {
            return 'Fehler: API-Key enthält ungültige Zeichen oder ist zu kurz/lang (8–128 alphanumerische Zeichen oder Bindestriche).';
        }
        if ($host === '') {
            return 'Fehler: Host nicht ermittelbar. Bitte manuell in den Einstellungen hinterlegen.';
        }
        if (!preg_match('/^[a-zA-Z0-9.\-]+(:\d+)?$/', $host)) {
            return 'Fehler: Host ist ungültig (ermittelter Wert: "' . $host . '").'
                 . "\n" . 'Bitte den Hostnamen manuell in den Plugin-Einstellungen eintragen.';
        }
        if ($sitemapUrl === '') {
            return 'Fehler: Sitemap-URL nicht ermittelbar.';
        }
        if (!filter_var($sitemapUrl, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED)) {
            return 'Fehler: Sitemap-URL ist keine gültige URL.';
        }
        if (!in_array(parse_url($sitemapUrl, PHP_URL_SCHEME), array('http', 'https'), true)) {
            return 'Fehler: Sitemap-URL muss http:// oder https:// verwenden.';
        }
        if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return 'Fehler: Endpunkt ist keine gültige URL.';
        }

        // --- Sitemap per HTTP abrufen ---

        $xml = $this->fetchUrl($sitemapUrl);
        if ($xml === null) {
            return 'Fehler: Sitemap konnte nicht abgerufen werden.' . "\n" . 'URL: ' . $sitemapUrl;
        }
        if (trim($xml) === '') {
            return 'Fehler: Sitemap-Antwort ist leer.';
        }

        // --- URLs extrahieren ---

        $urls = $this->extractUrls($xml, $host);
        if (empty($urls)) {
            return 'Fehler: Keine gültigen URLs für Host "' . $host . '" in der Sitemap gefunden.';
        }

        // --- Debug-Modus: Ausgabe statt Übermittlung ---

        if ($this->isDebugMode()) {
            return $this->buildDebugOutput($host, $apiKey, $endpoint, $urls);
        }

        // --- An IndexNow übermitteln ---

        return $this->sendToIndexNow($endpoint, $host, $apiKey, $urls);
    }

    /**
     * Gibt eine formatierte Debug-Ausgabe zurück (URL-Liste + JSON-Payload).
     * Wird im Debug-Modus statt der echten API-Übermittlung verwendet.
     */
    private function buildDebugOutput(
        string $host,
        string $apiKey,
        string $endpoint,
        array  $urls
    ): string {

        $keyLocation = 'https://' . $host . '/' . $apiKey . '.txt';
        $urlCount    = count($urls);

        $payload = json_encode(
            array(
                'host'        => $host,
                'key'         => $apiKey,
                'keyLocation' => $keyLocation,
                'urlList'     => $urls,
            ),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );

        return implode("\n", array(
            '=== IndexNow Debug-Ausgabe (' . self::VERSION . ') ===',
            '',
            'Endpunkt : ' . $endpoint,
            'Host     : ' . $host,
            'Key      : ' . $apiKey,
            'Key-Datei: ' . $keyLocation,
            'URLs     : ' . $urlCount . ' gefunden',
            '',
            '--- URL-Liste ---',
            implode("\n", $urls),
            '',
            '--- JSON-Payload (würde an IndexNow gesendet) ---',
            $payload,
        ));
    }

    // -----------------------------------------------------------------------
    // HTTP-Hilfsmethoden
    // -----------------------------------------------------------------------

    /**
     * Ruft eine URL per HTTP GET ab und gibt den Response-Body zurück.
     * Gibt null zurück wenn der Abruf fehlgeschlagen ist.
     */
    private function fetchUrl(string $url): ?string {

        $context = stream_context_create(array(
            'http' => array(
                'method'          => 'GET',
                'timeout'         => 15,
                'user_agent'      => 'moziloCMS-IndexNow/' . self::VERSION,
                'ignore_errors'   => true,
                'follow_location' => true,
                'max_redirects'   => 3,
            ),
            'ssl' => array(
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ),
        ));

        $body = @file_get_contents($url, false, $context);

        return ($body !== false) ? $body : null;
    }

    /**
     * Extrahiert alle <loc>-URLs aus dem Sitemap-XML.
     * Filtert auf URLs die zum konfigurierten Host gehören.
     * Gibt ein Array mit validierten, einzigartigen URLs zurück.
     */
    private function extractUrls(string $xml, string $host): array {

        $urls      = array();
        $seen      = array();
        $hostLower = strtolower($host);

        if (!preg_match_all('|<loc>\s*(https?://[^<\s]+)\s*</loc>|i', $xml, $matches)) {
            return $urls;
        }

        foreach ($matches[1] as $raw) {
            $url = trim($raw);

            // Grundlegende URL-Validierung
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            // Nur HTTP/HTTPS
            if (!in_array(parse_url($url, PHP_URL_SCHEME), array('http', 'https'), true)) {
                continue;
            }

            // Nur URLs des eigenen Hosts übermitteln
            $urlHost = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
            if ($urlHost !== $hostLower) {
                continue;
            }

            // Duplikate entfernen
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            $urls[] = $url;
        }

        return $urls;
    }

    /**
     * Sendet die URL-Liste via HTTP POST an die IndexNow API.
     * Gibt eine menschenlesbare Ergebnismeldung zurück.
     */
    private function sendToIndexNow(
        string $endpoint,
        string $host,
        string $apiKey,
        array  $urls
    ): string {

        $keyLocation = 'https://' . $host . '/' . $apiKey . '.txt';
        $urlCount    = count($urls);

        $payload = json_encode(
            array(
                'host'        => $host,
                'key'         => $apiKey,
                'keyLocation' => $keyLocation,
                'urlList'     => $urls,
            ),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );

        if ($payload === false) {
            return 'Fehler: URL-Liste konnte nicht als JSON kodiert werden.';
        }

        $context = stream_context_create(array(
            'http' => array(
                'method'        => 'POST',
                'header'        => implode("\r\n", array(
                    'Content-Type: application/json; charset=utf-8',
                    'Content-Length: ' . strlen($payload),
                    'User-Agent: moziloCMS-IndexNow/' . self::VERSION,
                )),
                'content'       => $payload,
                'timeout'       => 20,
                'ignore_errors' => true,
            ),
            'ssl' => array(
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ),
        ));

        @file_get_contents($endpoint, false, $context);

        $statusCode = $this->parseHttpStatus($http_response_header ?? array());

        return $this->interpretIndexNowResponse($statusCode, $urlCount, $host, $apiKey);
    }

    /**
     * Liest den HTTP-Statuscode aus dem $http_response_header Array.
     * Gibt 0 zurück wenn kein gültiger Status gefunden wurde.
     */
    private function parseHttpStatus(array $headers): int {
        foreach ($headers as $header) {
            if (preg_match('|^HTTP/\S+\s+(\d{3})|', $header, $m)) {
                return (int) $m[1];
            }
        }
        return 0;
    }

    /**
     * Übersetzt den HTTP-Statuscode der IndexNow API in eine
     * menschenlesbare Ergebnismeldung.
     */
    private function interpretIndexNowResponse(int $status, int $urlCount, string $host, string $apiKey): string {

        $keyFileUrl = 'https://' . $host . '/' . $apiKey . '.txt';

        switch ($status) {
            case 200:
                return "✓ Erfolgreich: {$urlCount} URL(s) an IndexNow übermittelt.\nHTTP-Status: 200 OK";

            case 202:
                return "✓ Akzeptiert: {$urlCount} URL(s) wurden zur Verarbeitung entgegengenommen.\n"
                     . "HTTP-Status: 202 Accepted\n"
                     . "(Verarbeitung erfolgt asynchron durch die Suchmaschine.)";

            case 400:
                return "Fehler: Ungültige Anfrage (400 Bad Request).\n"
                     . "Bitte API-Key und URL-Format prüfen.";

            case 403:
                return "Fehler: Zugriff verweigert (403 Forbidden).\n"
                     . "Key-Datei muss erreichbar sein unter: {$keyFileUrl}\n"
                     . "Inhalt der Datei muss exakt der API-Key sein: {$apiKey}";

            case 422:
                return "Fehler: Ungültige URL(s) in der Übermittlung (422 Unprocessable Entity).\n"
                     . "Alle URLs müssen zum konfigurierten Host '{$host}' gehören.";

            case 429:
                return "Fehler: Anfrage-Limit überschritten (429 Too Many Requests).\n"
                     . "Bitte später erneut versuchen.";

            case 0:
                return "Fehler: Keine Antwort vom IndexNow-Endpunkt erhalten.\n"
                     . "Bitte Internetverbindung und Endpunkt-URL prüfen.";

            default:
                return "Fehler: Unerwarteter HTTP-Status {$status} vom IndexNow-Endpunkt.";
        }
    }

    // -----------------------------------------------------------------------
    // CSRF-Schutz
    // -----------------------------------------------------------------------

    /**
     * Erzeugt einen kryptografisch sicheren CSRF-Token und speichert ihn in der Session.
     */
    private function generateCsrfToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $token = bin2hex(random_bytes(16));
        $_SESSION['indexnow_csrf_token'] = $token;
        return $token;
    }

    /**
     * Prüft den übermittelten CSRF-Token gegen den Session-Token (Constant-Time-Vergleich).
     * Macht den Token nach einmaliger Verwendung ungültig (One-Time-Token).
     */
    private function validateCsrfToken(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $submitted = isset($_POST['indexnow_csrf']) ? (string) $_POST['indexnow_csrf'] : '';
        $stored    = isset($_SESSION['indexnow_csrf_token']) ? (string) $_SESSION['indexnow_csrf_token'] : '';

        if ($submitted === '' || $stored === '') {
            return false;
        }

        // Token nach Verwendung ungültig machen
        unset($_SESSION['indexnow_csrf_token']);

        return hash_equals($stored, $submitted);
    }

    // -----------------------------------------------------------------------
    // Hilfsmethoden
    // -----------------------------------------------------------------------

    /**
     * Liest einen Plugin-Einstellungswert sicher aus.
     * Gibt einen leeren String zurück wenn der Wert nicht gesetzt ist.
     */
    private function getSetting(string $key): string {
        $value = $this->settings->get($key);
        return is_string($value) ? trim($value) : '';
    }
}
