<?php
/**
 * PHPUnit Bootstrap für das indexnow Plugin.
 *
 * Definiert alle nötigen CMS-Konstanten und Stub-Klassen damit
 * index.php geladen werden kann ohne eine echte moziloCMS-Instanz.
 */

// -----------------------------------------------------------------------
// CMS-Konstanten
// -----------------------------------------------------------------------

define('IS_CMS',          true);
define('BASE_DIR',        __DIR__ . '/../');
define('PLUGIN_DIR_NAME', 'plugins');
define('URL_BASE',        '/');

// -----------------------------------------------------------------------
// Properties-Stub
// Simuliert das moziloCMS Properties-Objekt (Schlüssel-Wert-Speicher).
// -----------------------------------------------------------------------

class Properties {

    private array $data = [];

    public function __construct(string $file = '') {
        // Im Test keine Datei lesen
    }

    public function get(string $key): ?string {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, string $value): void {
        $this->data[$key] = $value;
    }

    public function setFromArray(array $data): void {
        $this->data = array_merge($this->data, $data);
    }
}

// -----------------------------------------------------------------------
// Plugin-Basisklasse-Stub
// Ersetzt die abstrakte moziloCMS Plugin-Klasse.
// -----------------------------------------------------------------------

class Plugin {

    var $error    = null;
    var $settings;
    var $PLUGIN_SELF_DIR = '';
    var $PLUGIN_SELF_URL = '';

    function __construct() {
        $this->settings = new Properties();
    }

    function checkForMethod(string $method): void {}

    function makeUserParaArray(
        $value,
        $userparamarray    = false,
        $separation        = ',',
        $separation_key_value = '='
    ): array {
        return [];
    }
}

// -----------------------------------------------------------------------
// Plugin laden
// -----------------------------------------------------------------------

require_once __DIR__ . '/../indexnow/index.php';
