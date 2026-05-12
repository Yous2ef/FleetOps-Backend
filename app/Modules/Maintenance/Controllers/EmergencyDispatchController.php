<?php

namespace App\Modules\Maintenance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Modules\Maintenance\Services\EmergencyDispatchService;

class EmergencyDispatchController extends Controller
{
    protected EmergencyDispatchService $dispatchService;

    public function __construct(EmergencyDispatchService $dispatchService)
    {
        $this->dispatchService = $dispatchService;
    }

    /**
     * List of active vehicle breakdowns and incidents.
     * GET api/v1/maintenance/emergency/incidents
     */
    public function incidents(): JsonResponse
    {
        try {
            $data = $this->dispatchService->getActiveIncidents();

            return response()->json([
                'success' => true,
                'message' => "تم جلب البيانات بنجاح",
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "فشل جلب البيانات",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detailed data for a specific incident.
     * GET api/v1/maintenance/emergency/incident-details/{id}
     */
    public function incidentDetails(int $id): JsonResponse
    {
        try {
            $data = $this->dispatchService->getIncidentDetails($id);

            return response()->json([
                'success' => true,
                'message' => "تم جلب البيانات بنجاح",
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "فشل جلب البيانات",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List of mechanics sorted by distance to the incident.
     * GET api/v1/maintenance/emergency/nearby-mechanics/{id}
     */
    public function nearbyMechanics(int $id): JsonResponse
    {
        try {
            $data = $this->dispatchService->getNearbyMechanics($id);

            return response()->json([
                'success' => true,
                'message' => "تم جلب البيانات بنجاح",
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "فشل جلب البيانات",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dispatches a mechanic and creates an emergency work order.
     * POST api/v1/maintenance/emergency/dispatch-mechanic
     *
     * Body: { incident_id: int, mechanic_id: int }
     */
    public function dispatchMechanic(Request $request, $incident_id): JsonResponse
    {
        // Merge incident_id from URL into request for validation
        $request->merge(['incident_id' => $incident_id]);

        $validated = $request->validate([
            'incident_id' => 'required|integer|exists:incident_reports,incident_id',
            'mechanic_id' => 'required|integer|exists:mechanics,mechanic_id',
        ]);

        try {
            $data = $this->dispatchService->dispatchMechanic(
                $validated['incident_id'],
                ['mechanic_id' => $validated['mechanic_id']]
            );

            return response()->json([
                'success' => true,
                'message' => "تم التعيين بنجاح",
                'data' => $data
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "فشل التعيين",
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
