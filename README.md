# multiflexi-database-connection

PDO database connection credential support for [MultiFlexi](https://multiflexi.eu).

## Description

This package provides database connection credential management for MultiFlexi, split into two Debian packages:

- `multiflexi-database-connection` — Credential prototype with driver, host, port, database, user and password fields (enhances `php-vitexsoftware-multiflexi-core`)
- `multiflexi-database-connection-ui` — Connection builder wizard, PDO test, server info and table listing (enhances `multiflexi-web`)

## Credential Fields

- **DB_TYPE** — PDO driver (`mysql`, `pgsql`, `sqlite`)
- **DB_HOST** — Database server hostname or IP
- **DB_PORT** — Port number (empty = driver default)
- **DB_NAME** — Database name or SQLite file path
- **DB_USER** — Database username
- **DB_PASSWORD** — Database password

## UI Features

The web interface component provides:
- Interactive DSN builder wizard with driver-specific fields
- Live PDO DSN preview
- PDO connection test with error reporting
- Server version and driver information display
- Database table listing

## Installation

### From Debian packages

```bash
apt install multiflexi-database-connection multiflexi-database-connection-ui
```

### From source (development)

```bash
composer install
make phpunit
make cs
```

## Building Debian Packages

```bash
make deb
```

This produces `multiflexi-database-connection_*.deb` and `multiflexi-database-connection-ui_*.deb` in the parent directory.

## License

MIT — see [debian/copyright](debian/copyright) for details.

## MultiFlexi

[![MultiFlexi](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg)](https://www.multiflexi.eu/)
