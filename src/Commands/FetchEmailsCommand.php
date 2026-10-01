<?php

namespace Arzcode\Finisterre\Commands;

use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Support\InboundEmail\AuthenticationResults;
use Arzcode\Finisterre\Support\InboundEmail\InboundMessage;
use Arzcode\Finisterre\Support\InboundEmail\InboundMessageHandler;
use Arzcode\Finisterre\Support\Typed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Header;
use Webklex\PHPIMAP\Message;

/**
 * The IMAP driver of reply by email: reads the unread messages of the configured
 * mailbox and turns the replies to task emails into comments. Scheduled every
 * minute while reply by email is on with this driver.
 */
class FetchEmailsCommand extends Command
{
    public $signature = 'finisterre:fetch-emails';
    public $description = 'Turn the replies to Finisterre task emails waiting in the IMAP mailbox into comments';

    public function handle(InboundMessageHandler $handler): int
    {
        if (! config('finisterre.mail.inbound.enabled') || config('finisterre.mail.inbound.driver') !== 'imap') {
            $this->info('Reply by email through IMAP is off.');

            return self::SUCCESS;
        }

        if (! class_exists(ClientManager::class)) {
            $this->error('Reply by email through IMAP needs webklex/php-imap: composer require webklex/php-imap');

            return self::FAILURE;
        }

        try {
            $client = (new ClientManager)->make($this->imapConfig());
            $client->connect();

            $folderPath = Typed::string(config('finisterre.mail.inbound.imap.folder', 'INBOX'));
            $folder = $client->getFolderByPath($folderPath);

            if (! $folder instanceof Folder) {
                $this->error('The IMAP folder ' . $folderPath . ' does not exist.');

                return self::FAILURE;
            }

            $messages = $folder->query()->whereUnseen()->leaveUnread()->get();
        } catch (Throwable $throwable) {
            Log::error('Finisterre: could not read the IMAP mailbox', ['error' => $throwable->getMessage()]);
            $this->error('Could not read the IMAP mailbox: ' . $throwable->getMessage());

            return self::FAILURE;
        }

        $created = 0;

        foreach ($messages as $message) {
            if (! $message instanceof Message) {
                continue;
            }

            try {
                if ($handler->handle($this->toInboundMessage($message)) instanceof FinisterreTaskComment) {
                    $created++;
                }

                // Skipped messages are marked read too, or they'd be read again every
                // minute. A failure leaves the message unread, to retry next time.
                $message->setFlag('Seen');
            } catch (Throwable $e) {
                Log::error('Finisterre: could not turn an email into a comment', [
                    'message_id' => Typed::string($message->getHeader()?->get('message_id')),
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $client->disconnect();

        $this->info("Created {$created} comment(s) from {$messages->count()} email(s).");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    protected function imapConfig(): array
    {
        $encryption = Typed::string(config('finisterre.mail.inbound.imap.encryption', 'ssl'));

        return [
            'host'          => Typed::string(config('finisterre.mail.inbound.imap.host')),
            'port'          => Typed::int(config('finisterre.mail.inbound.imap.port', 993)),
            'encryption'    => in_array($encryption, ['', 'none'], true) ? false : $encryption,
            'validate_cert' => true,
            'username'      => Typed::string(config('finisterre.mail.inbound.imap.username')),
            'password'      => Typed::string(config('finisterre.mail.inbound.imap.password')),
            'protocol'      => 'imap',
        ];
    }

    protected function toInboundMessage(Message $message): InboundMessage
    {
        $header = $message->getHeader();
        $value = fn(string $name): string => $header instanceof Header ? trim(Typed::string($header->get($name))) : '';
        $from = $message->getFrom()->first();

        return new InboundMessage(
            messageId: trim($value('message_id'), '<> ') ?: null,
            from: $from instanceof Address ? $from->mail : null,
            recipients: array_values(array_filter(array_map(
                $value,
                ['to', 'cc', 'delivered_to', 'x_original_to', 'envelope_to']
            ))),
            html: $message->getHTMLBody(),
            text: $message->getTextBody(),
            headers: collect(['in-reply-to', 'references', 'auto-submitted', 'x-autoreply', 'x-autorespond', 'precedence', 'return-path'])
                ->mapWithKeys(fn(string $name) => [$name => $value($name)])
                ->put('authentication-results', $header instanceof Header ? AuthenticationResults::fromRawHeaders($header->raw) : '')
                ->all(),
        );
    }
}
