<?php

namespace Locospec\LLCS\Generators;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Locospec\Engine\Registry\GeneratorInterface;
use Locospec\LLCS\Facades\LLCS;

class DefaultGenerator implements GeneratorInterface
{
    /**
     * Generate the value based on type and options.
     *
     * @param  string  $type  The type of generator.
     * @param  array  $options  Additional options for the generator.
     * @return mixed The generated value.
     */
    public function generate(string $type, array $options = [])
    {
        try {
            switch ($type) {
                case 'uuid':
                    return Str::uuid()->toString();

                case 'uniqueSlugGenerator':
                    if (! isset($options['sourceValue'])) {
                        return null;
                    }

                    // Generate the slug.
                    $generatedSlug = Str::slug($options['sourceValue']);

                    $options['dbOps']->add([
                        'type' => 'select',
                        'modelName' => $options['modelName'],
                        'filters' => [
                            'op' => 'and',
                            'conditions' => [
                                [
                                    'attribute' => $options['attributeName'],
                                    'op' => 'contains',
                                    'value' => $options['sourceValue'],
                                ],
                            ],
                        ],
                    ]);
                    $response = $options['dbOps']->execute($options['dbOperator']);

                    // Extract existing slugs using array_column for optimization.
                    $existingSlugs = array_column($response[0]['result'] ?? [], $options['attributeName']);

                    // Ensure the slug is unique.
                    $uniqueSlug = $generatedSlug;
                    $counter = 1;
                    while (in_array($uniqueSlug, $existingSlugs)) {
                        $uniqueSlug = "{$generatedSlug}-{$counter}";
                        $counter++;
                    }

                    return $uniqueSlug;

                case 'datetime':
                    return isset($options['value']) && $options['value'] === 'now'
                            ? Carbon::now('UTC')->format('Y-m-d H:i:s.v') . '+00'
                            : Carbon::parse($options['value'])->format('Y-m-d H:i:s.v') . '+00';
                    // return isset($options['value']) && $options['value'] === 'now'
                    //     ? Carbon::now()->toDateTimeString()
                    //     : Carbon::parse($options['value'])->toDateTimeString();

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
                case 'stateMachine':
                    $result = LLCS::executeCustomAction(
                        LLCS::getDefaultValidator(),
                        LLCS::getDefaultGenerator(),
                        $options['modelName'],
                        $options
                    );

                    return $result['result'][$options['source']];
                default:
                    throw new \InvalidArgumentException("Unsupported generator type: {$type}");
            }
        } catch (\Exception $e) {
            dd('rajesh', $e);
            throw $e;
        }
    }
}
