<?php

namespace App\Modules\OrderManagement\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\ReportingAnalytics\Models\CashLedger;
use Illuminate\Support\Facades\DB;

class CodController extends Controller
{
    /**
     * Retrieve a list of all COD records.
     * Maps database fields to the structure expected by the frontend.
     */
    public function index(Request $request)
    {
        try {
            // Eager load relationships to prevent N+1 queries
            $records = CashLedger::with(['order.customer.user', 'order.vehicle', 'driver.user'])->get();

            $formattedRecords = $records->map(function ($record) {
                return $this->formatCodRecord($record);
            });

            return response()->json([
                'success' => true,
                'data' => $formattedRecords
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch COD records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retrieve a specific COD record by ID.
     */
    public function show($id)
    {
        // Handle 'COD-123' format if sent by frontend
        $transactionId = str_replace('COD-', '', $id);

        try {
            $record = CashLedger::with(['order.customer.user', 'order.vehicle', 'driver.user'])
                ->findOrFail($transactionId);

            return response()->json([
                'success' => true,
                'data' => $this->formatCodRecord($record)
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'COD record not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch COD record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark a COD record as handed over to finance/company.
     */
    public function markHandover(Request $request, $id)
    {
        $transactionId = str_replace('COD-', '', $id);

        try {
            $record = CashLedger::with(['order.customer.user', 'order.vehicle', 'driver.user'])
                ->findOrFail($transactionId);

            // Update handover status
            $record->handed_over_to_company = true;
            $record->save();

            return response()->json([
                'success' => true,
                'message' => 'Record marked as handed over',
                'data' => $this->formatCodRecord($record)
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'COD record not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark handover: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Maps a CashLedger Eloquent model to the frontend COD record structure.
     */
    private function formatCodRecord(CashLedger $record)
    {
        $order = $record->order;
        $driver = $record->driver;
        $customer = $order ? $order->customer : null;
        $customerUser = $customer ? $customer->user : null;
        $vehicle = $order ? $order->vehicle : null;
        
        // Calculate handover status text
        $handoverStatus = $record->handed_over_to_company ? 'Handed Over' : 'Not Handed Over';
        
        // Format names
        $driverName = $driver && $driver->user ? $driver->user->name : 'Unknown Driver';
        $driverInitials = implode('', array_map(function($n) { return $n[0] ?? ''; }, explode(' ', $driverName)));
        if (empty($driverInitials)) $driverInitials = 'N/A';

        // Payment status capitalization
        $collectionStatus = ucfirst($record->payment_status);

        // Date formatting
        $handedOverAt = $record->handed_over_to_company && $record->updated_at 
            ? $record->updated_at->format('M d, h:i A') 
            : '';
            
        $collectedAt = $record->transaction_ts 
            ? $record->transaction_ts->format('M d, h:i A') 
            : '';

        return [
            'id' => 'COD-' . $record->transaction_id,
            'orderId' => 'ORD-' . $record->order_id,
            'customer' => $customerUser ? $customerUser->name : 'Unknown Customer',
            'customerPhone' => $customerUser ? ($customerUser->phone_no ?? 'N/A') : 'N/A',
            'address' => $order ? ($order->Area ?? $customer->address ?? 'Unknown Address') : 'Unknown Address',
            'driver' => $driverName,
            'driverInitials' => strtoupper(substr($driverInitials, 0, 2)),
            'routeId' => 'N/A', // Route ID is not directly in cash_ledger or order, returning N/A or mock
            'vehicleId' => $vehicle ? $vehicle->VehicleLicense : 'N/A',
            'expectedAmount' => $order ? (float)$order->Price : 0,
            'collectedAmount' => (float)$record->amount_collected,
            'collectionStatus' => $collectionStatus,
            'handoverStatus' => $handoverStatus,
            'handedOverAt' => $handedOverAt,
            'collectedAt' => $collectedAt,
            'receivedBy' => $record->handed_over_to_company ? 'Finance Admin' : '',
            'receiptNumber' => $record->handed_over_to_company ? 'REC-' . (8000 + $record->transaction_id) : '',
            'paymentMethod' => ucfirst(str_replace('_', ' ', $record->payment_method)),
            'balanceReason' => '',
            'denominationBreakdown' => [],
        ];
    }
}
