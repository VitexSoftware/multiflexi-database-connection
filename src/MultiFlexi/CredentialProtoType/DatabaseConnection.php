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

        $dbTypeField = new \MultiFlexi\ConfigField('DB_TYPE', 'string', _('Database Type'), _('PDO driver (mysql, pgsql, sqlite)'));
        $dbTypeField->setHint('mysql')->setValue('mysql');

        $dbHostField = new \MultiFlexi\ConfigField('DB_HOST', 'string', _('Database Host'), _('Hostname or IP address of the database server'));
        $dbHostField->setHint('localhost')->setValue('localhost');

        $dbPortField = new \MultiFlexi\ConfigField('DB_PORT', 'string', _('Database Port'), _('Port number (default: 3306 for MySQL, 5432 for PostgreSQL)'));
        $dbPortField->setHint('3306')->setValue('');

        $dbNameField = new \MultiFlexi\ConfigField('DB_NAME', 'string', _('Database Name'), _('Name of the database'));
        $dbNameField->setHint('mydatabase')->setValue('');

        $dbUserField = new \MultiFlexi\ConfigField('DB_USER', 'string', _('Database User'), _('Username for database authentication'));
        $dbUserField->setHint('dbuser')->setValue('');

        $dbPasswordField = new \MultiFlexi\ConfigField('DB_PASSWORD', 'password', _('Database Password'), _('Password for database authentication'));
        $dbPasswordField->setHint('secret')->setValue('');

        $this->configFieldsInternal->addField($dbTypeField);
        $this->configFieldsInternal->addField($dbHostField);
        $this->configFieldsInternal->addField($dbPortField);
        $this->configFieldsInternal->addField($dbNameField);
        $this->configFieldsInternal->addField($dbUserField);
        $this->configFieldsInternal->addField($dbPasswordField);
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
        return _('PDO database connection credentials for MySQL, PostgreSQL, SQLite');
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
