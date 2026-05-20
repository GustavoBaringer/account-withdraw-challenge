<?php

declare(strict_types=1);

namespace App\Mail\Notification;

use App\Model\Account;
use App\Model\AccountWithdraw;

class WithdrawCompletedNotification extends AbstractNotification
{
    public function __construct(
        private readonly Account $account,
        private readonly AccountWithdraw $withdrawal,
    ) {}

    public function getTo(): string
    {
        return $this->withdrawal->pix?->key ?? '';
    }

    public function getSubject(): string
    {
        return 'Confirmação de saque PIX — Tecnofit';
    }

    public function getHtmlBody(): string
    {
        $amount = number_format((float) $this->withdrawal->amount, 2, ',', '.');
        $processedAt = date('d/m/Y H:i:s', strtotime($this->withdrawal->processed_at ?? date('Y-m-d H:i:s')));
        $pixType = $this->withdrawal->pix?->type ?? '';
        $pixKey = $this->withdrawal->pix?->key ?? '';
        $accountName = htmlspecialchars($this->account->name, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
                .container { background: #fff; max-width: 600px; margin: 0 auto; padding: 30px; border-radius: 8px; }
                .header { background: #0066cc; color: #fff; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
                .content { padding: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
                .label { color: #666; font-size: 14px; }
                .value { font-weight: bold; color: #333; }
                .amount { font-size: 28px; color: #0066cc; font-weight: bold; text-align: center; margin: 20px 0; }
                .footer { color: #999; font-size: 12px; text-align: center; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Saque PIX Realizado</h1>
                </div>
                <div class="content">
                    <p>Olá, <strong>{$accountName}</strong>!</p>
                    <p>Seu saque via PIX foi processado com sucesso.</p>
                    <div class="amount">R$ {$amount}</div>
                    <div class="detail-row">
                        <span class="label">Data/Hora do Processamento</span>
                        <span class="value">{$processedAt}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Tipo de Chave PIX</span>
                        <span class="value">{$pixType}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Chave PIX</span>
                        <span class="value">{$pixKey}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">ID da Operação</span>
                        <span class="value">{$this->withdrawal->id}</span>
                    </div>
                </div>
                <div class="footer">
                    <p>Este é um e-mail automático. Não responda.</p>
                    <p>Tecnofit — Plataforma de Conta Digital</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    public function getTextBody(): string
    {
        $amount = number_format((float) $this->withdrawal->amount, 2, ',', '.');
        $processedAt = date('d/m/Y H:i:s', strtotime($this->withdrawal->processed_at ?? date('Y-m-d H:i:s')));

        return <<<TEXT
        Saque PIX Realizado — Tecnofit

        Olá, {$this->account->name}!

        Seu saque foi processado com sucesso.

        Valor: R$ {$amount}
        Data/Hora: {$processedAt}
        Tipo de Chave: {$this->withdrawal->pix?->type}
        Chave PIX: {$this->withdrawal->pix?->key}
        ID da Operação: {$this->withdrawal->id}
        TEXT;
    }
}
