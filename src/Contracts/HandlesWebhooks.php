<?php

declare(strict_types=1);

namespace MrRijal\LaravelSms\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface HandlesWebhooks
{
    /**
     * Verify and handle an incoming provider webhook.
     */
    public function handleWebhook(Request $request): Response;
}
