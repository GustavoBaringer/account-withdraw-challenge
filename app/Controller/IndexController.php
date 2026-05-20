<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;

#[Controller]
class IndexController extends AbstractController
{
    #[GetMapping(path: '/')]
    public function index(): array
    {
        return ['status' => 'ok', 'service' => 'tecnofit-challenge'];
    }
}
