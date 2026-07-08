<?php

namespace Arzcode\Finisterre\Filament\Actions;

use Arzcode\Finisterre\Filament\Livewire\FinisterreCommentsComponent;
use Filament\Actions\Action;

class PostponeCommentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'postponeComment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Hidden shortcut (alt+y): postpones the comment notification to a
        // random minute 4 or 5 hours from now, kept within working hours. If
        // that lands at/after 21:00 it rolls to the next morning; if it lands
        // before 07:00 it starts from 07:00 that day. Either way the time is
        // 07:00 plus the same offset.
        $this->keyBindings(['alt+y'])
            ->action(function(FinisterreCommentsComponent $livewire): void {
                $offset = random_int(240, 300);

                $scheduledFor = now()->addMinutes($offset)->setSeconds(0);

                if ($scheduledFor->hour >= 21) {
                    $scheduledFor = $scheduledFor->addDay()->setTime(7, 0)->addMinutes($offset);
                } elseif ($scheduledFor->hour < 7) {
                    $scheduledFor = $scheduledFor->setTime(7, 0)->addMinutes($offset);
                }

                // Never land exactly on the hour.
                if ($scheduledFor->minute === 0) {
                    $scheduledFor->addMinute();
                }

                $livewire->data['scheduled_for'] = $scheduledFor->format('Y-m-d H:i:s');
            });
    }
}
