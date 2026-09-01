<?php

namespace Arzcode\Finisterre\Enums;

enum SubtaskChangeActionEnum: string
{
    case Added = 'added';
    case Renamed = 'renamed';
    case Completed = 'completed';
    case Uncompleted = 'uncompleted';
    case Deleted = 'deleted';

    /**
     * The digest line for this action.
     *
     * Deliberately not HasEnumFunctions: its getLabel() resolves root-level
     * `finisterre::finisterre.{CaseName}` keys, and these five labels belong
     * under the feature's own namespace rather than the top level.
     *
     * @param  array<string, string>  $replace
     */
    public function line(array $replace): string
    {
        return __('finisterre::finisterre.subtask_changes.actions.' . $this->value, $replace);
    }
}
