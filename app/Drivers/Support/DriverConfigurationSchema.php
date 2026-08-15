<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Support;

/**
 * A driver's configuration schema, so connection forms are generated rather than
 * hand-written per driver (spec §5).
 */
final readonly class DriverConfigurationSchema
{
    /**
     * @param  list<ConfigurationField>  $fields
     */
    public function __construct(public array $fields) {}

    /**
     * @return list<ConfigurationField>
     */
    public function secretFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (ConfigurationField $field): bool => $field->secret,
        ));
    }

    public function field(string $name): ?ConfigurationField
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }
}
