#!/bin/bash

# Run tests against a specific database
# Usage: ./bin/run-tests.sh [mariadb|postgres|sqlite]

DB_TYPE=$1

case $DB_TYPE in
    mariadb)
        ./bin/db-manage.sh mariadb up -d
        export DATABASE_URL="mysql://user:password@127.0.0.1:3306/geoname_test?serverVersion=mariadb-11.8.0&charset=utf8mb4"
        ;;
    postgres)
        ./bin/db-manage.sh postgres up -d
        export DATABASE_URL="postgresql://user:password@127.0.0.1:5432/geoname_test?serverVersion=17&charset=utf8"
        ;;
    sqlite)
        export DATABASE_URL="sqlite:///%kernel.project_dir%/var/test.db"
        ;;
    *)
        echo "Usage: $0 [mariadb|postgres|sqlite]"
        exit 1
        ;;
esac

echo "Running tests for $DB_TYPE..."
php tests/App/bin/console doctrine:schema:update --force --env=test
vendor/bin/phpunit
