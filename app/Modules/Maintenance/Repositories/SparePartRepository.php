<?php
namespace App\Modules\Maintenance\Repositories;

use App\Modules\Shared\Repositories\BaseRepository;
use App\Modules\Maintenance\Models\SparePart;
use Illuminate\Database\Eloquent\Collection;
use Exception;

class SparePartRepository extends BaseRepository
{
    public function __construct(SparePart $model)
    {
        parent::__construct($model);
    }

    public function getLowStockParts(): Collection
    {
        return $this->model->lowStock()->get();
    }

    public function deductStock(int $partId, int $quantity): bool
    {
        $part = $this->model->where('part_id', $partId)->lockForUpdate()->first();
        if (!$part)
            throw new Exception("قطعة الغيار غير موجودة");
        if ($part->stock_quantity < $quantity)
            throw new Exception("المخزون غير كافي");

        $this->model->where('part_id', $partId)->decrement('stock_quantity', $quantity);
        return true;
    }

    public function addStock(int $partId, int $quantity): bool
    {
        $this->model->where('part_id', $partId)->increment('stock_quantity', $quantity);
        return true;
    }
}