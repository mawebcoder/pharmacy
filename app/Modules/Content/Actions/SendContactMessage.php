<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Data\ContactMessageData;
use App\Modules\Content\Enums\ContactMessageStatus;
use App\Modules\Content\Exceptions\ContactMessageRefused;
use App\Modules\Content\Models\ContactMessage;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Saves a message from /contact as new for staff (roadmap S34): at most 3 every 10 minutes from one IP and for one
 * mobile, refused with the minutes to wait (ContactMessageRefused). Staff are not told by SMS or email until S28.
 */
final class SendContactMessage
{
    public const int MAX_MESSAGES = 3;

    public const int WINDOW_SECONDS = 600;

    /**
     * @throws ContactMessageRefused
     */
    #[\NoDiscard]
    public function __invoke(ContactMessageData $data): ContactMessage
    {
        $keys = ['contact:ip:'.($data->ipAddress ?? 'unknown'), 'contact:mobile:'.$data->mobile];

        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, self::MAX_MESSAGES)) {
                throw ContactMessageRefused::tooMany(RateLimiter::availableIn($key));
            }
        }

        foreach ($keys as $key) {
            RateLimiter::hit($key, self::WINDOW_SECONDS);
        }

        $message = (new ContactMessage)
            ->setCustomerId($data->customerId)
            ->setName($data->name)
            ->setMobile($data->mobile)
            ->setEmail($data->email)
            ->setSubject($data->subject)
            ->setBody($data->body)
            ->setStatus(ContactMessageStatus::New)
            ->setIpAddress($data->ipAddress);
        $message->save();

        return $message;
    }
}
