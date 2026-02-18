<?php

namespace Buzkall\Finisterre\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;

trait EmbedsPrivateImages
{
    protected array $inlineImages = [];

    protected function embedImages(string $html): string
    {
        $disk = config('finisterre.attachments_disk') ?? 'public';

        return preg_replace_callback(
            '/(src=["\'])(?:[^"\']*?)storage\/finisterre-files\/([^"\']+)(["\'])/i',
            function($matches) use ($disk) {
                $relativePath = $matches[2];

                if (! Storage::disk($disk)->exists($relativePath)) {
                    return $matches[0];
                }

                $cid = 'img-' . md5($relativePath);
                $this->inlineImages[$cid] = Storage::disk($disk)->path($relativePath);

                return $matches[1] . 'cid:' . $cid . $matches[3];
            },
            $html
        );
    }

    protected function withInlineImages(MailMessage $mail): MailMessage
    {
        if (! empty($this->inlineImages)) {
            $mail->withSymfonyMessage(function(Email $message) {
                foreach ($this->inlineImages as $cid => $path) {
                    $message->embedFromPath($path, $cid);
                }
            });
        }

        return $mail;
    }
}
