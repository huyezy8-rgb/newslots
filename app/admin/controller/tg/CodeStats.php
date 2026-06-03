<?php

namespace app\admin\controller\tg;

use app\common\controller\Backend;
use think\exception\HttpResponseException;
use think\facade\Db;
use think\facade\Log;
use Throwable;

class CodeStats extends Backend
{
    protected array $noNeedPermission = [];

    protected string|array $quickSearchField = ['code'];

    public function index(): void
    {
        try {
            $limit = max(1, (int)$this->request->param('limit/d', 10));
            $page = max(1, (int)$this->request->param('page/d', 1));

            $baseQuery = $this->buildBaseQuery();
            $total = (clone $baseQuery)->count();
            $list = $baseQuery
                ->page($page, $limit)
                ->select()
                ->toArray();

            $this->success('', [
                'list' => $list,
                'total' => $total,
                'remark' => get_route_remark(),
            ]);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('[TG] codeStats index failed: ' . $e->getMessage());
            $this->error($e->getMessage() ?: '操作失败');
        }
    }

    private function buildBaseQuery()
    {
        $query = Db::name('tg_send_record')
            ->alias('r')
            ->leftJoin('tg_bot_config b', 'r.bot_id = b.id')
            ->where('redemption_code_id', '>', 0)
            ->fieldRaw(
                'r.redemption_code_id,
                MAX(r.code) AS code,
                MAX(r.bot_id) AS bot_id,
                MAX(b.name) AS bot_name,
                MAX(r.template_name) AS template_name,
                MIN(r.send_time) AS first_send_time,
                MAX(r.send_time) AS last_send_time,
                COUNT(*) AS send_count,
                SUM(CASE WHEN r.send_status = 1 THEN 1 ELSE 0 END) AS success_send_count,
                SUM(CASE WHEN r.send_status = 0 THEN 1 ELSE 0 END) AS failed_send_count,
                MAX(r.claim_count) AS claim_count,
                MAX(r.claim_amount) AS claim_amount,
                MAX(r.register_count) AS register_count,
                MAX(r.first_recharge_count) AS first_recharge_count,
                MAX(r.first_recharge_amount) AS first_recharge_amount,
                MAX(r.recharge_count) AS recharge_count,
                MAX(r.recharge_amount) AS recharge_amount,
                CASE WHEN MAX(r.claim_count) > 0 THEN 1 ELSE 0 END AS has_claim,
                CASE WHEN MAX(r.recharge_count) > 0 THEN 1 ELSE 0 END AS has_recharge'
            )
            ->group('r.redemption_code_id');

        $this->applyFilters($query);

        return $query
            ->order('last_send_time', 'desc')
            ->order('r.redemption_code_id', 'desc');
    }

    private function applyFilters($query): void
    {
        $quickSearch = trim((string)$this->request->param('quick_search', ''));
        if ($quickSearch !== '') {
            $query->whereLike('r.code', '%' . str_replace('%', '\%', $quickSearch) . '%');
        }

        $filters = $this->request->param('filter', []);
        $ops = $this->request->param('op', []);
        if (is_string($filters)) {
            $filters = json_decode($filters, true) ?: [];
        }
        if (is_string($ops)) {
            $ops = json_decode($ops, true) ?: [];
        }
        if (!is_array($filters)) {
            $filters = [];
        }
        if (!is_array($ops)) {
            $ops = [];
        }

        if (isset($filters['code']) && $filters['code'] !== '') {
            $query->whereLike('r.code', '%' . str_replace('%', '\%', (string)$filters['code']) . '%');
        }

        if (isset($filters['bot_id']) && $filters['bot_id'] !== '') {
            $query->where('r.bot_id', (int)$filters['bot_id']);
        }

        if (isset($filters['send_time']) && $filters['send_time'] !== '') {
            $range = explode(',', (string)$filters['send_time']);
            if (count($range) >= 2) {
                $start = strtotime($range[0]);
                $end = strtotime($range[1]);
                if ($start && $end) {
                    $query->whereBetween('r.send_time', [$start, $end]);
                }
            }
        }

        if (isset($filters['has_claim']) && $filters['has_claim'] !== '') {
            (int)$filters['has_claim'] === 1
                ? $query->having('MAX(claim_count) > 0')
                : $query->having('MAX(claim_count) = 0');
        }

        if (isset($filters['has_recharge']) && $filters['has_recharge'] !== '') {
            (int)$filters['has_recharge'] === 1
                ? $query->having('MAX(recharge_count) > 0')
                : $query->having('MAX(recharge_count) = 0');
        }

        $search = $this->request->param('search', []);
        if (is_string($search)) {
            $search = json_decode($search, true) ?: [];
        }
        if (!is_array($search)) {
            return;
        }

        foreach ($search as $item) {
            if (!is_array($item) || !isset($item['field'], $item['val'])) {
                continue;
            }
            $field = (string)$item['field'];
            $value = $item['val'];
            if ($value === '' || $value === null) {
                continue;
            }

            if ($field === 'code') {
                $query->whereLike('r.code', '%' . str_replace('%', '\%', (string)$value) . '%');
            } elseif ($field === 'bot_id') {
                $query->where('r.bot_id', (int)$value);
            } elseif ($field === 'send_time') {
                $range = explode(',', (string)$value);
                if (count($range) >= 2) {
                    $start = strtotime($range[0]);
                    $end = strtotime($range[1]);
                    if ($start && $end) {
                        $query->whereBetween('r.send_time', [$start, $end]);
                    }
                }
            } elseif ($field === 'has_claim') {
                (int)$value === 1
                    ? $query->having('MAX(claim_count) > 0')
                    : $query->having('MAX(claim_count) = 0');
            } elseif ($field === 'has_recharge') {
                (int)$value === 1
                    ? $query->having('MAX(recharge_count) > 0')
                    : $query->having('MAX(recharge_count) = 0');
            }
        }
    }

    public function add(): void
    {
        $this->error(__('You have no permission'));
    }

    public function edit(): void
    {
        $this->error(__('You have no permission'));
    }

    public function del(): void
    {
        $this->error(__('You have no permission'));
    }
}
