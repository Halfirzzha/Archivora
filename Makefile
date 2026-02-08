# =============================================================================
# PRODUCTION DOCKER MAKEFILE - Easy Management Commands
# Usage: make <command>
# =============================================================================

.PHONY: help build up down restart logs shell db-shell redis-shell clean backup restore health

# Default target
.DEFAULT_GOAL := help

# Docker Compose file
COMPOSE_FILE := docker/docker-compose.yml
COMPOSE := docker compose -f $(COMPOSE_FILE) --profile production

# Colors for output
COLOR_RESET := \033[0m
COLOR_INFO := \033[36m
COLOR_SUCCESS := \033[32m
COLOR_WARNING := \033[33m

## help: Show this help message
help:
	@echo "$(COLOR_INFO)=================================="
	@echo "  ARCHIVORA PRODUCTION COMMANDS"
	@echo "==================================$(COLOR_RESET)"
	@echo ""
	@grep -E '^## ' Makefile | sed 's/^## /  /'
	@echo ""

## build: Build production images (no cache)
build:
	@echo "$(COLOR_INFO)🏗️  Building production images...$(COLOR_RESET)"
	$(COMPOSE) build --no-cache

## up: Start all production services (detached)
up:
	@echo "$(COLOR_INFO)🚀 Starting production services...$(COLOR_RESET)"
	$(COMPOSE) up -d
	@echo "$(COLOR_SUCCESS)✅ Services started$(COLOR_RESET)"
	@$(MAKE) ps

## down: Stop all production services
down:
	@echo "$(COLOR_WARNING)🛑 Stopping production services...$(COLOR_RESET)"
	$(COMPOSE) down
	@echo "$(COLOR_SUCCESS)✅ Services stopped$(COLOR_RESET)"

## restart: Restart all production services
restart:
	@echo "$(COLOR_INFO)🔄 Restarting production services...$(COLOR_RESET)"
	$(COMPOSE) restart
	@echo "$(COLOR_SUCCESS)✅ Services restarted$(COLOR_RESET)"

## ps: Show running containers status
ps:
	@echo "$(COLOR_INFO)📊 Container Status:$(COLOR_RESET)"
	@$(COMPOSE) ps

## logs: Follow logs from all services
logs:
	$(COMPOSE) logs -f --tail=100

## logs-app: Follow logs from app service only
logs-app:
	$(COMPOSE) logs -f --tail=100 app

## logs-nginx: Follow logs from nginx service only
logs-nginx:
	$(COMPOSE) logs -f --tail=100 nginx

## logs-queue: Follow logs from queue service only
logs-queue:
	$(COMPOSE) logs -f --tail=100 queue

## shell: Open bash shell in app container
shell:
	@echo "$(COLOR_INFO)🐚 Opening shell in app container...$(COLOR_RESET)"
	docker exec -it archivora-app bash

## db-shell: Open PostgreSQL shell
db-shell:
	@echo "$(COLOR_INFO)🗄️  Opening PostgreSQL shell...$(COLOR_RESET)"
	@read -p "Database name (default: archivora_production): " DB_NAME; \
	DB_NAME=$${DB_NAME:-archivora_production}; \
	docker exec -it archivora-postgres psql -U archivora_user -d $$DB_NAME

## redis-shell: Open Redis CLI
redis-shell:
	@echo "$(COLOR_INFO)📦 Opening Redis CLI...$(COLOR_RESET)"
	docker exec -it archivora-redis redis-cli

## health: Check health status of all services
health:
	@echo "$(COLOR_INFO)🏥 Checking health status...$(COLOR_RESET)"
	@echo ""
	@echo "$(COLOR_INFO)Container Health:$(COLOR_RESET)"
	@$(COMPOSE) ps --format "table {{.Name}}\t{{.Status}}"
	@echo ""
	@echo "$(COLOR_INFO)Application Health Endpoint:$(COLOR_RESET)"
	@curl -s http://localhost/health | jq '.' || echo "$(COLOR_WARNING)⚠️  Health endpoint not responding$(COLOR_RESET)"
	@echo ""
	@echo "$(COLOR_INFO)Queue Workers Status:$(COLOR_RESET)"
	@docker exec archivora-queue supervisorctl status 2>/dev/null || echo "$(COLOR_WARNING)⚠️  Queue service not running$(COLOR_RESET)"
	@echo ""
	@echo "$(COLOR_INFO)Scheduler Status:$(COLOR_RESET)"
	@docker exec archivora-scheduler supervisorctl status 2>/dev/null || echo "$(COLOR_WARNING)⚠️  Scheduler service not running$(COLOR_RESET)"

## artisan: Run artisan command (usage: make artisan CMD="migrate")
artisan:
	@if [ -z "$(CMD)" ]; then \
		echo "$(COLOR_WARNING)⚠️  Usage: make artisan CMD=\"your-command\"$(COLOR_RESET)"; \
		exit 1; \
	fi
	@echo "$(COLOR_INFO)🎨 Running: php artisan $(CMD)$(COLOR_RESET)"
	docker exec archivora-app php artisan $(CMD)

## migrate: Run database migrations (force production)
migrate:
	@echo "$(COLOR_INFO)🗄️  Running database migrations...$(COLOR_RESET)"
	docker exec archivora-app php artisan migrate --force
	@echo "$(COLOR_SUCCESS)✅ Migrations complete$(COLOR_RESET)"

## optimize: Optimize Laravel (cache config, routes, views)
optimize:
	@echo "$(COLOR_INFO)⚡ Optimizing Laravel...$(COLOR_RESET)"
	docker exec archivora-app php artisan optimize:clear
	docker exec archivora-app php artisan config:cache
	docker exec archivora-app php artisan route:cache
	docker exec archivora-app php artisan view:cache
	docker exec archivora-app php artisan filament:cache-components || true
	@echo "$(COLOR_SUCCESS)✅ Optimization complete$(COLOR_RESET)"

## backup-db: Backup PostgreSQL database to backups/ directory
backup-db:
	@echo "$(COLOR_INFO)💾 Backing up database...$(COLOR_RESET)"
	@mkdir -p backups
	@BACKUP_FILE="backups/db-backup-$$(date +%Y%m%d-%H%M%S).sql.gz"; \
	docker exec archivora-postgres pg_dump -U archivora_user archivora_production | gzip > $$BACKUP_FILE; \
	echo "$(COLOR_SUCCESS)✅ Database backed up to $$BACKUP_FILE$(COLOR_RESET)"

## restore-db: Restore PostgreSQL database from backup file (usage: make restore-db FILE=backups/db-backup.sql.gz)
restore-db:
	@if [ -z "$(FILE)" ]; then \
		echo "$(COLOR_WARNING)⚠️  Usage: make restore-db FILE=backups/db-backup-YYYYMMDD-HHMMSS.sql.gz$(COLOR_RESET)"; \
		exit 1; \
	fi
	@echo "$(COLOR_WARNING)⚠️  WARNING: This will replace the current database!$(COLOR_RESET)"
	@read -p "Are you sure? (yes/no): " CONFIRM; \
	if [ "$$CONFIRM" = "yes" ]; then \
		echo "$(COLOR_INFO)📥 Restoring database from $(FILE)...$(COLOR_RESET)"; \
		gunzip -c $(FILE) | docker exec -i archivora-postgres psql -U archivora_user -d archivora_production; \
		echo "$(COLOR_SUCCESS)✅ Database restored$(COLOR_RESET)"; \
	else \
		echo "$(COLOR_INFO)Restore cancelled$(COLOR_RESET)"; \
	fi

## clean: Remove all containers, volumes, and orphans (DESTRUCTIVE!)
clean:
	@echo "$(COLOR_WARNING)⚠️  WARNING: This will remove all data!$(COLOR_RESET)"
	@read -p "Are you sure? (yes/no): " CONFIRM; \
	if [ "$$CONFIRM" = "yes" ]; then \
		echo "$(COLOR_WARNING)🧹 Cleaning up...$(COLOR_RESET)"; \
		$(COMPOSE) down -v --remove-orphans; \
		echo "$(COLOR_SUCCESS)✅ Cleanup complete$(COLOR_RESET)"; \
	else \
		echo "$(COLOR_INFO)Cleanup cancelled$(COLOR_RESET)"; \
	fi

## stats: Show real-time resource usage
stats:
	@echo "$(COLOR_INFO)📊 Resource Usage (press Ctrl+C to exit):$(COLOR_RESET)"
	docker stats

## prune: Remove unused Docker resources (images, volumes, networks)
prune:
	@echo "$(COLOR_WARNING)🧹 Pruning unused Docker resources...$(COLOR_RESET)"
	docker system prune -a --volumes
	@echo "$(COLOR_SUCCESS)✅ Prune complete$(COLOR_RESET)"

## deploy: Full deployment (build, up, migrate, optimize)
deploy:
	@echo "$(COLOR_INFO)🚀 Starting full deployment...$(COLOR_RESET)"
	@$(MAKE) build
	@$(MAKE) up
	@sleep 10
	@$(MAKE) migrate
	@$(MAKE) optimize
	@$(MAKE) health
	@echo "$(COLOR_SUCCESS)🎉 Deployment complete!$(COLOR_RESET)"
