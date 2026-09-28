<?php

namespace Arzcode\Finisterre\Notifications\Concerns;

use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Facades\Filament;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

trait UsesFinisterreMailLayout
{
    protected function newMailMessage(FinisterreTask $task): MailMessage
    {
        return (new MailMessage)
            ->theme('finisterre::themes.finisterre')
            ->markdown('finisterre::mail.email', ['logo' => $this->mailLogo()])
            ->withSymfonyMessage(fn(Email $message) => $this->threadByTask($message, $task));
    }

    /**
     * The brand logo of the panel Finisterre lives in, as an absolute URL mail
     * clients can load. Inline (Htmlable) logos are skipped: they are usually
     * SVG markup, which most mail clients don't render.
     */
    protected function mailLogo(): ?string
    {
        if (! app()->bound('filament')) {
            return null;
        }

        $logo = (Filament::getPanels()[config('finisterre.panel_slug')] ?? null)?->getBrandLogo();

        return is_string($logo) && filled($logo) ? url($logo) : null;
    }

    /**
     * Point every email about a task at the same thread root, so mail clients
     * that thread by headers group them into one conversation. The Message-ID
     * itself stays unique; Symfony sets it.
     */
    protected function threadByTask(Email $message, FinisterreTask $task): void
    {
        $threadId = 'finisterre-task-' . $task->getKey() . '@' . (parse_url((string)config('app.url'), PHP_URL_HOST) ?: 'localhost');
        $headers = $message->getHeaders();

        $headers->addIdHeader('In-Reply-To', $threadId);
        $headers->addIdHeader('References', $threadId);
        // Keep out-of-office replies from answering every notification.
        $headers->addTextHeader('X-Auto-Response-Suppress', 'All');
    }
}
