<?php

namespace App\Services\GameWorld;

use App\Services\GameAccounts\InterludeClassNames;

final class InterludeCharacterLabels
{
    public function __construct(private readonly InterludeClassNames $classes) {}

    public function className(int $classId): string
    {
        return $this->classes->name($classId);
    }

    public function raceName(int $race): string
    {
        return match ($race) {
            0 => __('Human'),
            1 => __('Elf'),
            2 => __('Dark Elf'),
            3 => __('Orc'),
            4 => __('Dwarf'),
            5 => __('Kamael'),
            default => __('Unknown race'),
        };
    }

    public function genderName(int $gender): string
    {
        return match ($gender) {
            0 => __('Male'),
            1 => __('Female'),
            default => __('Unknown gender'),
        };
    }
}
