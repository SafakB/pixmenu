<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Libraries\Billing\Period;
use App\Models\ActivityLogModel;

/**
 * İşlem kayıtları (salt okunur) — App\Services\ActivityLogger'ın yazdıkları.
 */
final class ActivityLogController extends AdminBaseController
{
    /**
     * ?area=admin|panel&restaurant=ID&actor=&action=&from=Y-m-d&to=Y-m-d&page=N
     *
     * View: admin/logs/index —
     *   filters{area, restaurant, actor, action, from, to},
     *   restaurants[{id, name}] (filtre seçimi),
     *   logs[{id, created_at, area, actor_id, actor_label, restaurant_id|null, restaurant_name|null,
     *         action, action_label, path, ip, detail{anahtar: değer}}],
     *   page (int), hasMore (bool), retentionDays (int)
     */
    public function index(): string
    {
        $get = fn (string $key): string => trim((string) ($this->request->getGet($key) ?? ''));

        $area       = in_array($get('area'), ['admin', 'panel'], true) ? $get('area') : '';
        $restaurant = ctype_digit($get('restaurant')) ? (int) $get('restaurant') : 0;
        $actor      = mb_substr($get('actor'), 0, 100);
        $action     = mb_substr($get('action'), 0, 60);
        $from       = Period::isValidDate($get('from')) ? $get('from') : '';
        $to         = Period::isValidDate($get('to')) ? $get('to') : '';
        $page       = max(1, min(10000, (int) $get('page')));
        $perPage    = config('Pixmenu')->perPage;

        $model = new ActivityLogModel();

        if ($area !== '') {
            $model->where('area', $area);
        }

        if ($restaurant > 0) {
            $model->where('restaurant_id', $restaurant);
        }

        if ($actor !== '') {
            $model->like('actor_label', $actor);
        }

        if ($action !== '') {
            $model->like('action', $action, 'after');
        }

        if ($from !== '') {
            $model->where('created_at >=', $from . ' 00:00:00');
        }

        if ($to !== '') {
            $model->where('created_at <=', $to . ' 23:59:59');
        }

        // Sayım sorgusu yok: bir fazla satır "sonraki sayfa var mı" sorusunu yanıtlar.
        $rows    = $model->orderBy('id', 'DESC')->findAll($perPage + 1, ($page - 1) * $perPage);
        $hasMore = count($rows) > $perPage;
        $rows    = array_slice($rows, 0, $perPage);
        $names   = $this->restaurantNames();

        return $this->render('admin/logs/index', [
            'filters' => [
                'area'       => $area,
                'restaurant' => $restaurant > 0 ? $restaurant : null,
                'actor'      => $actor,
                'action'     => $action,
                'from'       => $from,
                'to'         => $to,
            ],
            'restaurants' => array_map(static fn (int $id, string $name): array => ['id' => $id, 'name' => $name], array_keys($names), $names),
            'logs'        => array_map(fn (array $row): array => [
                'id'              => (int) $row['id'],
                'created_at'      => $row['created_at'],
                'area'            => $row['area'],
                'actor_id'        => (int) $row['actor_id'],
                'actor_label'     => $row['actor_label'],
                'restaurant_id'   => $row['restaurant_id'] === null ? null : (int) $row['restaurant_id'],
                'restaurant_name' => $row['restaurant_id'] === null ? null : ($names[(int) $row['restaurant_id']] ?? '#' . $row['restaurant_id']),
                'action'          => $row['action'],
                'action_label'    => $this->actionLabel((string) $row['action']),
                'path'            => $row['path'],
                'ip'              => $row['ip'],
                'detail'          => $this->detail($row['detail']),
            ], $rows),
            'page'          => $page,
            'hasMore'       => $hasMore,
            'retentionDays' => config('Pixmenu')->activityLogRetentionDays,
        ]);
    }

    /**
     * `invoice.pay` → "Fatura: Ödendi işaretle". Sözlükte olmayan parça ham haliyle kalır.
     */
    private function actionLabel(string $action): string
    {
        [$subject, $verb] = explode('.', $action, 2) + [1 => ''];

        $whole = 'App.activity.action.' . $subject . '.' . $verb;

        if (lang($whole) !== $whole) {
            return lang($whole);
        }

        $subjectKey = 'App.activity.subject.' . $subject;
        $verbKey    = 'App.activity.verb.' . $verb;

        return (lang($subjectKey) !== $subjectKey ? lang($subjectKey) : $subject)
            . ': '
            . (lang($verbKey) !== $verbKey ? lang($verbKey) : $verb);
    }

    /**
     * @return array<string, string>
     */
    private function detail(?string $json): array
    {
        $data = $json === null ? null : json_decode($json, true);

        if (! is_array($data)) {
            return [];
        }

        $out = [];

        foreach ($data as $key => $value) {
            $out[(string) $key] = is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $out;
    }
}
