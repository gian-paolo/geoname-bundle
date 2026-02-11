# Contributing to GppGeonameBundle

First off, thank you for considering contributing to this bundle! 

## How to contribute

1. **Report Bugs**: Use GitHub Issues to report bugs. Be as specific as possible.
2. **Feature Requests**: If you have an idea for a new feature, open an issue to discuss it first.
3. **Pull Requests**:
    - Fork the repository.
    - Create a new branch (`git checkout -b feature/my-new-feature`).
    - Make your changes.
    - Ensure your code follows the PSR-12 coding standard.
    - Add tests if applicable.
    - Commit your changes and open a PR.

## Development Setup & Testing

To ensure the bundle works across different database engines, we use Docker for local testing.

### Test Databases

You can start the required databases using the provided `Makefile` or the management script:

- **MariaDB**: `make db-mariadb` (starts a container on port 3306)
- **PostgreSQL**: `make db-postgres` (starts a container on port 5432)
- **Stop all**: `make db-stop`

### Running Tests

The bundle uses PHPUnit for testing. You can run tests against different databases:

- **SQLite** (default, no setup required): `make test-sqlite`
- **MariaDB**: `make test-mariadb`
- **PostgreSQL**: `make test-postgres`
- **All at once**: `make test-all`

Alternatively, you can use the script directly: `./bin/run-tests.sh [mariadb|postgres|sqlite]`.
The script automatically handles schema updates and sets the correct `DATABASE_URL`.

## Coding Standards

This project follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards. Please ensure your contributions adhere to this.

## Branching Strategy

We use a standard branching model to keep the repository organized:

- **main**: Contains the stable, production-ready code. All releases are tagged from here.
- **develop**: The main integration branch for development.
- **feature/**: New features or enhancements should be developed in branches prefixed with `feature/` (e.g., `feature/add-alternate-names`).

To contribute a change:
1. Fork the repo and create your feature branch from `develop`.
2. Commit your changes.
3. Open a Pull Request targeting the `develop` branch.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
