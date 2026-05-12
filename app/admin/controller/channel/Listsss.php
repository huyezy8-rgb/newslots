<?php

namespace app\admin\controller\channel;

use app\common\controller\Backend;
use app\common\model\activity\Activity;
use Throwable;
use think\facade\Db;

/**
 * 渠道列管理
 */
class Listsss extends Backend
{
    /**
     * Listsss模型对象
     * @var object
     * @phpstan-var \app\admin\model\channel\Listsss
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\channel\Listsss();
    }

    private function mergeActivityOption($defaultOption, $incomingOption): array
    {
        if (!is_array($defaultOption)) { $defaultOption = []; }
        if (!is_array($incomingOption)) { $incomingOption = []; }
        return $this->arrayMergeRecursiveDistinct($defaultOption, $incomingOption);
    }

    private function arrayMergeRecursiveDistinct(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->arrayMergeRecursiveDistinct($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

	private function decodeJsonIfNeeded($value)
	{
		if (!is_string($value)) {
			return $value;
		}
		$decoded = html_entity_decode($value, ENT_QUOTES);
		$decoded = stripslashes($decoded);
		$trimmed = trim($decoded);
		if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
			$tmp = json_decode($trimmed, true);
			if (json_last_error() === JSON_ERROR_NONE) {
				return $tmp;
			}
		}
		return $value;
	}

	private function normalizeOption($option)
	{
		$option = $this->decodeJsonIfNeeded($option);
		if (!is_array($option)) {
			return $option;
		}
		foreach ($option as $k => $v) {
			$option[$k] = $this->normalizeOption($v);
		}
		return $option;
	}

	private function toBoolInt($value): int
	{
		if (is_bool($value)) return $value ? 1 : 0;
		if (is_numeric($value)) return ((int)$value) ? 1 : 0;
		$value = is_string($value) ? strtolower(trim($value)) : $value;
		return in_array($value, ['1','true','on','yes'], true) ? 1 : 0;
	}

	private function extractFlagsFromOption(array &$option): array
	{
		$flags = [];
		return $flags;
	}


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        // 添加渠道权限过滤
        $where = $this->addChannelFilter($where, 'channelid');

        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $list = $res->items();
        foreach ($list as $k=>$v) {
            if (isset($v["activity"])) {
                $list[$k]['activity_list'] = array_column(json_decode($v['activity'],true) ?? [],"title");
                unset($list[$k]['activity']);
            }
        }

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }


    /**
     * 添加
     */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data = $this->excludeFields($data);
            if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                $data[$this->dataLimitField] = $this->auth->id;
            }

            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('add');
                        $validate->check($data);
                    }
                }
                // 规范化并保存 activity
                $activity = [];
                if (isset($data['activity'])) {
                    $raw = $data['activity'];
                    if (is_string($raw)) {
                        $raw = html_entity_decode($raw, ENT_QUOTES);
                        $raw = stripslashes($raw);
                        $raw = json_decode($raw, true);
                    }
                    if (is_array($raw)) {
                        // 查询 slot_activity，构建映射
                        $allActivities = Activity::select();
                        $activityMap = [];
                        foreach ($allActivities as $a) {
                            $activityMap[$a['type']] = [
                                'title' => $a['name'],
                                'option' => $a['config'] ? json_decode($a['config'], true) : [],
                            ];
                        }
                        foreach ($raw as $item) {
                            $key = $item['key'] ?? ($item['type'] ?? null);
                            if (!$key) { continue; }
                            $title = $activityMap[$key]['title'] ?? ($item['title'] ?? '');
                            $defaultOption = $this->normalizeOption($activityMap[$key]['option'] ?? []);
                            $optionIncoming = $this->normalizeOption($item['option'] ?? []);
                            $option = $this->mergeActivityOption($defaultOption, $optionIncoming);
                            // 后台保存双位置弹窗控制，兼容旧 popup_enabled
                            $popupEnabled = $this->toBoolInt($item['popup_enabled'] ?? 0);
                            $flags = [
                                'enabled' => $this->toBoolInt($item['enabled'] ?? true),
                                'popup_enabled_home' => $this->toBoolInt($item['popup_enabled_home'] ?? $popupEnabled),
                                'popup_enabled_recharge' => $this->toBoolInt($item['popup_enabled_recharge'] ?? $popupEnabled),
                                'popup_order_home' => isset($item['popup_order_home']) ? (int)$item['popup_order_home'] : 0,
                                'popup_order_recharge' => isset($item['popup_order_recharge']) ? (int)$item['popup_order_recharge'] : 0,
                            ];
                            $activity[] = array_merge([
                                'key' => $key,
                                'title' => $title,
                                'option' => $option,
                            ], $flags);
                        }
                    }
                } elseif (isset($data['activity_list'])) {
                    foreach ($data['activity_list'] as $k => $v) {
                        $activityInfo = Activity::where('type', $v)->find();
                        if ($activityInfo) {
                            $defaultOption = $activityInfo['config'] ? json_decode($activityInfo['config'], true) : [];
                            $opt = $this->mergeActivityOption($defaultOption, []);
                            $flags = $this->extractFlagsFromOption($opt);
                            $activity[] = array_merge([
                                'key' => $activityInfo['type'],
                                'title' => $activityInfo['name'],
                                'option' => $opt,
                            ], $flags);
                        }
                    }
                } else {
                    $activityList = Activity::select();
                    foreach ($activityList as $v) {
                        $defaultOption = $v['config'] ? json_decode($v['config'], true) : [];
                        $opt = $this->mergeActivityOption($defaultOption, []);
                        $flags = $this->extractFlagsFromOption($opt);
                        $activity[] = array_merge([
                            'key' => $v['type'],
                            'title' => $v['name'],
                            'option' => $opt,
                        ], $flags);
                    }
                }
                $data['activity'] = json_encode($activity, JSON_UNESCAPED_UNICODE);
                $result = $this->model->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Added successfully'));
            } else {
                $this->error(__('No rows were added'));
            }
        }

        $this->error(__('Parameter error'));
    }

    public function edit(): void
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }

        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds && !in_array($row[$this->dataLimitField], $dataLimitAdminIds)) {
            $this->error(__('You have no permission'));
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data   = $this->excludeFields($data);
            // 兼容并规范化前端传入的 activity：解码、合并 slot_activity 的 title/option
            if (isset($data['activity'])) {
                $raw = $data['activity'];
                if (is_string($raw)) {
                    $raw = html_entity_decode($raw, ENT_QUOTES);
                    $raw = stripslashes($raw);
                    $raw = json_decode($raw, true);
                }
                if (is_array($raw)) {
                    $allActivities = Activity::select();
                    $activityMap = [];
                    foreach ($allActivities as $a) {
                        $activityMap[$a['type']] = [
                            'title' => $a['name'],
                            'option' => $a['config'] ? json_decode($a['config'], true) : [],
                            'is_bet_multiplier' => (int)($a['is_bet_multiplier'] ?? 1),
                            'is_sidebar' => (int)($a['is_sidebar'] ?? 1),
                            'group' => $a['group'] ?? 'null',
                        ];
                    }
                    $normalized = [];
                    foreach ($raw as $item) {
                        $key = $item['key'] ?? ($item['type'] ?? null);
                        if (!$key) { continue; }
                        $title = $activityMap[$key]['title'] ?? ($item['title'] ?? '');
                        $defaultOption = $this->normalizeOption($activityMap[$key]['option'] ?? []);
                        $optionIncoming = $this->normalizeOption($item['option'] ?? []);
                        $option = $this->mergeActivityOption($defaultOption, $optionIncoming);
                        $popupEnabled = $this->toBoolInt($item['popup_enabled'] ?? 0);
                        
                        // 获取活动的打码倍率控制状态
                        $isBetMultiplier = $activityMap[$key]['is_bet_multiplier'] ?? 1;
                        
                        // 只有当 is_bet_multiplier 开启时才在 option 中包含 bet_multiplier 字段
                        if ($isBetMultiplier != 1) {
                            unset($option['bet_multiplier']);
                        } else {
                            // 确保 bet_multiplier 有默认值
                            if (!isset($option['bet_multiplier'])) {
                                $option['bet_multiplier'] = 1.0;
                            }
                        }
                        
                        $flags = [
                            'enabled' => $this->toBoolInt($item['enabled'] ?? true),
                            'popup_enabled_home' => $this->toBoolInt($item['popup_enabled_home'] ?? $popupEnabled),
                            'popup_enabled_recharge' => $this->toBoolInt($item['popup_enabled_recharge'] ?? $popupEnabled),
                            'popup_order_home' => isset($item['popup_order_home']) ? (int)$item['popup_order_home'] : 0,
                            'popup_order_recharge' => isset($item['popup_order_recharge']) ? (int)$item['popup_order_recharge'] : 0,
                            // 添加侧边栏和分组字段
                            'is_sidebar' => $activityMap[$key]['is_sidebar'] ?? 1,
                            'group' => $activityMap[$key]['group'] ?? 'null',
                        ];
                        
                        $normalized[] = array_merge([
                            'key' => $key,
                            'title' => $title,
                            'option' => $option,
                        ], $flags);
                    }
                    $data['activity'] = json_encode($normalized, JSON_UNESCAPED_UNICODE);
                }
            }
            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('edit');
                        $data[$pk] = $row[$pk];
                        $validate->check($data);
                    }
                }
                $result = $row->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }

        if  (isset($row["activity"])) {
            $row["activity"] = json_decode($row["activity"],true);
        }

        // 将 slot_activity 的活动信息（key、title、option）合并到 activity 字段
        $allActivities = Activity::order('sort asc, id asc')->select();
        $activityMap = [];
        foreach ($allActivities as $a) {
            $activityMap[$a["type"]] = [
                "title" => $a["name"],
                "option" => $a["config"] ? json_decode($a["config"], true) : [],
                "is_bet_multiplier" => (int)($a["is_bet_multiplier"] ?? 1),
                "is_sidebar" => (int)($a["is_sidebar"] ?? 1),
                "group" => $a["group"] ?? "null",
            ];
        }
        $mergedActivity = [];
        if (!empty($row["activity"]) && is_array($row["activity"])) {
            foreach ($row["activity"] as $item) {
                $key = $item["key"] ?? ($item["type"] ?? null);
                if (!$key) { continue; }
                $title = $activityMap[$key]["title"] ?? ($item["title"] ?? "");
                $defaultOption = $this->normalizeOption($activityMap[$key]["option"] ?? []);
                $optionIncoming = $this->normalizeOption($item["option"] ?? []);
                $option = $this->mergeActivityOption($defaultOption, $optionIncoming);
                
                // 获取活动的打码倍率控制状态
                $isBetMultiplier = $activityMap[$key]['is_bet_multiplier'] ?? 1;
                
                // 只有当 is_bet_multiplier 开启时才在 option 中包含 bet_multiplier 字段
                if ($isBetMultiplier != 1) {
                    unset($option['bet_multiplier']);
                } else {
                    // 确保 bet_multiplier 有默认值
                    if (!isset($option['bet_multiplier'])) {
                        $option['bet_multiplier'] = 1.0;
                    }
                }
                
                $flags = [
                    'enabled' => $this->toBoolInt($item['enabled'] ?? true),
                    // 兼容旧 popup_enabled 字段
                    'popup_enabled_home' => $this->toBoolInt($item['popup_enabled_home'] ?? ($item['popup_enabled'] ?? 0)),
                    'popup_enabled_recharge' => $this->toBoolInt($item['popup_enabled_recharge'] ?? ($item['popup_enabled'] ?? 0)),
                    'popup_order_home' => isset($item['popup_order_home']) ? (int)$item['popup_order_home'] : 0,
                    'popup_order_recharge' => isset($item['popup_order_recharge']) ? (int)$item['popup_order_recharge'] : 0,
                    // 添加侧边栏和分组字段
                    'is_sidebar' => $activityMap[$key]['is_sidebar'] ?? 1,
                    'group' => $activityMap[$key]['group'] ?? 'null',
                ];
                
                $mergedActivity[] = array_merge([
                    "key" => $key,
                    "title" => $title,
                    "option" => $option,
                ], $flags);
            }
        }
        $row["activity"] = $mergedActivity;

        // 获取 slot_activity 表中的活动配置，用于前端显示
        $activityList = Activity::select();
        $availableActivities = [];
        foreach ($activityList as $v) {
            $availableActivities[] = [
                "key" => $v["type"],
                "title" => $v["name"],
                "option" => $v["config"] ? json_decode($v["config"],true) : [],
                // 能力：若旧 is_popup=1，则默认视为首页&充值均可弹
                "is_popup_home" => isset($v["is_popup_home"]) ? (int)$v["is_popup_home"] : (isset($v["is_popup"]) ? (int)$v["is_popup"] : 0),
                "is_popup_recharge" => isset($v["is_popup_recharge"]) ? (int)$v["is_popup_recharge"] : (isset($v["is_popup"]) ? (int)$v["is_popup"] : 0),
                // 新增字段
                "is_sidebar" => (int)($v["is_sidebar"] ?? 1),
                "is_bet_multiplier" => (int)($v["is_bet_multiplier"] ?? 1),
                "group" => $v["group"] ?? "null",
            ];
        }
        $row["available_activities"] = $availableActivities;

        $this->success('', [
            'row' => $row
        ]);
    }

    /**
     * 通用渠道列表接口
     */
    public function all(): void
    {
        $list = Db::name('channel_list')->field('id, name')->order('id desc')->select()->toArray();
        $this->success('success', $list);
    }

	/**
	 * 返回所有可用活动（用于新增表单）
	 */
	public function availableActivities(): void
	{
		$activityList = Activity::order('sort asc, id asc')->select();
		$availableActivities = [];
		foreach ($activityList as $v) {
			$option = $v["config"] ? json_decode($v["config"], true) : [];
			$isBetMultiplier = (int)($v["is_bet_multiplier"] ?? 1);
			
			// 只有当 is_bet_multiplier 开启时才在 option 中包含 bet_multiplier 字段
			if ($isBetMultiplier != 1) {
				unset($option['bet_multiplier']);
			} else {
				// 确保 bet_multiplier 有默认值
				if (!isset($option['bet_multiplier'])) {
					$option['bet_multiplier'] = 1.0;
				}
			}
			
			$activityData = [
				"key" => $v["type"],
				"title" => $v["name"],
				"option" => $option,
				"is_popup" => isset($v["is_popup"]) ? (int)$v["is_popup"] : 0,
				// 新增字段
				"is_sidebar" => (int)($v["is_sidebar"] ?? 1),
				"is_bet_multiplier" => $isBetMultiplier, // 保留用于前端控制显示
				"group" => $v["group"] ?? "null",
			];
			
			$availableActivities[] = $activityData;
		}
		$this->success('', [ 'available_activities' => $availableActivities ]);
	}
}