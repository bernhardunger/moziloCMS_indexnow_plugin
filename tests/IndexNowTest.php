<?php
/**
 * PHPUnit Testsuite für das indexnow Plugin.
 *
 * Testet alle reinen Logik-Methoden via ReflectionMethod –
 * kein Code-Change am Plugin nötig.
 *
 * Ausgeführt mit: ./vendor/bin/phpunit
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class IndexNowTest extends TestCase {

    private indexnow $plugin;

    // -----------------------------------------------------------------------
    // Setup
    // -----------------------------------------------------------------------

    protected function setUp(): void {
        $this->plugin = new indexnow();

        // HTTP_HOST und HTTPS für jeden Test zurücksetzen
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['HTTPS']);
    }

    // -----------------------------------------------------------------------
    // Hilfsmethoden
    // -----------------------------------------------------------------------

    /**
     * Ruft eine private Methode der Plugin-Instanz auf.
     */
    private function call(string $method, mixed ...$args): mixed {
        $ref = new ReflectionMethod($this->plugin, $method);
        $ref->setAccessible(true);
        return $ref->invoke($this->plugin, ...$args);
    }

    /**
     * Setzt einen Wert in den Settings-Stub der Plugin-Instanz.
     */
    private function setSetting(string $key, string $value): void {
        $this->plugin->settings->set($key, $value);
    }

    // -----------------------------------------------------------------------
    // extractUrls
    // -----------------------------------------------------------------------

    #[Test]
    public function extractUrls_leeresSitemap_gibtLeersArrayZurueck(): void {
        $result = $this->call('extractUrls', '', 'www.example.com');
        $this->assertSame([], $result);
    }

    #[Test]
    public function extractUrls_keineLocTags_gibtLeeresArrayZurueck(): void {
        $xml    = '<urlset><url><changefreq>daily</changefreq></url></urlset>';
        $result = $this->call('extractUrls', $xml, 'www.example.com');
        $this->assertSame([], $result);
    }

    #[Test]
    public function extractUrls_eineGueltigeUrl_wirdZurueckgegeben(): void {
        $xml = '<urlset><url><loc>https://www.example.com/seite/</loc></url></urlset>';
        $result = $this->call('extractUrls', $xml, 'www.example.com');
        $this->assertSame(['https://www.example.com/seite/'], $result);
    }

    #[Test]
    public function extractUrls_fremderHost_wirdGefiltert(): void {
        $xml = '<urlset>'
             . '<url><loc>https://www.example.com/seite/</loc></url>'
             . '<url><loc>https://www.fremd.de/seite/</loc></url>'
             . '</urlset>';
        $result = $this->call('extractUrls', $xml, 'www.example.com');
        $this->assertSame(['https://www.example.com/seite/'], $result);
    }

    #[Test]
    public function extractUrls_duplikate_werdenEntfernt(): void {
        $xml = '<urlset>'
             . '<url><loc>https://www.example.com/seite/</loc></url>'
             . '<url><loc>https://www.example.com/seite/</loc></url>'
             . '</urlset>';
        $result = $this->call('extractUrls', $xml, 'www.example.com');
        $this->assertCount(1, $result);
        $this->assertSame('https://www.example.com/seite/', $result[0]);
    }

    #[Test]
    public function extractUrls_ungueltigeUrl_wirdGefiltert(): void {
        $xml = '<urlset>'
             . '<url><loc>kein-url</loc></url>'
             . '<url><loc>https://www.example.com/gueltig/</loc></url>'
             . '</urlset>';
        $result = $this->call('extractUrls', $xml, 'www.example.com');
        $this->assertSame(['https://www.example.com/gueltig/'], $result);
    }

    #[Test]
    public function extractUrls_nichtHttpSchema_wirdGefiltert(): void {
        $xml = '<urlset>'
             . '<url><loc>ftp://www.example.com/seite/</loc></url>'
             . '<url><loc>https://www.example.com/gueltig/</loc></url>'
             . '</urlset>';
        $result = $this->call('extractUrls', $xml, 'www.example.com');
        $this->assertSame(['https://www.example.com/gueltig/'], $result);
    }

    #[Test]
    public function extractUrls_leerzeichenUmLoc_werdenGetrimmt(): void {
        $xml = '<urlset><url><loc>  https://www.example.com/seite/  </loc></url></urlset>';
        $result = $this->call('extractUrls', $xml, 'www.example.com');
        $this->assertSame(['https://www.example.com/seite/'], $result);
    }

    #[Test]
    public function extractUrls_hostVergleichCaseInsensitive(): void {
        $xml = '<urlset><url><loc>https://WWW.EXAMPLE.COM/seite/</loc></url></urlset>';
        $result = $this->call('extractUrls', $xml, 'www.example.com');
        $this->assertSame(['https://WWW.EXAMPLE.COM/seite/'], $result);
    }

    #[Test]
    public function extractUrls_mehrereGueltigeUrls_alleZurueckgegeben(): void {
        $xml = '<urlset>'
             . '<url><loc>https://www.example.com/</loc></url>'
             . '<url><loc>https://www.example.com/kontakt/</loc></url>'
             . '<url><loc>https://www.example.com/leistungen/</loc></url>'
             . '</urlset>';
        $result = $this->call('extractUrls', $xml, 'www.example.com');
        $this->assertCount(3, $result);
    }

    // -----------------------------------------------------------------------
    // parseHttpStatus
    // -----------------------------------------------------------------------

    #[Test]
    public function parseHttpStatus_leereHeaders_gibtNullZurueck(): void {
        $result = $this->call('parseHttpStatus', []);
        $this->assertSame(0, $result);
    }

    #[Test]
    public function parseHttpStatus_http11_200(): void {
        $result = $this->call('parseHttpStatus', ['HTTP/1.1 200 OK']);
        $this->assertSame(200, $result);
    }

    #[Test]
    public function parseHttpStatus_http2_202(): void {
        $result = $this->call('parseHttpStatus', ['HTTP/2 202 Accepted']);
        $this->assertSame(202, $result);
    }

    #[Test]
    public function parseHttpStatus_http10_404(): void {
        $result = $this->call('parseHttpStatus', ['HTTP/1.0 404 Not Found']);
        $this->assertSame(404, $result);
    }

    #[Test]
    public function parseHttpStatus_statusNichtErsteZeile_wirdTrotzdemGefunden(): void {
        $headers = [
            'Content-Type: application/json',
            'HTTP/1.1 429 Too Many Requests',
        ];
        $result = $this->call('parseHttpStatus', $headers);
        $this->assertSame(429, $result);
    }

    #[Test]
    public function parseHttpStatus_keineHttpZeile_gibtNullZurueck(): void {
        $result = $this->call('parseHttpStatus', ['Content-Type: text/html']);
        $this->assertSame(0, $result);
    }

    // -----------------------------------------------------------------------
    // interpretIndexNowResponse
    // -----------------------------------------------------------------------

    #[Test]
    public function interpretIndexNowResponse_200_erfolgsmeldung(): void {
        $result = $this->call('interpretIndexNowResponse', 200, 18, 'www.example.com', 'abc123');
        $this->assertStringContainsString('Erfolgreich', $result);
        $this->assertStringContainsString('18', $result);
        $this->assertStringContainsString('200', $result);
    }

    #[Test]
    public function interpretIndexNowResponse_202_akzeptiertMeldung(): void {
        $result = $this->call('interpretIndexNowResponse', 202, 5, 'www.example.com', 'abc123');
        $this->assertStringContainsString('Akzeptiert', $result);
        $this->assertStringContainsString('202', $result);
    }

    #[Test]
    public function interpretIndexNowResponse_400_fehlermeldung(): void {
        $result = $this->call('interpretIndexNowResponse', 400, 0, 'www.example.com', 'abc123');
        $this->assertStringContainsString('Fehler', $result);
        $this->assertStringContainsString('400', $result);
    }

    #[Test]
    public function interpretIndexNowResponse_403_enthaeltKeyDateiUrl(): void {
        $result = $this->call('interpretIndexNowResponse', 403, 0, 'www.example.com', 'abc123');
        $this->assertStringContainsString('Fehler', $result);
        $this->assertStringContainsString('403', $result);
        $this->assertStringContainsString('www.example.com/abc123.txt', $result);
    }

    #[Test]
    public function interpretIndexNowResponse_422_fehlermeldung(): void {
        $result = $this->call('interpretIndexNowResponse', 422, 0, 'www.example.com', 'abc123');
        $this->assertStringContainsString('Fehler', $result);
        $this->assertStringContainsString('422', $result);
    }

    #[Test]
    public function interpretIndexNowResponse_429_rateLimitMeldung(): void {
        $result = $this->call('interpretIndexNowResponse', 429, 0, 'www.example.com', 'abc123');
        $this->assertStringContainsString('Fehler', $result);
        $this->assertStringContainsString('429', $result);
    }

    #[Test]
    public function interpretIndexNowResponse_0_keineAntwortMeldung(): void {
        $result = $this->call('interpretIndexNowResponse', 0, 0, 'www.example.com', 'abc123');
        $this->assertStringContainsString('Fehler', $result);
        $this->assertStringContainsString('Keine Antwort', $result);
    }

    #[Test]
    public function interpretIndexNowResponse_unbekannterStatus_fehlermeldungMitStatuscode(): void {
        $result = $this->call('interpretIndexNowResponse', 503, 0, 'www.example.com', 'abc123');
        $this->assertStringContainsString('Fehler', $result);
        $this->assertStringContainsString('503', $result);
    }

    // -----------------------------------------------------------------------
    // getEffectiveEndpoint
    // -----------------------------------------------------------------------

    #[Test]
    public function getEffectiveEndpoint_leereSetting_gibtDefaultZurueck(): void {
        $result = $this->call('getEffectiveEndpoint');
        $this->assertSame(indexnow::DEFAULT_ENDPOINT, $result);
    }

    #[Test]
    public function getEffectiveEndpoint_konfigurierterWert_wirdZurueckgegeben(): void {
        $this->setSetting('endpoint', 'https://www.bing.com/indexnow');
        $result = $this->call('getEffectiveEndpoint');
        $this->assertSame('https://www.bing.com/indexnow', $result);
    }

    // -----------------------------------------------------------------------
    // getEffectiveHost
    // -----------------------------------------------------------------------

    #[Test]
    public function getEffectiveHost_konfigurierterHost_wirdZurueckgegeben(): void {
        $this->setSetting('host', 'www.example.com');
        $result = $this->call('getEffectiveHost');
        $this->assertSame('www.example.com', $result);
    }

    #[Test]
    public function getEffectiveHost_hostMitPfad_nurHostTeilWirdZurueckgegeben(): void {
        $this->setSetting('host', 'localhost/stb-hader');
        $result = $this->call('getEffectiveHost');
        $this->assertSame('localhost', $result);
    }

    #[Test]
    public function getEffectiveHost_leereSetting_autoDetectViaHttpHost(): void {
        $_SERVER['HTTP_HOST'] = 'www.example.com';
        $result = $this->call('getEffectiveHost');
        $this->assertSame('www.example.com', $result);
    }

    #[Test]
    public function getEffectiveHost_leereSetting_ungueltigerHttpHost_gibtLeerStringZurueck(): void {
        $_SERVER['HTTP_HOST'] = 'invalid host/with path';
        $result = $this->call('getEffectiveHost');
        $this->assertSame('', $result);
    }

    #[Test]
    public function getEffectiveHost_leereSetting_keinHttpHost_gibtLeerStringZurueck(): void {
        unset($_SERVER['HTTP_HOST']);
        $result = $this->call('getEffectiveHost');
        $this->assertSame('', $result);
    }

    // -----------------------------------------------------------------------
    // getEffectiveSitemapUrl
    // -----------------------------------------------------------------------

    #[Test]
    public function getEffectiveSitemapUrl_konfigurierteUrl_wirdZurueckgegeben(): void {
        $this->setSetting('sitemap_url', 'https://www.example.com/sitemap.xml');
        $result = $this->call('getEffectiveSitemapUrl');
        $this->assertSame('https://www.example.com/sitemap.xml', $result);
    }

    #[Test]
    public function getEffectiveSitemapUrl_leereSetting_wirdAusHostAbgeleitet(): void {
        $this->setSetting('host', 'www.example.com');
        $_SERVER['HTTPS'] = 'on';
        $result = $this->call('getEffectiveSitemapUrl');
        $this->assertSame('https://www.example.com/sitemap.xml', $result);
    }

    #[Test]
    public function getEffectiveSitemapUrl_ohneHttps_verwendetHttp(): void {
        $this->setSetting('host', 'www.example.com');
        unset($_SERVER['HTTPS']);
        $result = $this->call('getEffectiveSitemapUrl');
        $this->assertSame('http://www.example.com/sitemap.xml', $result);
    }

    #[Test]
    public function getEffectiveSitemapUrl_keinHost_gibtLeerStringZurueck(): void {
        unset($_SERVER['HTTP_HOST']);
        $result = $this->call('getEffectiveSitemapUrl');
        $this->assertSame('', $result);
    }

    // -----------------------------------------------------------------------
    // buildDebugOutput
    // -----------------------------------------------------------------------

    #[Test]
    public function buildDebugOutput_enthaeltVersion(): void {
        $result = $this->call('buildDebugOutput', 'www.example.com', 'abc123',
            'https://api.indexnow.org/indexnow', ['https://www.example.com/']);
        $this->assertStringContainsString(indexnow::VERSION, $result);
    }

    #[Test]
    public function buildDebugOutput_enthaeltEndpunkt(): void {
        $result = $this->call('buildDebugOutput', 'www.example.com', 'abc123',
            'https://api.indexnow.org/indexnow', ['https://www.example.com/']);
        $this->assertStringContainsString('https://api.indexnow.org/indexnow', $result);
    }

    #[Test]
    public function buildDebugOutput_enthaeltHost(): void {
        $result = $this->call('buildDebugOutput', 'www.example.com', 'abc123',
            'https://api.indexnow.org/indexnow', ['https://www.example.com/']);
        $this->assertStringContainsString('www.example.com', $result);
    }

    #[Test]
    public function buildDebugOutput_enthaeltUrlAnzahl(): void {
        $urls   = ['https://www.example.com/', 'https://www.example.com/kontakt/'];
        $result = $this->call('buildDebugOutput', 'www.example.com', 'abc123',
            'https://api.indexnow.org/indexnow', $urls);
        $this->assertStringContainsString('2 gefunden', $result);
    }

    #[Test]
    public function buildDebugOutput_enthaeltAlleUrls(): void {
        $urls   = ['https://www.example.com/', 'https://www.example.com/kontakt/'];
        $result = $this->call('buildDebugOutput', 'www.example.com', 'abc123',
            'https://api.indexnow.org/indexnow', $urls);
        foreach ($urls as $url) {
            $this->assertStringContainsString($url, $result);
        }
    }

    #[Test]
    public function buildDebugOutput_enthaeltGueltigesJson(): void {
        $urls   = ['https://www.example.com/'];
        $result = $this->call('buildDebugOutput', 'www.example.com', 'abc123',
            'https://api.indexnow.org/indexnow', $urls);

        // JSON-Block aus der Ausgabe extrahieren
        $jsonStart = strpos($result, '{');
        $this->assertNotFalse($jsonStart, 'Kein JSON-Block in der Debug-Ausgabe gefunden');
        $json   = substr($result, $jsonStart);
        $parsed = json_decode($json, true);
        $this->assertIsArray($parsed);
        $this->assertSame('www.example.com', $parsed['host']);
        $this->assertSame('abc123', $parsed['key']);
        $this->assertContains('https://www.example.com/', $parsed['urlList']);
    }

    #[Test]
    public function buildDebugOutput_keyDateiUrlKorrekt(): void {
        $result = $this->call('buildDebugOutput', 'www.example.com', 'abc123',
            'https://api.indexnow.org/indexnow', ['https://www.example.com/']);
        $this->assertStringContainsString('www.example.com/abc123.txt', $result);
    }

    // -----------------------------------------------------------------------
    // isDebugMode
    // -----------------------------------------------------------------------

    #[Test]
    public function isDebugMode_nichtGesetzt_gibtFalseZurueck(): void {
        $result = $this->call('isDebugMode');
        $this->assertFalse($result);
    }

    #[Test]
    public function isDebugMode_gesetzt_gibtTrueZurueck(): void {
        $this->setSetting('debug_mode', 'true');
        $result = $this->call('isDebugMode');
        $this->assertTrue($result);
    }

    // -----------------------------------------------------------------------
    // buildPayload
    // -----------------------------------------------------------------------

    #[Test]
    public function buildPayload_gibtKorrekteStrukturZurueck(): void {
        $urls   = ['https://www.example.com/', 'https://www.example.com/kontakt/'];
        $result = $this->call('buildPayload', 'www.example.com', 'abc123', $urls);

        $this->assertSame('www.example.com',                      $result['host']);
        $this->assertSame('abc123',                               $result['key']);
        $this->assertSame('https://www.example.com/abc123.txt',   $result['keyLocation']);
        $this->assertSame($urls,                                  $result['urlList']);
    }

    #[Test]
    public function buildPayload_keyLocationAusHostUndKeyKombiniert(): void {
        $result = $this->call('buildPayload', 'www.example.com', 'meinkey123', []);
        $this->assertSame('https://www.example.com/meinkey123.txt', $result['keyLocation']);
    }

    // -----------------------------------------------------------------------
    // buildWarnings
    // -----------------------------------------------------------------------

    #[Test]
    public function buildWarnings_allesFehlt_zeigtDreiWarnungen(): void {
        $result = $this->call('buildWarnings', '', '', '', false);
        $this->assertStringContainsString('API-Key', $result);
        $this->assertStringContainsString('Host', $result);
        $this->assertStringContainsString('Sitemap', $result);
    }

    #[Test]
    public function buildWarnings_alleKonfiguriert_keinWarnung(): void {
        $result = $this->call('buildWarnings', 'abc123', 'www.example.com', 'https://www.example.com/sitemap.xml', false);
        $this->assertStringNotContainsString('in-warning', $result);
    }

    #[Test]
    public function buildWarnings_debugAktiv_zeigtDebugBanner(): void {
        $result = $this->call('buildWarnings', 'abc123', 'www.example.com', 'https://www.example.com/sitemap.xml', true);
        $this->assertStringContainsString('Debug-Modus', $result);
        $this->assertStringContainsString('in-debug', $result);
    }

    #[Test]
    public function buildWarnings_apiKeyUndHostGesetzt_zeigtKeyDateiHinweis(): void {
        $result = $this->call('buildWarnings', 'abc123', 'www.example.com', 'https://www.example.com/sitemap.xml', false);
        $this->assertStringContainsString('www.example.com/abc123.txt', $result);
        $this->assertStringContainsString('in-hint', $result);
    }

    #[Test]
    public function buildWarnings_nurApiKeyFehlt_nurApiKeyWarnung(): void {
        $result = $this->call('buildWarnings', '', 'www.example.com', 'https://www.example.com/sitemap.xml', false);
        $this->assertStringContainsString('API-Key', $result);
        $this->assertStringNotContainsString('Host konnte', $result);
        $this->assertStringNotContainsString('Sitemap-URL', $result);
    }

    // -----------------------------------------------------------------------
    // buildConfigTable
    // -----------------------------------------------------------------------

    #[Test]
    public function buildConfigTable_enthaeltAlleWerte(): void {
        $result = $this->call('buildConfigTable', 'www.example.com', 'https://www.example.com/sitemap.xml', 'https://api.indexnow.org/indexnow');
        $this->assertStringContainsString('www.example.com', $result);
        $this->assertStringContainsString('https://www.example.com/sitemap.xml', $result);
        $this->assertStringContainsString('https://api.indexnow.org/indexnow', $result);
    }

    #[Test]
    public function buildConfigTable_leereSetting_zeigtAutoErkannt(): void {
        $result = $this->call('buildConfigTable', 'www.example.com', 'https://www.example.com/sitemap.xml', 'https://api.indexnow.org/indexnow');
        $this->assertStringContainsString('auto-erkannt', $result);
        $this->assertStringContainsString('abgeleitet', $result);
        $this->assertStringContainsString('Standard', $result);
    }

    #[Test]
    public function buildConfigTable_konfigurierterHost_zeigtKonfiguriert(): void {
        $this->setSetting('host', 'www.example.com');
        $result = $this->call('buildConfigTable', 'www.example.com', 'https://www.example.com/sitemap.xml', 'https://api.indexnow.org/indexnow');
        $this->assertStringContainsString('konfiguriert', $result);
    }

    #[Test]
    public function buildConfigTable_sonderzeichenWerdenEscaped(): void {
        $result = $this->call('buildConfigTable', 'www.example.com', 'https://www.example.com/sitemap.xml', 'https://api.indexnow.org/indexnow');
        $this->assertStringNotContainsString('<script>', $result);
    }

    // -----------------------------------------------------------------------
    // buildResultHtml
    // -----------------------------------------------------------------------

    #[Test]
    public function buildResultHtml_leeresErgebnis_gibtLeerStringZurueck(): void {
        $result = $this->call('buildResultHtml', '');
        $this->assertSame('', $result);
    }

    #[Test]
    public function buildResultHtml_erfolg_gibtSuccessNoticeZurueck(): void {
        $result = $this->call('buildResultHtml', '✓ Erfolgreich: 18 URL(s) übermittelt.');
        $this->assertStringContainsString('in-success', $result);
        $this->assertStringNotContainsString('in-error', $result);
    }

    #[Test]
    public function buildResultHtml_fehler_gibtErrorNoticeZurueck(): void {
        $result = $this->call('buildResultHtml', 'Fehler: API-Key nicht konfiguriert.');
        $this->assertStringContainsString('in-error', $result);
        $this->assertStringNotContainsString('in-success', $result);
    }

    #[Test]
    public function buildResultHtml_inhaltWirdEscaped(): void {
        $result = $this->call('buildResultHtml', 'Fehler: <script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    // -----------------------------------------------------------------------
    // validateSettings
    // -----------------------------------------------------------------------

    #[Test]
    public function validateSettings_alleGueltig_gibtNullZurueck(): void {
        $result = $this->call('validateSettings',
            'abc12345', 'www.example.com',
            'https://www.example.com/sitemap.xml',
            'https://api.indexnow.org/indexnow'
        );
        $this->assertNull($result);
    }

    #[Test]
    public function validateSettings_apiKeyLeer_fehlermeldung(): void {
        $result = $this->call('validateSettings', '', 'www.example.com',
            'https://www.example.com/sitemap.xml', 'https://api.indexnow.org/indexnow');
        $this->assertStringContainsString('API-Key', $result);
    }

    #[Test]
    public function validateSettings_apiKeyUngueltig_fehlermeldung(): void {
        $result = $this->call('validateSettings', 'kurz', 'www.example.com',
            'https://www.example.com/sitemap.xml', 'https://api.indexnow.org/indexnow');
        $this->assertStringContainsString('Fehler', $result);
    }

    #[Test]
    public function validateSettings_hostLeer_fehlermeldung(): void {
        $result = $this->call('validateSettings', 'abc12345', '',
            'https://www.example.com/sitemap.xml', 'https://api.indexnow.org/indexnow');
        $this->assertStringContainsString('Host', $result);
    }

    #[Test]
    public function validateSettings_sitemapLeer_fehlermeldung(): void {
        $result = $this->call('validateSettings', 'abc12345', 'www.example.com',
            '', 'https://api.indexnow.org/indexnow');
        $this->assertStringContainsString('Sitemap', $result);
    }

    #[Test]
    public function validateSettings_endpunktUngueltig_fehlermeldung(): void {
        $result = $this->call('validateSettings', 'abc12345', 'www.example.com',
            'https://www.example.com/sitemap.xml', 'kein-url');
        $this->assertStringContainsString('Endpunkt', $result);
    }

    // -----------------------------------------------------------------------
    // buildKeyFileHint
    // -----------------------------------------------------------------------

    #[Test]
    public function buildKeyFileHint_apiKeyUndHostGesetzt_gibtHintZurueck(): void {
        $result = $this->call('buildKeyFileHint', 'abc123', 'www.example.com');
        $this->assertStringContainsString('www.example.com/abc123.txt', $result);
        $this->assertStringContainsString('in-hint', $result);
    }

    #[Test]
    public function buildKeyFileHint_apiKeyLeer_gibtLeerStringZurueck(): void {
        $result = $this->call('buildKeyFileHint', '', 'www.example.com');
        $this->assertSame('', $result);
    }

    #[Test]
    public function buildKeyFileHint_hostLeer_gibtLeerStringZurueck(): void {
        $result = $this->call('buildKeyFileHint', 'abc123', '');
        $this->assertSame('', $result);
    }

    // -----------------------------------------------------------------------
    // getValueSource
    // -----------------------------------------------------------------------

    #[Test]
    public function getValueSource_leereSetting_gibtFallbackZurueck(): void {
        $result = $this->call('getValueSource', 'host', 'auto-erkannt');
        $this->assertSame('auto-erkannt', $result);
    }

    #[Test]
    public function getValueSource_konfigurierterWert_gibtKonfiguriert(): void {
        $this->setSetting('host', 'www.example.com');
        $result = $this->call('getValueSource', 'host', 'auto-erkannt');
        $this->assertSame('konfiguriert', $result);
    }

    // -----------------------------------------------------------------------
    // VERSION und DEFAULT_ENDPOINT Konstanten
    // -----------------------------------------------------------------------

    #[Test]
    public function versionKonstante_istDefiniert(): void {
        $this->assertNotEmpty(indexnow::VERSION);
        $this->assertStringStartsWith('v', indexnow::VERSION);
    }

    #[Test]
    public function defaultEndpointKonstante_istIndexNowOrg(): void {
        $this->assertSame('https://api.indexnow.org/indexnow', indexnow::DEFAULT_ENDPOINT);
    }
}
