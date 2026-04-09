<?php

namespace App\DTOs\Groups;

readonly class SyncGroupMembersDto
{
    /**
     * @param  list<string>  $userIds
     */
    public function __construct(
        public array $userIds,
    ) {}
}
