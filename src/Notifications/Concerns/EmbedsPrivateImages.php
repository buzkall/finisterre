<?php

namespace Arzcode\Finisterre\Notifications\Concerns;

use Arzcode\Finisterre\Support\AttachmentsDisk;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;

trait EmbedsPrivateImages
{
    /** @var array<string, string> */
    protected array $inlineImages = [];

    protected function embedImages(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $disk = AttachmentsDisk::name();

        return preg_replace_callback(
            '/(src=["\'])(?:[^"\']*?)storage\/finisterre-files\/([^"\']+)(["\'])/i',
            function(array $matches) use ($disk): string {
                $relativePath = $matches[2];

                if (! Storage::disk($disk)->exists($relativePath)) {
                    return $matches[0];
                }

                $cid = 'img-' . md5($relativePath);
                $this->inlineImages[$cid] = Storage::disk($disk)->path($relativePath);

                return $matches[1] . 'cid:' . $cid . $matches[3];
            },
            $html
        ) ?? $html;
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
