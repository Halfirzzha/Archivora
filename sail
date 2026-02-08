#!/usr/bin/env bash

# Archivora Docker Wrapper (Sail-compatible)
# Usage: ./sail <command>

COMPOSE_FILE="docker/docker-compose.yml"
CONTAINER="archivora_app"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if docker-compose file exists
if [ ! -f "$COMPOSE_FILE" ]; then
    echo -e "${RED}Error: $COMPOSE_FILE not found${NC}"
    exit 1
fi

# Function to check if containers are running
function is_running() {
    if docker ps --format '{{.Names}}' | grep -q "$CONTAINER"; then
        return 0
    else
        return 1
    fi
}

# If no arguments, show help
if [ $# -eq 0 ]; then
    echo -e "${GREEN}Archivora Docker Helper${NC}"
    echo ""
    echo "Usage:"
    echo "  ./sail up              Start all services (dev profile)"
    echo "  ./sail up -d           Start in background"
    echo "  ./sail down            Stop all services"
    echo "  ./sail ps              Show running containers"
    echo "  ./sail logs            Show logs"
    echo "  ./sail logs -f         Follow logs"
    echo "  ./sail shell           Access app container shell"
    echo "  ./sail artisan <cmd>   Run artisan command"
    echo "  ./sail composer <cmd>  Run composer command"
    echo "  ./sail npm <cmd>       Run npm command"
    echo "  ./sail test            Run PHPUnit tests"
    echo ""
    echo "Examples:"
    echo "  ./sail artisan migrate:fresh --seed"
    echo "  ./sail artisan make:filament-user"
    echo "  ./sail composer require package/name"
    echo "  ./sail npm run dev"
    exit 0
fi

# Handle commands
case "$1" in
    up)
        shift
        echo -e "${GREEN}Starting containers...${NC}"
        docker compose -f "$COMPOSE_FILE" --profile dev up "$@"
        ;;
    down)
        echo -e "${YELLOW}Stopping containers...${NC}"
        docker compose -f "$COMPOSE_FILE" --profile dev down
        ;;
    ps)
        docker compose -f "$COMPOSE_FILE" ps
        ;;
    logs)
        shift
        docker compose -f "$COMPOSE_FILE" logs "$@"
        ;;
    shell|bash|sh)
        if ! is_running; then
            echo -e "${RED}Containers are not running. Start them with: ./sail up -d${NC}"
            exit 1
        fi
        docker exec -it "$CONTAINER" sh
        ;;
    artisan|art)
        shift
        if ! is_running; then
            echo -e "${RED}Containers are not running. Start them with: ./sail up -d${NC}"
            exit 1
        fi
        docker exec -it "$CONTAINER" php artisan "$@"
        ;;
    composer)
        shift
        if ! is_running; then
            echo -e "${RED}Containers are not running. Start them with: ./sail up -d${NC}"
            exit 1
        fi
        docker exec "$CONTAINER" composer "$@"
        ;;
    npm)
        shift
        if ! is_running; then
            echo -e "${RED}Containers are not running. Start them with: ./sail up -d${NC}"
            exit 1
        fi
        docker exec "$CONTAINER" npm "$@"
        ;;
    test)
        shift
        if ! is_running; then
            echo -e "${RED}Containers are not running. Start them with: ./sail up -d${NC}"
            exit 1
        fi
        docker exec "$CONTAINER" php artisan test "$@"
        ;;
    tinker)
        if ! is_running; then
            echo -e "${RED}Containers are not running. Start them with: ./sail up -d${NC}"
            exit 1
        fi
        docker exec -it "$CONTAINER" php artisan tinker
        ;;
    *)
        # Pass through to docker-compose
        docker compose -f "$COMPOSE_FILE" "$@"
        ;;
esac
