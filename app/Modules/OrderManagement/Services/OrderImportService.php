<?php

/**
 * @file: OrderImportService.php
 * @description: خدمة استيراد الطلبات الجماعية من CSV/XML (OM-01 / fn39)
 * @module: OrderManagement
 * @author: Team Leader (Khalid)
 */

namespace App\Modules\OrderManagement\Services;

use App\Modules\OrderManagement\Repositories\OrderRepository;
use Illuminate\Http\UploadedFile;
use Exception;

class OrderImportService
{
    protected OrderRepository $orderRepository;

    // Required CSV columns
    protected array $requiredColumns = [
        'customer_name', 'customer_phone', 'delivery_address',
        'lat', 'lng', 'weight_kg', 'payment_type',
    ];

    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function importOrders(UploadedFile $file, string $format): array
    {
        if ($format !== 'csv') {
            throw new Exception("Only CSV format is supported in this simple implementation.");
        }

        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new Exception("Could not open file.");
        }

        // Read first row as headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new Exception("Empty CSV file.");
        }

        // Clean headers and make them lower case for easier matching
        $headers = array_map(fn($h) => strtolower(trim($h, "\xEF\xBB\xBF ")), $headers);

        $imported = 0;
        $errors = [];
        $rowNumber = 1;
        $createdOrders = [];

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;
            
            // Skip empty rows
            if (empty(array_filter($data))) {
                continue;
            }

            // Basic check: column count must match
            if (count($headers) !== count($data)) {
                $errors[] = "Row $rowNumber: Column count mismatch (expected " . count($headers) . " columns).";
                continue;
            }

            // Trim all data values
            $data = array_map('trim', $data);
            $row = array_combine($headers, $data);
            
            try {
                // Mapping (Simplified)
                // 1. Find Customer
                $customerName = $row['customer name'] ?? $row['customer_name'] ?? $row['customer'] ?? null;
                if (!$customerName) {
                    $errors[] = "Row $rowNumber: Customer Name is missing (tried 'customer name', 'customer_name', 'customer').";
                    continue;
                }

                $user = \App\Modules\AuthIdentity\Models\User::where('name', $customerName)->first();
                
                // If customer not found, create a new one on the fly
                if (!$user) {
                    $user = \App\Modules\AuthIdentity\Models\User::create([
                        'name'     => $customerName,
                        'email'    => strtolower(str_replace(' ', '.', $customerName)) . '@example.com', // Placeholder email
                        'password' => bcrypt('password123'), // Default password
                        'role'     => 'Customer',
                    ]);
                }

                // 2. Prepare Order Data
                $orderData = [
                    'OrderID'             => rand(100000, 999999), // Generate a random unique ID for now
                    'CustomerID(FK)'      => $user->user_id,
                    'Status'              => $row['status'] ?? 'Pending',
                    'Type'                => $row['type'] ?? 'Normal',
                    'Price'               => (int) ($row['price'] ?? 0),
                    'Payment_method'      => $row['payment method'] ?? $row['payment_method'] ?? $row['payment_r'] ?? 'Cash',
                    'Area'                => $row['area'] ?? 'Cairo',
                    'Weight'              => !empty($row['weight']) ? (int)$row['weight'] : null,
                    'Volume'              => !empty($row['volume']) ? (int)$row['volume'] : null,
                    'Latitude'            => !empty($row['latitude']) ? (float)$row['latitude'] : null,
                    'Longitude'           => !empty($row['longitude']) ? (float)$row['longitude'] : null,
                    'Perishable'          => (isset($row['perishable']) && strtoupper($row['perishable']) === 'TRUE'),
                    'Delivery_preference' => $row['delivery_preference'] ?? null,
                ];

                // 3. Save
                $order = clone $this->orderRepository->create($orderData);
                
                // Trigger tracking token fallback
                if (empty($order->LiveTrackingLink)) {
                    $order->LiveTrackingLink = bin2hex(random_bytes(16));
                    $order->save();
                }

                $createdOrders[] = $order;
                $imported++;

            } catch (Exception $e) {
                $errors[] = "Row $rowNumber: " . $e->getMessage();
            }
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'errors'   => $errors,
            'batch_id' => uniqid('batch_'),
            'orders'   => \App\Modules\OrderManagement\Resources\OrderResource::collection($createdOrders)
        ];
    }

    /**
     * التحقق من Schema ملف CSV (OM-01)
     * @param array $headers  CSV header row
     * @return array  missing columns
     */
    protected function validateCsvSchema(array $headers): array
    {
        // TODO: Return list of missing required columns
        // return array_diff($this->requiredColumns, $headers);
        return [];
    }

    /**
     * التحقق من بيانات صف واحد
     * @param array $row
     * @param int $rowNumber
     * @return array  validation errors for this row
     */
    protected function validateRow(array $row, int $rowNumber): array
    {
        // TODO: Validate single row data
        // Check: lat/lng are valid numbers, payment_type in allowed values, weight_kg > 0
        // Return errors array (empty if valid)
        return [];
    }
}

