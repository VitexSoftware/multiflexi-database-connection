<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MultiFlexi\Ui\CredentialType;

/**
 * Description of DatabaseConnection.
 *
 * @author Vitex <info@vitexsoftware.cz>
 */
class DatabaseConnection extends \MultiFlexi\Ui\CredentialFormHelperPrototype
{
    /**
     * Default ports for supported database drivers.
     */
    private const DRIVER_PORTS = [
        'mysql' => 3306,
        'pgsql' => 5432,
        'sqlite' => 0,
        'sqlsrv' => 1433,
    ];

    public function finalize(): void
    {
        $dbConnectionField = $this->credential->getFields()->getFieldByCode('DB_CONNECTION');
        $dbHostField = $this->credential->getFields()->getFieldByCode('DB_HOST');
        $dbPortField = $this->credential->getFields()->getFieldByCode('DB_PORT');
        $dbDatabaseField = $this->credential->getFields()->getFieldByCode('DB_DATABASE');
        $dbUsernameField = $this->credential->getFields()->getFieldByCode('DB_USERNAME');
        $dbPasswordField = $this->credential->getFields()->getFieldByCode('DB_PASSWORD');
        $dbSettingsField = $this->credential->getFields()->getFieldByCode('DB_SETTINGS');

        $dbConnection = $dbConnectionField ? $dbConnectionField->getValue() : '';
        $dbHost = $dbHostField ? $dbHostField->getValue() : '';
        $dbPort = $dbPortField ? $dbPortField->getValue() : '';
        $dbDatabase = $dbDatabaseField ? $dbDatabaseField->getValue() : '';
        $dbUsername = $dbUsernameField ? $dbUsernameField->getValue() : '';
        $dbPassword = $dbPasswordField ? $dbPasswordField->getValue() : '';
        $dbSettings = $dbSettingsField ? $dbSettingsField->getValue() : '';

        // Always show the DSN builder wizard
        $this->addItem($this->buildDsnWizard($dbConnection, $dbHost, $dbPort, $dbDatabase, $dbUsername, $dbSettings));

        if (empty($dbConnection) || empty($dbHost) || empty($dbDatabase)) {
            $missing = [];

            if (empty($dbConnection)) {
                $missing[] = 'DB_CONNECTION';
            }

            if (empty($dbHost)) {
                $missing[] = 'DB_HOST';
            }

            if (empty($dbDatabase)) {
                $missing[] = 'DB_DATABASE';
            }

            $this->addItem(new \Ease\TWB4\Alert('danger', sprintf(
                _('Required fields not set: %s'),
                implode(', ', $missing),
            )));
            parent::finalize();

            return;
        }

        if (!\in_array($dbConnection, ['mysql', 'pgsql', 'sqlite', 'sqlsrv'], true)) {
            $this->addItem(new \Ease\TWB4\Alert('danger', sprintf(
                _('Unsupported database driver: %s. Supported: mysql, pgsql, sqlite, sqlsrv'),
                $dbConnection,
            )));
            parent::finalize();

            return;
        }

        if (!\in_array($dbConnection, \PDO::getAvailableDrivers(), true)) {
            $this->addItem(new \Ease\TWB4\Alert('warning', sprintf(
                _('The "%s" PDO driver is not available on this server. Install the %s PHP extension to use this database type.'),
                $dbConnection,
                self::phpExtensionName($dbConnection),
            )));
            parent::finalize();

            return;
        }

        // Display parsed connection info
        $infoPanel = new \Ease\TWB4\Panel(_('Database Configuration'), 'default');
        $infoList = new \Ease\Html\DlTag(null, ['class' => 'row']);

        $infoList->addItem(new \Ease\Html\DtTag(_('Driver'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag($dbConnection, ['class' => 'col-sm-8']));

        if ($dbConnection !== 'sqlite') {
            $infoList->addItem(new \Ease\Html\DtTag(_('Host'), ['class' => 'col-sm-4']));
            $infoList->addItem(new \Ease\Html\DdTag($dbHost, ['class' => 'col-sm-8']));

            $effectivePort = !empty($dbPort) ? (int) $dbPort : (self::DRIVER_PORTS[$dbConnection] ?? 0);
            $infoList->addItem(new \Ease\Html\DtTag(_('Port'), ['class' => 'col-sm-4']));
            $infoList->addItem(new \Ease\Html\DdTag((string) $effectivePort, ['class' => 'col-sm-8']));
        }

        $infoList->addItem(new \Ease\Html\DtTag(_('Database'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag($dbDatabase, ['class' => 'col-sm-8']));

        if (!empty($dbUsername)) {
            $infoList->addItem(new \Ease\Html\DtTag(_('Username'), ['class' => 'col-sm-4']));
            $infoList->addItem(new \Ease\Html\DdTag($dbUsername, ['class' => 'col-sm-8']));
        }

        if (!empty($dbSettings)) {
            $infoList->addItem(new \Ease\Html\DtTag(_('Extra Settings'), ['class' => 'col-sm-4']));
            $infoList->addItem(new \Ease\Html\DdTag(
                new \Ease\Html\SpanTag($dbSettings, ['class' => 'font-monospace']),
                ['class' => 'col-sm-8'],
            ));
        }

        // Show composed PDO DSN
        $pdoDsn = self::buildPdoDsn($dbConnection, $dbHost, $dbPort, $dbDatabase, $dbSettings);
        $infoList->addItem(new \Ease\Html\DtTag(_('PDO DSN'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag(
            new \Ease\Html\SpanTag($pdoDsn, ['class' => 'font-monospace']),
            ['class' => 'col-sm-8'],
        ));

        $infoPanel->addItem($infoList);
        $this->addItem($infoPanel);

        // Test PDO connection
        $testResult = self::testConnection($dbConnection, $dbHost, $dbPort, $dbDatabase, $dbUsername, $dbPassword, $dbSettings);

        if ($testResult['success']) {
            $this->addItem(new \Ease\TWB4\Alert('success', sprintf(
                _('Database connection to %s successful'),
                $pdoDsn,
            )));

            // Display server info
            if (!empty($testResult['server_info'])) {
                $serverPanel = new \Ease\TWB4\Panel(_('Server Information'), 'info');
                $serverList = new \Ease\Html\DlTag(null, ['class' => 'row']);

                foreach ($testResult['server_info'] as $key => $value) {
                    $serverList->addItem(new \Ease\Html\DtTag($key, ['class' => 'col-sm-4']));
                    $serverList->addItem(new \Ease\Html\DdTag($value, ['class' => 'col-sm-8']));
                }

                $serverPanel->addItem($serverList);
                $this->addItem($serverPanel);
            }

            // Display tables if available
            if (!empty($testResult['tables'])) {
                $tablesPanel = new \Ease\TWB4\Panel(
                    sprintf(_('Tables in %s (%d)'), $dbDatabase, \count($testResult['tables'])),
                    'default',
                );
                $tableList = new \Ease\Html\UlTag(null, ['class' => 'list-group list-group-flush', 'style' => 'max-height: 300px; overflow-y: auto;']);

                foreach ($testResult['tables'] as $table) {
                    $tableList->addItem(new \Ease\Html\LiTag(
                        new \Ease\Html\SpanTag($table, ['class' => 'font-monospace']),
                        ['class' => 'list-group-item py-1'],
                    ));
                }

                $tablesPanel->addItem($tableList);
                $this->addItem($tablesPanel);
            }
        } else {
            $this->addItem(new \Ease\TWB4\Alert('danger', self::humanizeError(
                $dbConnection,
                $dbHost,
                $dbPort,
                $dbDatabase,
                $dbUsername,
                $testResult['message'],
            )));
        }

        parent::finalize();
    }

    /**
     * Return the Debian/Ubuntu PHP extension package name for a PDO driver.
     */
    private static function phpExtensionName(string $driver): string
    {
        return match ($driver) {
            'mysql'  => 'php-mysql',
            'pgsql'  => 'php-pgsql',
            'sqlite' => 'php-sqlite3',
            'sqlsrv' => 'php-sqlsrv',
            default  => 'php-'.$driver,
        };
    }

    /**
     * Build the driver select options, appending "(not available)" for missing PDO drivers.
     *
     * @return array<string, string>
     */
    private static function driverOptions(): array
    {
        $available = \PDO::getAvailableDrivers();
        $drivers = [
            'mysql'  => 'MySQL / MariaDB',
            'pgsql'  => 'PostgreSQL',
            'sqlite' => 'SQLite',
            'sqlsrv' => 'Microsoft SQL Server',
        ];

        foreach ($drivers as $code => $label) {
            if (!\in_array($code, $available, true)) {
                $drivers[$code] = sprintf(
                    _('%s (not available — install %s)'),
                    $label,
                    self::phpExtensionName($code),
                );
            }
        }

        return $drivers;
    }

    /**
     * Build the interactive DSN wizard panel.
     */
    private function buildDsnWizard(string $dbConnection, string $dbHost, string $dbPort, string $dbDatabase, string $dbUsername, string $dbSettings): \Ease\Html\DivTag
    {
        $wizardId = 'db-wizard-'.bin2hex(random_bytes(4));

        $wizard = new \Ease\Html\DivTag(null, ['id' => $wizardId, 'class' => 'card mb-3']);

        $header = new \Ease\Html\DivTag(
            new \Ease\Html\H5Tag('🗄 '._('Database Connection Builder')),
            ['class' => 'card-header bg-light'],
        );
        $wizard->addItem($header);

        $body = new \Ease\Html\DivTag(null, ['class' => 'card-body']);

        // Driver selector
        $body->addItem(new \Ease\Html\DivTag([
            new \Ease\Html\LabelTag('dbDriver_'.$wizardId, _('Database Driver'), ['class' => 'form-label fw-bold']),
            new \Ease\Html\SelectTag('dbDriver_'.$wizardId, self::driverOptions(), $dbConnection ?: 'mysql', ['class' => 'form-select', 'id' => 'dbDriver_'.$wizardId]),
        ], ['class' => 'mb-3']));

        // Network fields container (hidden for SQLite)
        $netFields = new \Ease\Html\DivTag(null, ['id' => 'dbNetFields_'.$wizardId]);

        // Host
        $netFields->addItem(new \Ease\Html\DivTag([
            new \Ease\Html\LabelTag('dbHost_'.$wizardId, _('Host'), ['class' => 'form-label']),
            new \Ease\Html\InputTextTag('dbHost_'.$wizardId, $dbHost ?: 'localhost', [
                'class' => 'form-control',
                'id' => 'dbHost_'.$wizardId,
                'placeholder' => 'localhost',
            ]),
        ], ['class' => 'mb-3']));

        // Port
        $netFields->addItem(new \Ease\Html\DivTag([
            new \Ease\Html\LabelTag('dbPort_'.$wizardId, _('Port (leave empty for default)'), ['class' => 'form-label']),
            new \Ease\Html\InputTextTag('dbPort_'.$wizardId, $dbPort, [
                'class' => 'form-control',
                'id' => 'dbPort_'.$wizardId,
                'placeholder' => '3306',
            ]),
        ], ['class' => 'mb-3']));

        // Username
        $netFields->addItem(new \Ease\Html\DivTag([
            new \Ease\Html\LabelTag('dbUser_'.$wizardId, _('Username'), ['class' => 'form-label']),
            new \Ease\Html\InputTextTag('dbUser_'.$wizardId, $dbUsername, [
                'class' => 'form-control',
                'id' => 'dbUser_'.$wizardId,
                'placeholder' => 'dbuser',
            ]),
        ], ['class' => 'mb-3']));

        $body->addItem($netFields);

        // Database name / file path
        $body->addItem(new \Ease\Html\DivTag([
            new \Ease\Html\LabelTag('dbName_'.$wizardId, _('Database Name / File Path'), ['class' => 'form-label']),
            new \Ease\Html\InputTextTag('dbName_'.$wizardId, $dbDatabase, [
                'class' => 'form-control',
                'id' => 'dbName_'.$wizardId,
                'placeholder' => 'mydatabase',
            ]),
        ], ['class' => 'mb-3']));

        // Extra settings
        $body->addItem(new \Ease\Html\DivTag([
            new \Ease\Html\LabelTag('dbSettings_'.$wizardId, _('Extra DSN Settings'), ['class' => 'form-label']),
            new \Ease\Html\InputTextTag('dbSettings_'.$wizardId, $dbSettings, [
                'class' => 'form-control font-monospace',
                'id' => 'dbSettings_'.$wizardId,
                'placeholder' => ';TrustServerCertificate=true',
            ]),
        ], ['class' => 'mb-3']));

        // DSN Preview
        $currentDsn = !empty($dbConnection) ? self::buildPdoDsn($dbConnection, $dbHost, $dbPort, $dbDatabase, $dbSettings) : '';
        $body->addItem(new \Ease\Html\DivTag([
            new \Ease\Html\LabelTag('dbDsnPreview_'.$wizardId, _('Composed PDO DSN'), ['class' => 'form-label fw-bold']),
            new \Ease\Html\InputTextTag('dbDsnPreview_'.$wizardId, $currentDsn, [
                'class' => 'form-control font-monospace bg-light',
                'id' => 'dbDsnPreview_'.$wizardId,
                'readonly' => 'readonly',
            ]),
        ], ['class' => 'mb-3']));

        // Apply button
        $body->addItem(new \Ease\Html\DivTag(
            new \Ease\Html\ATag('#', '📋 '._('Apply fields'), [
                'class' => 'btn btn-primary',
                'id' => 'dbApply_'.$wizardId,
            ]),
            ['class' => 'mb-2'],
        ));

        $wizard->addItem($body);

        // JavaScript for interactive wizard
        $js = <<<JS
(function(){
    var wid = '{$wizardId}';
    var driver = document.getElementById('dbDriver_' + wid);
    var netFields = document.getElementById('dbNetFields_' + wid);
    var host = document.getElementById('dbHost_' + wid);
    var port = document.getElementById('dbPort_' + wid);
    var user = document.getElementById('dbUser_' + wid);
    var dbname = document.getElementById('dbName_' + wid);
    var settings = document.getElementById('dbSettings_' + wid);
    var preview = document.getElementById('dbDsnPreview_' + wid);
    var applyBtn = document.getElementById('dbApply_' + wid);

    var defaultPorts = {'mysql': '3306', 'pgsql': '5432', 'sqlsrv': '1433'};

    function buildDsn() {
        var drv = driver.value;
        var h = host.value.trim() || 'localhost';
        var p = port.value.trim();
        var db = dbname.value.trim() || 'mydatabase';
        var extra = settings.value.trim();
        if (drv === 'sqlite') {
            return 'sqlite:' + (dbname.value.trim() || '/path/to/database.db');
        }
        if (drv === 'sqlsrv') {
            var srv = p ? h + ',' + p : h;
            return 'sqlsrv:Server=' + srv + ';Database=' + db + extra;
        }
        var dsn = drv + ':host=' + h;
        if (p) dsn += ';port=' + p;
        dsn += ';dbname=' + db + extra;
        return dsn;
    }

    function updatePreview() {
        var drv = driver.value;
        netFields.style.display = drv === 'sqlite' ? 'none' : '';
        preview.value = buildDsn();
    }

    function updatePortPlaceholder() {
        port.placeholder = defaultPorts[driver.value] || '';
    }

    driver.addEventListener('change', function() { updatePreview(); updatePortPlaceholder(); });
    host.addEventListener('input', updatePreview);
    port.addEventListener('input', updatePreview);
    dbname.addEventListener('input', updatePreview);
    settings.addEventListener('input', updatePreview);

    applyBtn.addEventListener('click', function(e) {
        e.preventDefault();
        var fields = {
            'DB_CONNECTION': driver.value,
            'DB_HOST': host.value.trim(),
            'DB_PORT': port.value.trim(),
            'DB_DATABASE': dbname.value.trim(),
            'DB_USERNAME': user.value.trim(),
            'DB_SETTINGS': settings.value.trim()
        };
        var applied = 0;
        for (var fieldName in fields) {
            var inputs = document.querySelectorAll('input[name], input[id]');
            for (var i = 0; i < inputs.length; i++) {
                var el = inputs[i];
                if ((el.name && el.name.indexOf(fieldName) !== -1) || (el.id && el.id.indexOf(fieldName) !== -1)) {
                    el.value = fields[fieldName];
                    el.dispatchEvent(new Event('change', {bubbles: true}));
                    applied++;
                    break;
                }
            }
        }
        if (applied > 0) {
            applyBtn.textContent = '✅ ' + applyBtn.textContent.replace(/^[\S]+ /, '');
            setTimeout(function() { applyBtn.textContent = '📋 ' + applyBtn.textContent.replace(/^[\S]+ /, ''); }, 2000);
        } else {
            alert('No matching input fields found on this page.');
        }
    });

    if (driver.value === 'sqlite') netFields.style.display = 'none';
    updatePortPlaceholder();
    if (!preview.value) updatePreview();
})();
JS;

        $wizard->addItem(new \Ease\Html\ScriptTag($js));

        return $wizard;
    }

    /**
     * Build a PDO DSN string from components.
     */
    private static function buildPdoDsn(string $dbConnection, string $dbHost, string $dbPort, string $dbDatabase, string $dbSettings = ''): string
    {
        if ($dbConnection === 'sqlite') {
            return 'sqlite:'.$dbDatabase;
        }

        if ($dbConnection === 'sqlsrv') {
            $server = !empty($dbPort) ? $dbHost.','.$dbPort : $dbHost;

            return 'sqlsrv:Server='.$server.';Database='.$dbDatabase.$dbSettings;
        }

        $dsn = $dbConnection.':host='.$dbHost;

        if (!empty($dbPort)) {
            $dsn .= ';port='.$dbPort;
        }

        $dsn .= ';dbname='.$dbDatabase.$dbSettings;

        return $dsn;
    }

    /**
     * Translate a raw PDOException message into a human-readable diagnostic.
     */
    private static function humanizeError(string $driver, string $host, string $port, string $database, string $username, string $rawMessage): string
    {
        $msg = strtolower($rawMessage);

        // Host / network unreachable
        if (
            str_contains($msg, 'connection refused')
            || str_contains($msg, 'no route to host')
            || str_contains($msg, 'connection timed out')
            || str_contains($msg, 'unable to connect')
            || str_contains($msg, 'network-related')
            || str_contains($msg, 'host not found')
            || str_contains($msg, 'name or service not known')
            || str_contains($msg, 'nodename nor servname provided')
        ) {
            $address = !empty($port) ? $host.':'.$port : $host;

            return sprintf(_('Cannot reach database server at %s. Check DB_HOST and DB_PORT, and ensure the server is running and network access is allowed.'), $address);
        }

        // Authentication failures
        if (
            str_contains($msg, 'access denied for user')       // MySQL
            || str_contains($msg, 'login failed for user')      // SQL Server
            || str_contains($msg, 'password authentication failed')  // PostgreSQL
            || str_contains($msg, 'fe_sendauth: no password supplied')
            || str_contains($msg, 'invalid username/password')
        ) {
            return sprintf(_('Authentication failed for user "%s". Check DB_USERNAME and DB_PASSWORD.'), $username);
        }

        // Wrong / missing database
        if (
            str_contains($msg, 'unknown database')             // MySQL
            || str_contains($msg, 'database "'.$database.'" does not exist')  // PostgreSQL
            || str_contains($msg, 'cannot open database')       // SQL Server
            || str_contains($msg, 'invalid catalog name')
        ) {
            return sprintf(_('Database "%s" does not exist or is not accessible to user "%s". Check DB_DATABASE.'), $database, $username);
        }

        // SSL / TLS / certificate issues
        if (
            str_contains($msg, 'ssl')
            || str_contains($msg, 'certificate')
            || str_contains($msg, 'tls')
            || str_contains($msg, 'encryption')
        ) {
            $hint = $driver === 'sqlsrv' ? _('Try adding ";TrustServerCertificate=true" to DB_SETTINGS.') : _('Check server SSL configuration.');

            return sprintf(_('SSL/TLS error: %s %s'), $rawMessage, $hint);
        }

        // Driver not available
        if (str_contains($msg, 'could not find driver') || str_contains($msg, 'driver not found')) {
            return sprintf(_('PDO driver for "%s" is not installed. Install the php-%s extension.'), $driver, $driver === 'sqlsrv' ? 'sqlsrv' : 'pdo-'.$driver);
        }

        return sprintf(_('Database connection failed: %s'), $rawMessage);
    }

    /**
     * Test PDO database connection and gather server information.
     *
     * @return array{success: bool, message: string, server_info: array<string, string>, tables: array<string>}
     */
    private static function testConnection(string $dbConnection, string $dbHost, string $dbPort, string $dbDatabase, string $dbUsername, string $dbPassword, string $dbSettings = ''): array
    {
        $dsn = self::buildPdoDsn($dbConnection, $dbHost, $dbPort, $dbDatabase, $dbSettings);

        try {
            $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];

            // sqlsrv does not support PDO::ATTR_TIMEOUT; use LoginTimeout in DSN instead
            if ($dbConnection !== 'sqlsrv') {
                $options[\PDO::ATTR_TIMEOUT] = 10;
            }

            $pdo = new \PDO($dsn, $dbUsername ?: null, $dbPassword ?: null, $options);

            $serverInfo = [];

            try {
                $serverInfo[_('Server Version')] = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
            } catch (\PDOException) {
            }

            try {
                $serverInfo[_('Client Version')] = $pdo->getAttribute(\PDO::ATTR_CLIENT_VERSION);
            } catch (\PDOException) {
            }

            try {
                $serverInfo[_('Connection Status')] = $pdo->getAttribute(\PDO::ATTR_CONNECTION_STATUS);
            } catch (\PDOException) {
            }

            $serverInfo[_('Driver')] = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

            $tables = [];

            try {
                switch ($dbConnection) {
                    case 'mysql':
                        $stmt = $pdo->query('SHOW TABLES');

                        break;

                    case 'pgsql':
                        $stmt = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");

                        break;

                    case 'sqlite':
                        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");

                        break;

                    case 'sqlsrv':
                        $stmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");

                        break;

                    default:
                        $stmt = null;
                }

                if ($stmt) {
                    $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                }
            } catch (\PDOException) {
                // Table listing is informational; don't fail the whole test
            }

            return [
                'success' => true,
                'message' => '',
                'server_info' => $serverInfo,
                'tables' => $tables,
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'server_info' => [],
                'tables' => [],
            ];
        }
    }
}
