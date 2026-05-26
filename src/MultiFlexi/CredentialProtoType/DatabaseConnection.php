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

namespace MultiFlexi\CredentialProtoType;

/**
 * Description of DatabaseConnection.
 *
 * author Vitex <info@vitexsoftware.cz>
 *
 * @no-named-arguments
 */
class DatabaseConnection extends \MultiFlexi\CredentialProtoType implements \MultiFlexi\credentialTypeInterface
{
    public static string $logo = 'DatabaseConnection.svg';

    public function __construct()
    {
        parent::__construct();

        $dbConnectionField = new \MultiFlexi\ConfigField('DB_CONNECTION', 'string', _('Database Connection'), _('PDO driver (mysql, pgsql, sqlsrv, sqlite)'));
        $dbConnectionField->setHint('mysql')->setValue('mysql');

        $dbHostField = new \MultiFlexi\ConfigField('DB_HOST', 'string', _('Database Host'), _('Hostname or IP address of the database server'));
        $dbHostField->setHint('localhost')->setValue('localhost');

        $dbPortField = new \MultiFlexi\ConfigField('DB_PORT', 'string', _('Database Port'), _('Port number (default: 3306 for MySQL, 5432 for PostgreSQL, 1433 for SQL Server)'));
        $dbPortField->setHint('3306')->setValue('');

        $dbDatabaseField = new \MultiFlexi\ConfigField('DB_DATABASE', 'string', _('Database Name'), _('Name of the database'));
        $dbDatabaseField->setHint('mydatabase')->setValue('');

        $dbUsernameField = new \MultiFlexi\ConfigField('DB_USERNAME', 'string', _('Database Username'), _('Username for database authentication'));
        $dbUsernameField->setHint('dbuser')->setValue('');

        $dbPasswordField = new \MultiFlexi\ConfigField('DB_PASSWORD', 'password', _('Database Password'), _('Password for database authentication'));
        $dbPasswordField->setHint('secret')->setValue('');

        $dbSettingsField = new \MultiFlexi\ConfigField('DB_SETTINGS', 'string', _('Database Settings'), _('Additional DSN settings (e.g. ;TrustServerCertificate=true)'));
        $dbSettingsField->setHint('')->setValue('');

        $this->configFieldsInternal->addField($dbConnectionField);
        $this->configFieldsInternal->addField($dbHostField);
        $this->configFieldsInternal->addField($dbPortField);
        $this->configFieldsInternal->addField($dbDatabaseField);
        $this->configFieldsInternal->addField($dbUsernameField);
        $this->configFieldsInternal->addField($dbPasswordField);
        $this->configFieldsInternal->addField($dbSettingsField);
    }

    public function load(int $credTypeId)
    {
        $loaded = parent::load($credTypeId);

        foreach ($this->configFieldsInternal->getFields() as $field) {
            $this->configFieldsProvided->addField($field);
        }

        return $loaded;
    }

    #[\Override]
    public function prepareConfigForm(): void
    {
    }

    public static function name(): string
    {
        return _('Database Connection (PDO)');
    }

    public static function description(): string
    {
        return _('PDO database connection credentials for MySQL, PostgreSQL, SQLite, SQL Server');
    }

    public static function uuid(): string
    {
        return 'cd2b27a0-dc5a-45b4-a48e-e4f532260d52';
    }

    #[\Override]
    public static function logo(): string
    {
        return self::$logo;
    }
}
