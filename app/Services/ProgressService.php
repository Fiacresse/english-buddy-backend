<?php

namespace App\Services;

use App\Models\User;

class ProgressService
{
    /** Enregistre une activité : temps de pratique, XP et série de jours (streak). */
    public function record(User $user, string $type, int $seconds, int $xp): void
    {
        if ($seconds > 0) {
            $user->practiceSessions()->create(['type' => $type, 'seconds' => $seconds]);
        }

        $today = now()->startOfDay();
        $last = $user->last_active_on?->copy()->startOfDay();

        if (! $last || $last->lt($today)) {
            $user->streak = ($last && $last->eq($today->copy()->subDay())) ? $user->streak + 1 : 1;
            $user->last_active_on = $today;
        }

        $user->xp += $xp;
        $user->save();
    }

    /** Streak affiché : remis à 0 si l'utilisateur a sauté un jour. */
    public function currentStreak(User $user): int
    {
        $last = $user->last_active_on;
        if (! $last) {
            return 0;
        }

        return $last->startOfDay()->gte(now()->subDay()->startOfDay()) ? $user->streak : 0;
    }
}
