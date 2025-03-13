<?php

namespace Locospec\LLCS\Generators;

use Locospec\Engine\Registry\GeneratorInterface;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DefaultGenerator implements GeneratorInterface
{
    /**
     * Generate the value based on type and options.
     *
     * @param string $type The type of generator.
     * @param array $options Additional options for the generator.
     * @return mixed The generated value.
     */
    public function generate(string $type, array $options = [])
    {
        switch ($type) {
            case 'uuid':
                return Str::uuid()->toString();

            case 'slugGenerator':
                return isset($options['source']) ? Str::slug($options['source']) : null;

            case 'datetime':
                return isset($options['value']) && $options['value'] === 'now' 
                    ? Carbon::now()->toDateTimeString() 
                    : Carbon::parse($options['value'])->toDateTimeString();

            case 'boolean':
                return isset($options['default']) ? (bool) $options['default'] : false;

            case 'random_string':
                return Str::random($options['length'] ?? 10);

            case 'default_value':
                return $options['value'] ?? null;

            case 'enum':
                return isset($options['values']) && is_array($options['values']) 
                    ? $options['values'][0] 
                    : null;

            default:
                throw new \InvalidArgumentException("Unsupported generator type: {$type}");
        }
    }
}
