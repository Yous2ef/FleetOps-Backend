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
            throw new Exception("Only CSV format is supported at the moment.");
        }

        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new Exception("Could not open the uploaded file.");
        }

        // 1. Read and clean headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new Exception("The CSV file appears to be empty.");
        }

        // Clean BOM and trim headers
        $headers = array_map(fn($h) => strtolower(trim($h, "\xEF\xBB\xBF ")), $headers);

        $imported = 0;
        $errors = [];
        $rowNumber = 1;

        // 2. Define expected column mappings (Aligned with your image)
        $mapping = [
            'customer_name'       => ['customer', 'customer name', 'customer_name'],
            'customer_phone'      => ['customer_phone', 'phone', 'mobile'],
            'status'              => ['status'],
            'type'                => ['type'],
            'price'               => ['price'],
            'delivery_preference' => ['delivery_preference', 'delivery preference'],
            'payment_method'      => ['payment_method', 'payment method', 'payment'],
            'perishable'          => ['perishable'],
            'weight'              => ['weight'],
            'volume'              => ['volume'],
            'delivery_time_window' => ['deliverytimewindow', 'delivery time window'],
            'longitude'           => ['longitude', 'lng'],
            'latitude'            => ['latitude', 'lat'],
            'area'                => ['area', 'delivery_address', 'address'],
        ];

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;
            
            if (empty(array_filter($data))) continue;

            if (count($headers) !== count($data)) {
                $errors[] = "Row $rowNumber: Column mismatch (Headers: " . count($headers) . ", Data: " . count($data) . ")";
                continue;
            }

            $rawRow = array_combine($headers, array_map('trim', $data));
            
            // Map the CSV data to our internal format
            $mappedData = [];
            foreach ($mapping as $internalKey => $aliases) {
                foreach ($aliases as $alias) {
                    $aliasLower = strtolower($alias);
                    if (isset($rawRow[$aliasLower])) {
                        $mappedData[$internalKey] = $rawRow[$aliasLower];
                        break;
                    }
                }
            }

            // 3. Data Validation
            $validator = \Illuminate\Support\Facades\Validator::make($mappedData, [
                'customer_name'  => 'required|string|max:255',
                'customer_phone' => 'required|string|max:20',
                'area'           => 'required|string|max:500',
                'latitude'       => 'required|numeric',
                'longitude'      => 'required|numeric',
                'weight'         => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                $errors[] = "Row $rowNumber Validation: " . implode(', ', $validator->errors()->all());
                continue;
            }

            try {
                \Illuminate\Support\Facades\DB::transaction(function () use ($mappedData, &$imported) {
                    // 4. Handle Customer/User Creation
                    $email = 'cust.' . \Illuminate\Support\Str::slug($mappedData['customer_name']) . '.' . rand(100,999) . '@fleetops.local';

                    $user = \App\Modules\AuthIdentity\Models\User::firstOrCreate(
                        ['phone_no' => $mappedData['customer_phone']], // Match by phone if possible
                        [
                            'name'      => $mappedData['customer_name'],
                            'email'     => $email,
                            'password'  => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(12)),
                            'role'      => 'Customer',
                            'is_active' => true,
                        ]
                    );

                    // Ensure Customer profile exists
                    \App\Modules\AuthIdentity\Models\Customer::updateOrCreate(
                        ['customer_id' => $user->user_id],
                        [
                            'address' => $mappedData['area'],
                            'delivery_preference' => $mappedData['delivery_preference'] ?? null
                        ]
                    );

                    // 5. Prepare Order Record (Aligned with Image Columns)
                    $orderId = rand(1000000, 9999999);
                    $deliveryWindow = $mappedData['delivery_time_window'] ?? null;
                    if ($deliveryWindow) {
                        // Remove colons and other non-numeric chars except the decimal point
                        $deliveryWindow = preg_replace('/[^0-9.]/', '', str_replace(':', '.', $deliveryWindow));
                    }

                    $orderData = [
                        'OrderID'             => $orderId, 
                        'CustomerID(FK)'      => $user->user_id,
                        'Status'              => $mappedData['status'] ?? 'Pending',
                        'Type'                => $mappedData['type'] ?? 'Normal',
                        'Priority'            => (strtoupper($mappedData['type'] ?? '') === 'EXPRESS') ? 80 : 40,
                        'Price'               => (int)($mappedData['price'] ?? 0),
                        'Payment_method'      => $mappedData['payment_method'] ?? 'Cash',
                        'Area'                => $mappedData['area'],
                        'Weight'              => (int)$mappedData['weight'],
                        'Volume'              => (int)($mappedData['volume'] ?? 0),
                        'Latitude'            => $mappedData['latitude'],
                        'Longitude'           => $mappedData['longitude'],
                        'Perishable'          => (isset($mappedData['perishable']) && strtoupper($mappedData['perishable']) === 'TRUE'),
                        'DeliveryTimeWindow'  => $deliveryWindow ? (float)$deliveryWindow : null,
                        'Delivery_preference' => $mappedData['delivery_preference'] ?? null,
                        'digital_signature'   => strtoupper(\Illuminate\Support\Str::random(10)),
                        'LiveTrackingLink'    => 'http://fleetops.com/track/' . $orderId,
                        'Created_at'          => now(),
                        'UpdatedAt'           => now(),
                    ];

                    $this->orderRepository->create($orderData);
                    $imported++;
                });

            } catch (Exception $e) {
                $errors[] = "Row $rowNumber Error: " . $e->getMessage();
            }
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'errors'   => $errors,
            'batch_id' => uniqid('batch_')
        ];
    }
}

