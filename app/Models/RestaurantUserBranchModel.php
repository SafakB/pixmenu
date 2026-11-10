<?php

declare(strict_types=1);

namespace App\Models;

/**
 * PK (user_id, branch_id). Yalnızca manager için anlamlıdır.
 * Tekil find/update/delete yerine aşağıdaki kapsamlı metodlar kullanılır.
 */
final class RestaurantUserBranchModel extends TenantModel
{
    protected $table            = 'restaurant_user_branches';
    protected $primaryKey       = 'branch_id';
    protected $useAutoIncrement = false;
    protected $useTimestamps    = false;
    protected $allowedFields    = ['restaurant_id', 'user_id', 'branch_id'];

    /**
     * Kullanıcının atanmış (ve silinmemiş) şube ID'leri.
     *
     * @return list<int>
     */
    public function branchIdsForUser(int $userId): array
    {
        $rows = $this->scopedBuilder()
            ->select('restaurant_user_branches.branch_id')
            ->join(
                'branches',
                'branches.id = restaurant_user_branches.branch_id AND branches.restaurant_id = restaurant_user_branches.restaurant_id',
            )
            ->where('restaurant_user_branches.user_id', $userId)
            ->where('branches.deleted_at', null)
            ->orderBy('restaurant_user_branches.branch_id', 'ASC')
            ->get()
            ->getResultArray();

        return array_map('intval', array_column($rows, 'branch_id'));
    }

    /**
     * Birden fazla kullanıcının şubeleri.
     *
     * @param list<int> $userIds
     *
     * @return array<int, list<int>> user_id => branch_id[]
     */
    public function branchIdsForUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = $this->scopedBuilder()
            ->select('restaurant_user_branches.user_id, restaurant_user_branches.branch_id')
            ->join(
                'branches',
                'branches.id = restaurant_user_branches.branch_id AND branches.restaurant_id = restaurant_user_branches.restaurant_id',
            )
            ->whereIn('restaurant_user_branches.user_id', $userIds)
            ->where('branches.deleted_at', null)
            ->get()
            ->getResultArray();

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row['user_id']][] = (int) $row['branch_id'];
        }

        return $map;
    }

    /**
     * Kullanıcının şube atamalarını verilen kümeyle değiştirir.
     * Çağıran taraf ID'lerin tenant'a ait olduğunu ÖNCEDEN doğrulamış olmalıdır;
     * bileşik FK ikinci savunma hattıdır.
     *
     * @param list<int> $branchIds
     */
    public function replaceForUser(int $userId, array $branchIds): void
    {
        $restaurantId = $this->requireScopeRestaurantId();

        $this->scopedBuilder()->where('user_id', $userId)->delete();

        foreach (array_values(array_unique($branchIds)) as $branchId) {
            $this->builder()->insert([
                'restaurant_id' => $restaurantId,
                'user_id'       => $userId,
                'branch_id'     => $branchId,
            ]);
        }
    }
}
