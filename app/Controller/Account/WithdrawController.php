<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Controller\AbstractController;
use App\Request\WithdrawRequest;
use App\Service\WithdrawService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\PostMapping;
use Psr\Http\Message\ResponseInterface;

#[Controller(prefix: '/account')]
class WithdrawController extends AbstractController
{
    public function __construct(
        private readonly WithdrawService $withdrawService,
    ) {}

    #[PostMapping(path: '{accountId}/balance/withdraw')]
    public function withdraw(string $accountId, WithdrawRequest $request): ResponseInterface
    {
        $data = $request->validated();

        $withdrawal = $this->withdrawService->withdraw($accountId, $data);

        $responseData = [
            'id' => $withdrawal->id,
            'account_id' => $withdrawal->account_id,
            'method' => $withdrawal->method,
            'amount' => $withdrawal->amount,
            'scheduled' => $withdrawal->scheduled,
            'scheduled_for' => $withdrawal->scheduled_for,
            'done' => $withdrawal->done,
            'created_at' => $withdrawal->created_at,
        ];

        return $this->response->json($responseData)->withStatus(201);
    }
}
