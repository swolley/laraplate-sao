<?php

declare(strict_types=1);

namespace Modules\SAO\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\SAO\Enums\ChangeRefType;
use Modules\SAO\Models\ChangeRef;
use Modules\SAO\Models\Ticket;

/**
 * @extends Factory<ChangeRef>
 */
final class ChangeRefFactory extends Factory
{
    /**
     * @var class-string<ChangeRef>
     */
    protected $model = ChangeRef::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'type' => ChangeRefType::Commit,
            'identifier' => $this->faker->unique()->regexify('[0-9a-f]{40}'),
            'url' => $this->faker->url(),
            'source' => 'github',
        ];
    }
}
