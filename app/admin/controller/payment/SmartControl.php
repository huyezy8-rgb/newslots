<?php

namespace app\admin\controller\payment;

use app\common\controller\Backend;
use app\common\service\PaymentSmartControlService;

class SmartControl extends Backend
{
    protected array $noNeedPermission = ['options'];

    private PaymentSmartControlService $service;

    public function initialize(): void
    {
        parent::initialize();
        $this->service = new PaymentSmartControlService();
    }

    public function detail(): void
    {
        $this->success('', [
            'config' => $this->service->getConfig(),
            'options' => $this->buildOptions(),
        ]);
    }

    public function options(): void
    {
        $this->success('', $this->buildOptions());
    }

    public function edit(): void
    {
        if (!$this->request->isPost()) {
            $this->error('Invalid request method');
        }

        $data = $this->request->post();
        if (!$data) {
            $input = $this->request->getContent() ?: $this->request->getInput();
            $decoded = json_decode($input, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        try {
            $this->service->saveConfig($data);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }

        $this->success(__('Update successful'));
    }

    private function buildOptions(): array
    {
        return [
            'withdraw_pay_types' => $this->service->getWithdrawPayTypeOptions(),
            'recharge_pay_types' => $this->service->getRechargePayTypeOptions(),
        ];
    }
}
