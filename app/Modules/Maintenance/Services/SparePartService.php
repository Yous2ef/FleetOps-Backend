<?php

namespace App\Modules\Maintenance\Services;

use App\Modules\Maintenance\Repositories\SparePartRepository;

class SparePartService
{
    protected SparePartRepository $sparePartRepository;

    public function __construct(SparePartRepository $sparePartRepository)
    {
        $this->sparePartRepository = $sparePartRepository;
    }

    public function getAllParts()
    {
        // التعديل هنا: استخدام getAll() بدلاً من all()
        return $this->sparePartRepository->getAll();
    }

    public function getPartById(int $id)
    {
        return $this->sparePartRepository->findById($id);
    }

    public function createPart(array $data)
    {
        return $this->sparePartRepository->create($data);
    }

    public function updatePart(int $id, array $data)
    {
        $this->sparePartRepository->update($id, $data);
        return $this->sparePartRepository->findById($id); // عشان نرجع الداتا متحدثة
    }

    public function deletePart(int $id): bool
    {
        return $this->sparePartRepository->delete($id);
    }

    public function getLowStockParts()
    {
        return $this->sparePartRepository->getLowStockParts();
    }

    public function adjustStock(int $partId, int $quantity, string $operation): bool
    {
        if ($operation === 'add') {
            return $this->sparePartRepository->addStock($partId, $quantity);
        } elseif ($operation === 'deduct') {
            return $this->sparePartRepository->deductStock($partId, $quantity);
        }
        return false;
    }
}