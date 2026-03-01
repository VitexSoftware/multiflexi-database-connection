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
    ];

    public function finalize(): void
    {
        $dbTypeField = $this->credential->getFields()->getFieldByCode('DB_TYPE');
        $dbHostField = $this->credential->getFields()->getFieldByCode('DB_HOST');
        $dbPortField = $this->credential->getFields()->getFieldByCode('DB_PORT');
        $dbNameField = $this->credential->getFields()->getFieldByCode('DB_NAME');
        $dbUserField = $this->credential->getFields()->getFieldByCode('DB_USER');
        $dbPasswordField = $this->credential->getFields()->getFieldByCode('DB_PASSWORD');

        $dbType = $dbTypeField ? $dbTypeField->getValue() : '';
        $dbHost = $dbHostField ? $dbHostField->getValue() : '';
        $dbPort = $dbPortField ? $dbPortField->getValue() : '';
        $dbName = $dbNameField ? $dbNameField->getValue() : '';
        $dbUser = $dbUserField ? $dbUserField->getValue() : '';
        $dbPassword = $dbPasswordField ? $dbPasswordField->getValue() : '';

        // Always show the DSN builder wizard
        $this->addItem($this->buildDsnWizard($dbType, $dbHost, $dbPort, $dbName, $dbUser));

        if (empty($dbType) || empty($dbHost) || empty($dbName)) {
            $missing = [];

            if (empty($dbType)) {
                $missing[] = 'DB_TYPE';
            }

            if (empty($dbHost)) {
                $missing[] = 'DB_HOST';
            }

            if (empty($dbName)) {
                $missing[] = 'DB_NAME';
            }

            $this->addItem(new \Ease\TWB4\Alert('danger', sprintf(
                _('Required fields not set: %s'),
                implode(', ', $missing),
            )));
            parent::finalize();

            return;
        }

        if (!\in_array($dbType, ['mysql', 'pgsql', 'sqlite'], true)) {
            $this->addItem(new \Ease\TWB4\Alert('danger', sprintf(
                _('Unsupported database type: %s. Supported: mysql, pgsql, sqlite'),
                $dbType,
            )));
            parent::finalize();

            return;
        }

        // Display parsed connection info
        $infoPanel = new \Ease\TWB4\Panel(_('Database Configuration'), 'default');
        $infoList = new \Ease\Html\DlTag(null, ['class' => 'row']);

        $infoList->addItem(new \Ease\Html\DtTag(_('Driver'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag($dbType, ['class' => 'col-sm-8']));

        if ($dbType !== 'sqlite') {
            $infoList->addItem(new \Ease\Html\DtTag(_('Host'), ['class' => 'col-sm-4']));
            $infoList->addItem(new \Ease\Html\DdTag($dbHost, ['class' => 'col-sm-8']));

            $effectivePort = !empty($dbPort) ? (int) $dbPort : (self::DRIVER_PORTS[$dbType] ?? 0);
            $infoList->addItem(new \Ease\Html\DtTag(_('Port'), ['class' => 'col-sm-4']));
            $infoList->addItem(new \Ease\Html\DdTag((string) $effectivePort, ['class' => 'col-sm-8']));
        }

        $infoList->addItem(new \Ease\Html\DtTag(_('Database'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag($dbName, ['class' => 'col-sm-8']));

        if (!empty($dbUser)) {
            $infoList->addItem(new \Ease\Html\DtTag(_('User'), ['class' => 'col-sm-4']));
            $infoList->addItem(new \Ease\Html\DdTag($dbUser, ['class' => 'col-sm-8']));
        }

        // Show composed PDO DSN
        $pdoDsn = self::buildPdoDsn($dbType, $dbHost, $dbPort, $dbName);
        $infoList->addItem(new \Ease\Html\DtTag(_('PDO DSN'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag(
            new \Ease\Html\SpanTag($pdoDsn, ['class' => 'font-monospace']),
            ['class' => 'col-sm-8'],
        ));

        $infoPanel->addItem($infoList);
        $this->addItem($infoPanel);

        // Test PDO connection
        $testResult = self::testConnection($dbType, $dbHost, $dbPort, $dbName, $dbUser, $dbPassword);

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
                    sprintf(_('Tables in %s (%d)'), $dbName, \count($testResult['tables'])),
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
            $this->addItem(new \Ease\TWB4\Alert('danger', sprintf(
                _('Database connection failed: %s'),
                $testResult['message'],
            )));
        }

        parent::finalize();
    }

    /**
     * Build the interactive DSN wizard panel.
     */
    private function buildDsnWizard(string $dbType, string $dbHost, string $dbPort, string $dbName, string $dbUser): \Ease\Html\DivTag
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
            new \Ease\Html\SelectTag('dbDriver_'.$wizardId, [
                'mysql' => 'MySQL / MariaDB',
                'pgsql' => 'PostgreSQL',
                'sqlite' => 'SQLite',
            ], $dbType ?: 'mysql', ['class' => 'form-select', 'id' => 'dbDriver_'.$wizardId]),
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
            new \Ease\Html\InputTextTag('dbUser_'.$wizardId, $dbUser, [
                'class' => 'form-control',
                'id' => 'dbUser_'.$wizardId,
                'placeholder' => 'dbuser',
            ]),
        ], ['class' => 'mb-3']));

        $body->addItem($netFields);

        // Database name / file path
        $body->addItem(new \Ease\Html\DivTag([
            new \Ease\Html\LabelTag('dbName_'.$wizardId, _('Database Name / File Path'), ['class' => 'form-label']),
            new \Ease\Html\InputTextTag('dbName_'.$wizardId, $dbName, [
                'class' => 'form-control',
                'id' => 'dbName_'.$wizardId,
                'placeholder' => 'mydatabase',
            ]),
        ], ['class' => 'mb-3']));

        // DSN Preview
        $currentDsn = !empty($dbType) ? self::buildPdoDsn($dbType, $dbHost, $dbPort, $dbName) : '';
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
    var preview = document.getElementById('dbDsnPreview_' + wid);
    var applyBtn = document.getElementById('dbApply_' + wid);

    var defaultPorts = {'mysql': '3306', 'pgsql': '5432'};

    function updatePreview() {
        var drv = driver.value;
        if (drv === 'sqlite') {
            netFields.style.display = 'none';
            preview.value = 'sqlite:' + (dbname.value.trim() || '/path/to/database.db');
            return;
        }
        netFields.style.display = '';
        var dsn = drv + ':host=' + (host.value.trim() || 'localhost');
        var p = port.value.trim();
        if (p) dsn += ';port=' + p;
        dsn += ';dbname=' + (dbname.value.trim() || 'mydatabase');
        preview.value = dsn;
    }

    function updatePortPlaceholder() {
        port.placeholder = defaultPorts[driver.value] || '';
    }

    driver.addEventListener('change', function() { updatePreview(); updatePortPlaceholder(); });
    host.addEventListener('input', updatePreview);
    port.addEventListener('input', updatePreview);
    dbname.addEventListener('input', updatePreview);

    applyBtn.addEventListener('click', function(e) {
        e.preventDefault();
        var fields = {
            'DB_TYPE': driver.value,
            'DB_HOST': host.value.trim(),
            'DB_PORT': port.value.trim(),
            'DB_NAME': dbname.value.trim(),
            'DB_USER': user.value.trim()
        };
        var applied = 0;
        for (var fieldName in fields) {
            var target = document.querySelector('input[name="' + fieldName + '"], input[id*="' + fieldName + '"]');
            if (!target) {
                var inputs = document.querySelectorAll('input');
                for (var i = 0; i < inputs.length; i++) {
                    if (inputs[i].name && inputs[i].name.indexOf(fieldName) !== -1) {
                        target = inputs[i];
                        break;
                    }
                }
            }
            if (target) {
                target.value = fields[fieldName];
                target.dispatchEvent(new Event('change', {bubbles: true}));
                applied++;
            }
        }
        if (applied > 0) {
            applyBtn.textContent = '\u2705 ' + applyBtn.textContent.replace(/^[\S]+ /, '');
            setTimeout(function() { applyBtn.textContent = '\uD83D\uDCCB ' + applyBtn.textContent.replace(/^[\S]+ /, ''); }, 2000);
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
    private static function buildPdoDsn(string $dbType, string $dbHost, string $dbPort, string $dbName): string
    {
        if ($dbType === 'sqlite') {
            return 'sqlite:'.$dbName;
        }

        $dsn = $dbType.':host='.$dbHost;

        if (!empty($dbPort)) {
            $dsn .= ';port='.$dbPort;
        }

        $dsn .= ';dbname='.$dbName;

        return $dsn;
    }

    /**
     * Test PDO database connection and gather server information.
     *
     * @return array{success: bool, message: string, server_info: array<string, string>, tables: array<string>}
     */
    private static function testConnection(string $dbType, string $dbHost, string $dbPort, string $dbName, string $dbUser, string $dbPassword): array
    {
        $dsn = self::buildPdoDsn($dbType, $dbHost, $dbPort, $dbName);

        try {
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 10,
            ];

            $pdo = new \PDO($dsn, $dbUser ?: null, $dbPassword ?: null, $options);

            $serverInfo = [];

            // Gather server version info
            try {
                $serverInfo[_('Server Version')] = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
            } catch (\PDOException $e) {
                // Some drivers may not support this attribute
            }

            try {
                $serverInfo[_('Client Version')] = $pdo->getAttribute(\PDO::ATTR_CLIENT_VERSION);
            } catch (\PDOException $e) {
            }

            try {
                $serverInfo[_('Connection Status')] = $pdo->getAttribute(\PDO::ATTR_CONNECTION_STATUS);
            } catch (\PDOException $e) {
            }

            $serverInfo[_('Driver')] = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

            // List tables
            $tables = [];

            try {
                switch ($dbType) {
                    case 'mysql':
                        $stmt = $pdo->query('SHOW TABLES');

                        break;

                    case 'pgsql':
                        $stmt = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");

                        break;

                    case 'sqlite':
                        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");

                        break;

                    default:
                        $stmt = null;
                }

                if ($stmt) {
                    $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                }
            } catch (\PDOException $e) {
                // Table listing failed, but connection itself succeeded
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
