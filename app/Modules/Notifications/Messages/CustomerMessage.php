<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Messages;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * What a customer was texted, kept for «اعلان‌ها» in their account (owner, 2026-11-21): the SMS template, a title, the
 * text and the page it is about (their order).
 */
final class CustomerMessage extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $template,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{template: string, title: string, body: string, url: string|null}
     */
    public function toDatabase(object $notifiable): array
    {
        return ['template' => $this->template, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
