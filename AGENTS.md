# AGENTS.md

## Project Overview

This project provides PDO database connection credential support for MultiFlexi as a separate Debian-packaged addon. It produces two binary packages from one source:

- **multiflexi-database-connection** — credential prototype for `php-vitexsoftware-multiflexi-core` (`MultiFlexi\CredentialProtoType\DatabaseConnection`)
- **multiflexi-database-connection-ui** — UI form helper for `multiflexi-web` (`MultiFlexi\Ui\CredentialType\DatabaseConnection`)

## Directory Structure

- `src/MultiFlexi/CredentialProtoType/DatabaseConnection.php` — core credential prototype class
- `src/MultiFlexi/Ui/CredentialType/DatabaseConnection.php` — web UI credential form helper
- `src/images/DatabaseConnection.svg` — logo asset
- `debian/` — Debian packaging
- `tests/` — PHPUnit tests

## Build & Test

```bash
make vendor    # install composer dependencies
make phpunit   # run tests
make cs        # fix coding standards
make deb       # build Debian packages
```

## Coding Standards

- PHP 8.1+ with strict types
- PSR-12 via ergebnis/php-cs-fixer-config
- Run `make cs` before committing

## Debian Packaging

The `debian/control` defines two binary packages with proper dependency chains:
- `multiflexi-database-connection` depends on `php-vitexsoftware-multiflexi-core` and `multiflexi-cli (>= 2.2.0)`
- `multiflexi-database-connection-ui` depends on `multiflexi-database-connection` and `multiflexi-web`

The `postinst` for `multiflexi-database-connection` runs `multiflexi-cli crprototype sync` to register the credential prototype.

## Key Classes

### MultiFlexi\CredentialProtoType\DatabaseConnection
Extends `\MultiFlexi\CredentialProtoType` and implements `\MultiFlexi\credentialTypeInterface`.
Defines fields: DB_TYPE, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD.

### MultiFlexi\Ui\CredentialType\DatabaseConnection
Extends `\MultiFlexi\Ui\CredentialFormHelperPrototype`.
Provides an interactive DSN builder wizard (JavaScript), tests PDO connectivity, displays server version/driver info, and lists database tables.

## Credential Fields Provided

The DatabaseConnection credential prototype (UUID `cd2b27a0-dc5a-45b4-a48e-e4f532260d52`) provides six environment variables to consuming applications:

- **`DB_TYPE`** — PDO driver name: `mysql`, `pgsql`, or `sqlite`
- **`DB_HOST`** — Database server hostname or IP address
- **`DB_PORT`** — Port number (empty = driver default: 3306 for MySQL, 5432 for PostgreSQL)
- **`DB_NAME`** — Database name (or file path for SQLite)
- **`DB_USER`** — Database authentication username
- **`DB_PASSWORD`** — Database authentication password

## Consumer Integration Guide

Applications consuming this credential type can construct a PDO DSN from the provided fields:

```php
// For MySQL/PostgreSQL:
$dsn = \Ease\Shared::cfg('DB_TYPE') . ':host=' . \Ease\Shared::cfg('DB_HOST')
     . ';port=' . \Ease\Shared::cfg('DB_PORT')
     . ';dbname=' . \Ease\Shared::cfg('DB_NAME');

// For SQLite:
$dsn = 'sqlite:' . \Ease\Shared::cfg('DB_NAME');

$pdo = new \PDO($dsn, \Ease\Shared::cfg('DB_USER'), \Ease\Shared::cfg('DB_PASSWORD'));
```
