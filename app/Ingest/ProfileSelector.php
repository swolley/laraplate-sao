<?php

declare(strict_types=1);

namespace Modules\SAO\Ingest;

use Modules\SAO\Models\SourceProfile;

/**
 * Picks the first active source profile whose matchers all pass for a payload.
 */
final readonly class ProfileSelector
{
    public function __construct(private PayloadMatcher $matcher) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function select(array $payload): ?SourceProfile
    {
        return SourceProfile::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->first(fn (SourceProfile $profile): bool => $this->matcher->matches($profile, $payload));
    }
}
