<?php

namespace App\Modules\Maintenance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Maintenance\Services\SparePartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SparePartController extends Controller
{
    protected SparePartService $sparePartService;

    public function __construct(SparePartService $sparePartService)
    {
        $this->sparePartService = $sparePartService;
    }

    // دالة مساعدة لتوحيد شكل الداتا اللي راجعة للفرونت إند
    private function mapToFrontend($part)
    {
        return [
            'id' => 'PRT-' . $part->part_id,
            'name' => $part->name,
            'sku' => $part->sku,
            'category' => $part->category ?? 'other',
            'quantity' => $part->stock_quantity,
            'minThreshold' => $part->minimum_stock,
            'maxLevel' => $part->reorder_level ?? 200,
            'unitPrice' => (float) $part->unit_price,
            'location' => $part->description ?? 'Warehouse',
            'supplier' => $part->supplier_name ?? 'N/A',
            'lastRestocked' => $part->updated_at ? $part->updated_at->toIso8601String() : null,
            // داتا عشوائية مؤقتة عشان الـ Chart يشتغل
            'monthlyUsage' => [rand(10, 50), rand(20, 80), rand(15, 60), rand(30, 90), rand(10, 40), rand(25, 75)]
        ];
    }

    // دالة مساعدة لتوحيد الداتا اللي جاية من الفرونت إند قبل ما نحفظها في الداتا بيز
    private function mapToBackend(Request $request)
    {
        return [
            'name' => $request->input('name'),
            'sku' => $request->input('sku'),
            'category' => $request->input('category', 'other'),
            // لو الفرونت بعت unitPrice هاتها، لو مبعتش دور على unit_price
            'unit_price' => $request->input('unitPrice', $request->input('unit_price', 0)),
            'stock_quantity' => $request->input('quantity', $request->input('stock_quantity', 0)),
            'minimum_stock' => $request->input('minThreshold', $request->input('minimum_stock', 0)),
            'reorder_level' => $request->input('maxLevel', $request->input('reorder_level', null)),
            'supplier_name' => $request->input('supplier', $request->input('supplier_name', null)),
            'description' => $request->input('location', $request->input('description', null)),
        ];
    }

    public function index(): JsonResponse
    {
        $parts = $this->sparePartService->getAllParts();
        $formatted = $parts->map(fn($part) => $this->mapToFrontend($part));
        return response()->json($formatted);
    }

    public function store(Request $request): JsonResponse
    {
        // نستخدم المابينج هنا عشان ننظف الداتا قبل الحفظ
        $data = $this->mapToBackend($request);
        $part = $this->sparePartService->createPart($data);

        return response()->json($this->mapToFrontend($part), 201);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        // ونستخدمه هنا كمان في التعديل
        $data = $this->mapToBackend($request);
        $part = $this->sparePartService->updatePart($id, $data);

        return response()->json($this->mapToFrontend($part));
    }

    public function adjustStock(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'operation' => 'required|in:add,deduct'
        ]);

        $this->sparePartService->adjustStock($id, $request->quantity, $request->operation);
        return response()->json(['success' => true]);
    }
}