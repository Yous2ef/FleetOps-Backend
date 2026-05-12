#!/usr/bin/env pwsh
# FleetOps Backend Docker Management Script

param(
    [Parameter(Position=0)]
    [string]$Command = "help"
)

function Show-Help {
    Write-Host "FleetOps Backend - Docker Commands" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Usage: .\docker.ps1 [command]" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Commands:" -ForegroundColor Green
    Write-Host "  install     - Build and start containers (first time setup)"
    Write-Host "  up          - Start all containers"
    Write-Host "  down        - Stop all containers"
    Write-Host "  restart     - Restart all containers"
    Write-Host "  rebuild     - Rebuild and start containers"
    Write-Host "  logs        - Show logs from all containers"
    Write-Host "  logs-app    - Show logs from app container"
    Write-Host "  logs-db     - Show logs from database"
    Write-Host "  shell       - Access app container shell"
    Write-Host "  db          - Access SQL Server CLI"
    Write-Host "  migrate     - Run database migrations"
    Write-Host "  seed        - Seed the database with test data"
    Write-Host "  fresh       - Fresh database with migrations and seeds"
    Write-Host "  optimize    - Optimize Laravel caches"
    Write-Host "  clear       - Clear all Laravel caches"
    Write-Host "  test        - Run tests"
    Write-Host "  status      - Show container status"
    Write-Host "  health      - Check API health"
    Write-Host "  clean       - Remove all containers and volumes"
    Write-Host ""
}

function Install {
    Write-Host "Building and starting FleetOps Backend..." -ForegroundColor Cyan
    docker-compose up -d --build
    
    Write-Host "Waiting for services to be ready..." -ForegroundColor Yellow
    Start-Sleep -Seconds 20
    
    Write-Host "Ensuring database exists..." -ForegroundColor Yellow
    docker-compose exec sqlserver /opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "Fleetops12345678!" -C -Q "IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'fleetops') CREATE DATABASE fleetops"
    
    Write-Host "Running migrations..." -ForegroundColor Yellow
    docker-compose exec app php artisan migrate --force
    
    Write-Host "Seeding database with test data..." -ForegroundColor Yellow
    docker-compose exec app php artisan db:seed --force
    
    Write-Host "Clearing and optimizing caches..." -ForegroundColor Yellow
    docker-compose exec app php artisan cache:clear
    docker-compose exec app php artisan config:clear
    docker-compose exec app php artisan config:cache
    docker-compose exec app php artisan route:cache
    
    Write-Host ""
    Write-Host "Setup complete!" -ForegroundColor Green
    Write-Host "API available at: http://localhost:8000" -ForegroundColor Cyan
    Write-Host "Health check: http://localhost:8000/up" -ForegroundColor Cyan
    Write-Host "Login endpoint: http://localhost:8000/api/v1/auth/login" -ForegroundColor Cyan
}

function Start-Containers {
    Write-Host "Starting containers..." -ForegroundColor Cyan
    docker-compose up -d
    Write-Host "Containers started!" -ForegroundColor Green
}

function Stop-Containers {
    Write-Host "Stopping containers..." -ForegroundColor Cyan
    docker-compose down
    Write-Host "Containers stopped!" -ForegroundColor Green
}

function Restart-Containers {
    Write-Host "Restarting containers..." -ForegroundColor Cyan
    docker-compose restart
    Write-Host "Containers restarted!" -ForegroundColor Green
}

function Rebuild-Containers {
    Write-Host "Rebuilding and starting containers..." -ForegroundColor Cyan
    docker-compose up -d --build
    Write-Host "Containers rebuilt and started!" -ForegroundColor Green
}

function Show-Logs {
    docker-compose logs -f
}

function Show-AppLogs {
    docker-compose logs -f app
}

function Show-DbLogs {
    docker-compose logs -f sqlserver
}

function Enter-Shell {
    docker-compose exec app sh
}

function Enter-Database {
    docker-compose exec sqlserver /opt/mssql-tools18/bin/sqlcmd -S localhost -U sa -P "Fleetops12345678!" -C
}

function Run-Migrations {
    Write-Host "Running migrations..." -ForegroundColor Cyan
    docker-compose exec app php artisan migrate --force
    Write-Host "Migrations complete!" -ForegroundColor Green
}

function Seed-Database {
    Write-Host "Seeding database with test data..." -ForegroundColor Cyan
    docker-compose exec app php artisan db:seed --force
    Write-Host "Database seeded!" -ForegroundColor Green
}

function Fresh-Database {
    Write-Host "Refreshing database with fresh migrations and seeds..." -ForegroundColor Cyan
    docker-compose exec app php artisan migrate:fresh --force
    docker-compose exec app php artisan db:seed --force
    Write-Host "Database refreshed and seeded!" -ForegroundColor Green
}

function Optimize-App {
    Write-Host "Optimizing application..." -ForegroundColor Cyan
    docker-compose exec app php artisan config:cache
    docker-compose exec app php artisan route:cache
    docker-compose exec app php artisan view:cache
    Write-Host "Optimization complete!" -ForegroundColor Green
}

function Clear-Caches {
    Write-Host "Clearing caches..." -ForegroundColor Cyan
    docker-compose exec app php artisan cache:clear
    docker-compose exec app php artisan config:clear
    docker-compose exec app php artisan route:clear
    docker-compose exec app php artisan view:clear
    Write-Host "Caches cleared!" -ForegroundColor Green
}

function Run-Tests {
    Write-Host "Running tests..." -ForegroundColor Cyan
    docker-compose exec app php artisan test tests/Feature/UserTest.php
    docker-compose exec app php artisan test tests/Unit/TestUserPassword.php
}

function Show-Status {
    docker-compose ps
}

function Check-Health {
    Write-Host "Checking API health..." -ForegroundColor Cyan
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:8000/up" -UseBasicParsing -ErrorAction Stop
        if ($response.StatusCode -eq 200) {
            Write-Host "✅ API is healthy!" -ForegroundColor Green
            Write-Host "Status Code: $($response.StatusCode)" -ForegroundColor Green
        }
    } catch {
        Write-Host "❌ API is not responding properly" -ForegroundColor Red
        Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    }
}

function Clean-All {
    Write-Host "WARNING: This will remove all containers and volumes!" -ForegroundColor Red
    $confirm = Read-Host "Are you sure? (yes/no)"
    if ($confirm -eq "yes") {
        docker-compose down -v
        Write-Host "Removing fleetops-backend image..." -ForegroundColor Cyan
        docker rmi fleetops-backend-app -f 2>$null
        Write-Host "Cleanup complete!" -ForegroundColor Green
    } else {
        Write-Host "Cancelled." -ForegroundColor Yellow
    }
}

# Command routing
switch ($Command.ToLower()) {
    "install"   { Install }
    "up"        { Start-Containers }
    "down"      { Stop-Containers }
    "restart"   { Restart-Containers }
    "rebuild"   { Rebuild-Containers }
    "logs"      { Show-Logs }
    "logs-app"  { Show-AppLogs }
    "logs-db"   { Show-DbLogs }
    "shell"     { Enter-Shell }
    "db"        { Enter-Database }
    "migrate"   { Run-Migrations }
    "seed"      { Seed-Database }
    "fresh"     { Fresh-Database }
    "optimize"  { Optimize-App }
    "clear"     { Clear-Caches }
    "test"      { Run-Tests }
    "status"    { Show-Status }
    "health"    { Check-Health }
    "clean"     { Clean-All }
    default     { Show-Help }
}
