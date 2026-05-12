<?php

namespace app\command;

use app\common\service\TeamPathService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

/**
 * 团队路径迁移命令
 */
class TeamPathMigration extends Command
{
    protected function configure()
    {
        $this->setName('team:path')
            ->setDescription('团队路径管理工具')
            ->addArgument('action', \think\console\input\Argument::REQUIRED, '操作类型: init/update/validate/statistics')
            ->addOption('user_id', 'u', \think\console\input\Option::VALUE_OPTIONAL, '指定用户ID');
    }

    protected function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');
        $userId = $input->getOption('user_id');

        $teamPathService = new TeamPathService();

        switch ($action) {
            case 'init':
                $this->initTeamPaths($output, $teamPathService);
                break;
            case 'update':
                $this->updateTeamPaths($output, $teamPathService, $userId);
                break;
            case 'validate':
                $this->validateTeamPaths($output, $teamPathService, $userId);
                break;
            case 'statistics':
                $this->showStatistics($output, $teamPathService, $userId);
                break;
            default:
                $output->error("未知操作: {$action}");
                $output->writeln("支持的操作: init, update, validate, statistics");
                break;
        }
    }

    /**
     * 初始化团队路径
     */
    private function initTeamPaths(Output $output, TeamPathService $service)
    {
        $output->writeln("开始初始化团队路径...");

        try {
            // 检查字段是否存在
            $this->checkTeamPathFields($output);

            // 重置所有团队路径（新规范：根路径为'/'）
            $output->writeln("重置现有团队路径...");
            Db::execute("UPDATE slot_account SET team_path = '/', team_level = 0");
            $output->writeln("重置完成");

            // 批量更新所有用户
            $result = $service->updateAllTeamPaths();
            
            if ($result) {
                $output->writeln("<info>团队路径初始化成功！</info>");
            } else {
                $output->writeln("<error>团队路径初始化失败！</error>");
            }

        } catch (\Exception $e) {
            $output->writeln("<error>初始化失败: " . $e->getMessage() . "</error>");
        }
    }

    /**
     * 更新团队路径
     */
    private function updateTeamPaths(Output $output, TeamPathService $service, $userId = null)
    {
        if ($userId) {
            $output->writeln("更新用户 {$userId} 的团队路径...");
            $result = $service->updateTeamPath((int)$userId);
            
            if ($result) {
                $output->writeln("<info>用户 {$userId} 团队路径更新成功！</info>");
            } else {
                $output->writeln("<error>用户 {$userId} 团队路径更新失败！</error>");
            }
        } else {
            $output->writeln("批量更新所有用户团队路径...");
            $result = $service->updateAllTeamPaths();
            
            if ($result) {
                $output->writeln("<info>批量更新成功！</info>");
            } else {
                $output->writeln("<error>批量更新失败！</error>");
            }
        }
    }

    /**
     * 验证团队路径
     */
    private function validateTeamPaths(Output $output, TeamPathService $service, $userId = null)
    {
        if ($userId) {
            $output->writeln("验证用户 {$userId} 的团队路径...");
            $result = $service->validateTeamPath((int)$userId);
            
            if ($result) {
                $output->writeln("<info>用户 {$userId} 团队路径验证通过！</info>");
            } else {
                $output->writeln("<error>用户 {$userId} 团队路径验证失败！</error>");
            }
        } else {
            $output->writeln("验证所有用户团队路径...");
            
            $users = Db::name('account')->select();
            $validCount = 0;
            $invalidCount = 0;
            $invalidUsers = [];

            foreach ($users as $user) {
                if ($service->validateTeamPath($user['id'])) {
                    $validCount++;
                } else {
                    $invalidCount++;
                    $invalidUsers[] = $user['id'];
                }
            }

            $output->writeln("验证结果:");
            $output->writeln("  有效用户: {$validCount}");
            $output->writeln("  无效用户: {$invalidCount}");
            
            if (!empty($invalidUsers)) {
                $output->writeln("  无效用户ID: " . implode(', ', array_slice($invalidUsers, 0, 10)));
                if (count($invalidUsers) > 10) {
                    $output->writeln("  ... 还有 " . (count($invalidUsers) - 10) . " 个");
                }
            }
        }
    }

    /**
     * 显示统计信息
     */
    private function showStatistics(Output $output, TeamPathService $service, $userId = null)
    {
        if ($userId) {
            $this->showUserStatistics($output, $service, (int)$userId);
        } else {
            $this->showGlobalStatistics($output, $service);
        }
    }

    /**
     * 显示用户统计信息
     */
    private function showUserStatistics(Output $output, TeamPathService $service, int $userId)
    {
        $user = Db::name('account')->where('id', $userId)->find();
        if (!$user) {
            $output->writeln("<error>用户 {$userId} 不存在！</error>");
            return;
        }

        $output->writeln("用户 {$userId} 统计信息:");
        $output->writeln("  团队路径: {$user['team_path']}");
        $output->writeln("  团队层级: {$user['team_level']}");
        $output->writeln("  上级ID: {$user['p_id']}");

        // 统计下级
        $directChildren = $service->getDirectChildren($userId, ['id', 'name']);
        $allChildren = $service->getAllChildren($userId, ['id', 'name']);
        
        $output->writeln("  直属下级: " . count($directChildren));
        $output->writeln("  所有下级: " . count($allChildren));

        // 统计上级
        $allParents = $service->getAllParents($userId, ['id', 'name']);
        $output->writeln("  所有上级: " . count($allParents));

        // 团队充值统计
        $teamRecharge = $service->getTeamRechargeAmount($userId);
        $output->writeln("  团队充值: " . number_format($teamRecharge, 2));

        // 显示直属下级
        if (!empty($directChildren)) {
            $output->writeln("  直属下级列表:");
            foreach ($directChildren as $child) {
                $output->writeln("    - ID: {$child['id']}, 姓名: {$child['name']}");
            }
        }
    }

    /**
     * 显示全局统计信息
     */
    private function showGlobalStatistics(Output $output, TeamPathService $service)
    {
        $output->writeln("全局团队统计信息:");

        // 总用户数
        $totalUsers = Db::name('account')->count();
        $output->writeln("  总用户数: {$totalUsers}");

        // 根节点用户数
        $rootUsers = Db::name('account')->where('p_id', 0)->count();
        $output->writeln("  根节点用户: {$rootUsers}");

        // 各层级用户数
        $levelStats = Db::name('account')
            ->field('team_level, COUNT(*) as count')
            ->group('team_level')
            ->order('team_level', 'asc')
            ->select();

        $output->writeln("  各层级用户分布:");
        foreach ($levelStats as $stat) {
            $output->writeln("    层级 {$stat['team_level']}: {$stat['count']} 人");
        }

        // 团队路径长度分布（以斜杠包裹：根'/'为1段，其它根据斜杠数-1计算）
        $pathLengthStats = Db::name('account')
            ->field('GREATEST(LENGTH(team_path) - LENGTH(REPLACE(team_path, "/", "")) - 1, 0) as path_length, COUNT(*) as count')
            ->group('path_length')
            ->order('path_length', 'asc')
            ->select();

        $output->writeln("  团队路径长度分布:");
        foreach ($pathLengthStats as $stat) {
            $output->writeln("    长度 {$stat['path_length']}: {$stat['count']} 人");
        }
    }

    /**
     * 检查团队路径字段
     */
    private function checkTeamPathFields(Output $output)
    {
        $output->writeln("检查团队路径字段...");

        $fields = ['team_path', 'team_level'];
        foreach ($fields as $field) {
            try {
                $result = Db::query("SHOW COLUMNS FROM slot_account LIKE '{$field}'");
                if (empty($result)) {
                    throw new \Exception("缺少字段: {$field}");
                }
                $output->writeln("  字段 {$field} 存在");
            } catch (\Exception $e) {
                throw new \Exception("字段检查失败: " . $e->getMessage());
            }
        }
    }
} 