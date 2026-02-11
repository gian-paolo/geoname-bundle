.PHONY: db-mariadb db-postgres db-stop test-mariadb test-postgres test-sqlite test-all

db-mariadb:
	./bin/db-manage.sh mariadb up -d

db-postgres:
	./bin/db-manage.sh postgres up -d

db-stop:
	./bin/db-manage.sh mariadb stop
	./bin/db-manage.sh postgres stop

test-mariadb:
	./bin/run-tests.sh mariadb

test-postgres:
	./bin/run-tests.sh postgres

test-sqlite:
	./bin/run-tests.sh sqlite

test-all: test-sqlite test-mariadb test-postgres
