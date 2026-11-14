<?php

declare(strict_types=1);

namespace App\Modules\Shared\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The parent of every customer-facing (storefront and API) error: a Persian message for the customer and a stable
 * machine code. Laravel calls render(): JSON requests get {"message": …, "code": …} with the error's HTTP status;
 * page requests go back with the message as a form error. These are expected refusals, so they are not reported
 * (bootstrap/app.php). Admin-panel refusals do not extend it.
 */
abstract class BaseException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
    ) {
        parent::__construct($message);
    }

    /**
     * A stable snake_case code for clients, e.g. «otp_expired».
     */
    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array{message: string, code: string}
     */
    public function toArray(): array
    {
        return ['message' => $this->getMessage(), 'code' => $this->errorCode];
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return new JsonResponse($this->toArray(), $this->status);
        }

        return back()->withInput()->withErrors(['message' => $this->getMessage()]);
    }
}
