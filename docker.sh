#!/usr/bin/env bash
# FleetOps Backend Docker Management Script

COMMAND=${1:-help}

show_help() {
    echo -e "\033[1;36mFleetOps Backend - Docker Commands\033[0m"
    echo ""
    echo -e "\033[1;33mUsage: ./docker.sh [command]\033[0m"
    echo ""
    echo -e "\033[1;32mCommands:\033[0m"
    echo "  install     - Build and start containers (first time setup)"
    echo "  up          - Start all containers"
    echo "  down        - Stop all containers"
    echo "  restart     - Restart all containers"
    echo "  rebuild     - Rebuild and start containers"
    echo "  logs        - Show logs from all containers"
    echo "  logs-app    - Show logs from app container"
    echo "  logs-db     - Show logs from database"
    echo "  shell       - Access app container shell"
    echo "  db          - Access SQL Server CLI"
    echo "  migrate     - Run database migrations"
    echo "  seed        - Seed the database with test data"
    echo "  fresh       - Fresh database with migrations and seeds"
    echo "  optimize    - Optimize Laravel caches"
    echo "  clear       - Clear all Laravel caches"
    echo "  test        - Run tests"
    echo "  status      - Show container status"
    echo "  health      - Check API health"
    echo "  clean       - Remove all containers and volumes"
    echo ""
}

install() {
    echo -e "\033[1;36mBuilding and starting FleetOps Backend...\033[0m"
    docker compose up -d --build
    
    echo -e "\033[1;33mWaiting for services to be ready...\033[0m"
    sleep 20
    
    echo -e "\033[1;33mEnsuring database exists...\033[0m"
    docker compose exec -T sqlserver /opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "Fleetops12345678!" -C -Q "IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'fleetops') CREATE DATABASE fleetops"
    
    echo -e "\033[1;33mRunning migrations...\033[0m"
    docker compose exec app php artisan migrate --force
    
    echo -e "\033[1;33mSeeding database with test data...\033[0m"
    docker compose exec app php artisan db:seed --force
    
    echo -e "\033[1;33mClearing and optimizing caches...\033[0m"
    docker compose exec app php artisan cache:clear
    docker compose exec app php artisan config:clear
    docker compose exec app php artisan config:cache
    docker compose exec app php artisan route:cache
    
    echo ""
    echo -e "\033[1;32mSetup complete!\033[0m"
    echo -e "\033[1;36mAPI available at: http://localhost:8000\033[0m"
    echo -e "\033[1;36mHealth check: http://localhost:8000/up\033[0m"
    echo -e "\033[1;36mLogin endpoint: http://localhost:8000/api/v1/auth/login\033[0m"
}

start_containers() {
    echo -e "\033[1;36mStarting containers...\033[0m"
    docker compose up -d
    echo -e "\033[1;32mContainers started!\033[0m"
}

stop_containers() {
    echo -e "\033[1;36mStopping containers...\033[0m"
    docker compose down
    echo -e "\033[1;32mContainers stopped!\033[0m"
}

restart_containers() {
    echo -e "\033[1;36mRestarting containers...\033[0m"
    docker compose restart
    echo -e "\033[1;32mContainers restarted!\033[0m"
}

rebuild_containers() {
    echo -e "\033[1;36mRebuilding and starting containers...\033[0m"
    docker compose up -d --build
    echo -e "\033[1;32mContainers rebuilt and started!\033[0m"
}

show_logs() {
    docker compose logs -f
}

show_applogs() {
    docker compose logs -f app
}

show_dblogs() {
    docker compose logs -f sqlserver
}

enter_shell() {
    docker compose exec app sh
}

enter_database() {
    docker compose exec sqlserver /opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "Fleetops12345678!" -C
}

run_migrations() {
    echo -e "\033[1;36mRunning migrations...\033[0m"
    docker compose exec app php artisan migrate --force
    echo -e "\033[1;32mMigrations complete!\033[0m"
}

seed_database() {
    echo -e "\033[1;36mSeeding database with test data...\033[0m"
    docker compose exec app php artisan db:seed --force
    echo -e "\033[1;32mDatabase seeded!\033[0m"
}

fresh_database() {
    echo -e "\033[1;36mRefreshing database with fresh migrations and seeds...\033[0m"
    docker compose exec app php artisan migrate:fresh --force
    docker compose exec app php artisan db:seed --force
    echo -e "\033[1;32mDatabase refreshed and seeded!\033[0m"
}

optimize_app() {
    echo -e "\033[1;36mOptimizing application...\033[0m"
    docker compose exec app php artisan config:cache
    docker compose exec app php artisan route:cache
    docker compose exec app php artisan view:cache
    echo -e "\033[1;32mOptimization complete!\033[0m"
}

clear_caches() {
    echo -e "\033[1;36mClearing caches...\033[0m"
    docker compose exec app php artisan cache:clear
    docker compose exec app php artisan config:clear
    docker compose exec app php artisan route:clear
    docker compose exec app php artisan view:clear
    echo -e "\033[1;32mCaches cleared!\033[0m"
}

run_tests() {
    echo -e "\033[1;36mRunning tests...\033[0m"
    docker compose exec app php artisan test
}

show_status() {
    docker compose ps
}

check_health() {
    echo -e "\033[1;36mChecking API health...\033[0m"
    if response=$(curl -s -w "\n%{http_code}" http://localhost:8000/up 2>/dev/null); then
        http_code=$(echo "$response" | tail -n 1)
        if [ "$http_code" = "200" ]; then
            echo -e "\033[1;32m✅ API is healthy!\033[0m"
            echo -e "\033[1;32mStatus Code: $http_code\033[0m"
        else
            echo -e "\033[1;31m❌ API is not responding properly\033[0m"
            echo -e "\033[1;31mStatus Code: $http_code\033[0m"
        fi
    else
        echo -e "\033[1;31m❌ API is not responding properly\033[0m"
        echo -e "\033[1;31mError: Connection failed\033[0m"
    fi
}

clean_all() {
    echo -e "\033[1;31mWARNING: This will remove all containers and volumes!\033[0m"
    read -p "Are you sure? (yes/no) " confirm
    if [ "$confirm" = "yes" ]; then
        docker compose down -v
        echo -e "\033[1;36mRemoving fleetops-backend image...\033[0m"
        docker rmi fleetops-backend-app -f 2>/dev/null
        echo -e "\033[1;32mCleanup complete!\033[0m"
    else
        echo -e "\033[1;33mCancelled.\033[0m"
    fi
}

# Command routing
case "$(echo "$COMMAND" | tr '[:upper:]' '[:lower:]')" in
    "install") install ;;
    "up") start_containers ;;
    "down") stop_containers ;;
    "restart") restart_containers ;;
    "rebuild") rebuild_containers ;;
    "logs") show_logs ;;
    "logs-app") show_applogs ;;
    "logs-db") show_dblogs ;;
    "shell") enter_shell ;;
    "db") enter_database ;;
    "migrate") run_migrations ;;
    "seed") seed_database ;;
    "fresh") fresh_database ;;
    "optimize") optimize_app ;;
    "clear") clear_caches ;;
    "test") run_tests ;;
    "status") show_status ;;
    "health") check_health ;;
    "clean") clean_all ;;
    *) show_help ;;
esac