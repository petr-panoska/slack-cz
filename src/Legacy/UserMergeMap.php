<?php

namespace App\Legacy;

use App\Entity\User;
use App\Repository\UserRepository;

/**
 * Resolves a legacy uzivatel.id to the canonical new User after import.
 *
 * Lazily loads the merge map from the User table on first lookup. Used by
 * crossings/first-ascent imports so that historical references to merged
 * legacy IDs are remapped to the surviving User row.
 */
final class UserMergeMap
{
    /** @var array<int, User>|null */
    private ?array $map = null;

    public function __construct(private readonly UserRepository $users)
    {
    }

    public function find(int $legacyId): ?User
    {
        return $this->loaded()[$legacyId] ?? null;
    }

    public function reset(): void
    {
        $this->map = null;
    }

    /**
     * @return array<int, User>
     */
    private function loaded(): array
    {
        if ($this->map === null) {
            $this->map = [];
            foreach ($this->users->findAll() as $user) {
                if ($user->getLegacyId() !== null) {
                    $this->map[$user->getLegacyId()] = $user;
                }
                foreach ($user->getLegacyMergedIds() as $mergedId) {
                    $this->map[$mergedId] = $user;
                }
            }
        }
        return $this->map;
    }
}
