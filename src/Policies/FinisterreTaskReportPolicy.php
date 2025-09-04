<?php

namespace Buzkall\Finisterre\Policies;

use Buzkall\Finisterre\Models\FinisterreTask;
use Illuminate\Contracts\Auth\Authenticatable;

class FinisterreTaskReportPolicy
{
    public function before(Authenticatable $user): ?bool
    {
        if ($expression = config('finisterre.restrict_task_reports_callback')) {
            if ($this->evaluateExpression($expression, $user)) {
                return false;
            }
        }

        return null;
    }

    private function evaluateExpression(string $expression, Authenticatable $user): bool
    {
        // Parse the expression safely
        if (preg_match('/^\$user->hasRole\(([^)]+)\)$/', $expression, $matches)) {
            $role = trim($matches[1], '"\'');
            // Handle enum references
            if (str_contains($role, '::')) {
                $role = $this->resolveEnumValue($role);
            }
            return method_exists($user, 'hasRole') ? /** @phpstan-ignore-next-line */ $user->hasRole($role) : false;
        }

        if (preg_match('/^\$user->hasPermission\(([^)]+)\)$/', $expression, $matches)) {
            $permission = trim($matches[1], '"\'');
            return method_exists($user, 'hasPermission') ? /** @phpstan-ignore-next-line */ $user->hasPermission($permission) : false;
        }

        if ($expression === '$user->isAdmin()') {
            return method_exists($user, 'isAdmin') ? /** @phpstan-ignore-next-line */ $user->isAdmin() : false;
        }

        throw new \InvalidArgumentException("Unsupported expression: {$expression}");
    }

    private function resolveEnumValue(string $enumReference): string
    {
        if ($enumReference === 'App\Enums\RoleEnum::Admin') {
            return 'admin';
        }
        
        throw new \InvalidArgumentException("Unsupported enum reference: {$enumReference}");
    }

    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, FinisterreTask $finisterreTask): bool
    {
        return true;
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function update(Authenticatable $user, FinisterreTask $finisterreTask): bool
    {
        return true;
    }

    public function delete(Authenticatable $user, FinisterreTask $finisterreTask): bool
    {
        return false;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function restore(Authenticatable $user, FinisterreTask $finisterreTask): bool
    {
        return false;
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return false;
    }

    public function forceDelete(Authenticatable $user, FinisterreTask $finisterreTask): bool
    {
        return false;
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return false;
    }
}
