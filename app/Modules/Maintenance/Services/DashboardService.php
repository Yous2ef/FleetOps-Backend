<?php

namespace App\Modules\Maintenance\Services;

use App\Modules\RouteDispatch\Models\Vehicle;
use App\Modules\Maintenance\Models\MaintenanceAssignment;
use App\Modules\Maintenance\Models\VehicleInspection;
use App\Modules\Maintenance\Models\SparePart;
use Carbon\Carbon;

class DashboardService
{
    public function getSummary(): array
    {
        return [
            'KPI_DATA'              => $this->getKpiData(),
            'WORK_ORDERS_DATA'      => $this->getWorkOrdersData(),
            'VEHICLES_ATTENTION_DATA' => $this->getVehiclesAttentionData(), // Fixed casing
        ];
    }

    private function getKpiData(): array
    {
        $totalVehicles = Vehicle::count();
        
        // Assuming 'Status' column exists as per your Vehicle model earlier
        $availableVehicles = Vehicle::where('Status', 'Active')->count(); 
        $inMaintenanceVehicles = Vehicle::where('Status', 'Maintenance')->count();
        
        // "Out of service" might be a specific status or simply those in maintenance + inactive
        $outOfServiceVehicles = Vehicle::whereNotIn('Status', ['Active'])->count();

        $openWorkOrders = MaintenanceAssignment::whereIn('status', ['open','assigned','in_progress'])->count();
        $urgentWorkOrders = MaintenanceAssignment::whereIn('status', ['open','assigned','in_progress'])
            ->whereIn('priority', ['high','critical'])->count();

        return [
            'total_vehicles'          => $totalVehicles,
            'available_vehicles'      => $availableVehicles,
            'in_service_vehicles'     => $inMaintenanceVehicles, // Renamed for clarity
            'out_of_service_vehicles' => $outOfServiceVehicles,
            'open_work_orders'        => $openWorkOrders,
            'urgent_work_orders'      => $urgentWorkOrders,
        ];
    }

    private function getWorkOrdersData(): array
    {
        return MaintenanceAssignment::with(['vehicle', 'mechanic.user'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->toArray();
    }

    private function getVehiclesAttentionData(): array
    {
        // The vehicle_inspections table does not exist in the DB, returning empty collection
        //   $overdueInspections = VehicleInspection::whereNotNull('next_inspection_date')
        //     ->where('next_inspection_date', '<', Carbon::now())
        //     ->pluck('vehicle_id');
        $overdueInspections = collect([]);

        $criticalWorkOrders = MaintenanceAssignment::whereIn('status', ['open', 'assigned', 'in_progress'])
            ->whereIn('priority', ['high', 'critical'])
            ->pluck('vehicle_id');

        // Merge collections and get unique IDs
        $vehicleIds = $overdueInspections->merge($criticalWorkOrders)->unique();

        return Vehicle::whereIn('vehicle_id', $vehicleIds)->get()->toArray();
    }
}
