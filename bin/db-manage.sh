#!/bin/bash

# Simple script to manage Docker databases for the bundle
# Usage: ./bin/db-manage.sh [mariadb|postgres] [up|down|stop]

DB_TYPE=$1
ACTION=$2

if [ -z "$DB_TYPE" ] || [ -z "$ACTION" ]; then
    echo "Usage: $0 [mariadb|postgres] [up|down|stop]"
    exit 1
fi

COMPOSE_FILE="docker-compose.${DB_TYPE}.yml"

if [ ! -f "$COMPOSE_FILE" ]; then
    echo "Error: $COMPOSE_FILE not found."
    exit 1
fi

docker compose -f "$COMPOSE_FILE" "$ACTION"
