<?php

namespace LCSLaravel\Validations;

use Illuminate\Support\Facades\Validator;
use LCSEngine\Registry\ValidatorInterface;

class DefaultValidator implements ValidatorInterface
{
    /**
     * Validate input data based on a given JSON schema.
     *
     * @param  array  $input  The input data to validate.
     * @param  array  $attributes  The JSON attributes with validation rules.
     * @param  string  $options  The array of options: action and dbOps.
     * @return mixed True if validation passes, or a collection of errors.
     */
    public function validate(array $input, array $attributes, array $options = [])
    {

        $rules = [];
        $messages = [];
        $customRules = [];

        try {
            foreach ($attributes as $field => $definition) {
                $fieldRules = [];
                $validations = $definition->getValidations();
                if (isset($validations) && is_array($validations)) {
                    foreach ($validations as $validation) {
                        // If the validation has an 'operations' key, only apply if the current operation is allowed.
                        if (isset($validation->operations) && is_array($validation->operations)) {
                            if (! in_array($options['action'], $validation->operations)) {
                                continue;
                            }
                        }

                        // Allow users to directly specify a Laravel validation rule
                        if (isset($validation->type)) {
                            switch ($validation->type) {
                                case 'unique':
                                case 'exists':
                                    $customRules[$field][] = [
                                        'rule' => $validation->type,
                                        'message' => $validation->message ?? "Validation failed for custom rule: {$validation->type}",
                                    ];
                                    if (isset($validation->model)) {
                                        $options['modelName'] = $validation->model;
                                    }

                                    if (isset($validation->with)) {
                                        $options['with'] = $validation->with;
                                    }
                                    break;

                                default:
                                    // code...
                                    $fieldRules[] = $validation->type;
                                    // Determine the rule name (e.g., "min" from "min:3") for message assignment.
                                    $ruleName = explode(':', $validation->type)[0];
                                    if (isset($validation->message)) {
                                        $messages["{$field}.{$ruleName}"] = $validation->message;
                                    }
                                    break;
                            }
                        }
                    }
                }
                if (! empty($fieldRules)) {
                    // Join multiple rules with a pipe (e.g., "required|min:3|regex:pattern").
                    $rules[$field] = implode('|', $fieldRules);
                }
            }

            // Create the Laravel validator instance.
            $validator = Validator::make($input, $rules, $messages);
            $errors = $validator->errors();

            // Now process each custom rule.
            foreach ($customRules as $field => $rulesData) {
                $value = $input[$field] ?? null;
                $options['attributeName'] = $field;
                foreach ($rulesData as $customRule) {
                    $customRuleName = $customRule['rule'];
                    $options['input'] = $input;
                    if (! $this->validateCustomRule($customRuleName, $value, $options)) {
                        $errors->add($field, $customRule['message']);
                    }
                }
            }

            if ($errors->isNotEmpty()) {
                return $errors;
            }

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Dispatch the custom validation to the appropriate method.
     *
     * @param  string  $rule  The custom rule name (without the "custom:" prefix).
     * @param  mixed  $value  The value to validate.
     * @return mixed True if validation passes, false otherwise.
     */
    public function validateCustomRule(string $rule, $value, array $options)
    {
        switch ($rule) {
            case 'unique':
                $filters = [
                    'op' => 'and',
                    'conditions' => [
                        [
                            'attribute' => $options['attributeName'],
                            'op' => 'is',
                            'value' => $value,
                        ],
                    ],
                ];

                if (isset($options['with'])) {
                    foreach ($options['with'] as $value) {
                        $filters['conditions'][] = [
                            'attribute' => $value,
                            'op' => 'is',
                            'value' => $options['input'][$value],
                        ];
                    }
                }

                $options['dbOps']->add([
                    'type' => 'select',
                    'modelName' => $options['modelName'],
                    'filters' => $filters,
                ]);
                $response = $options['dbOps']->execute($options['dbOperator']);
                $isUnique = empty($response[0]['result']);

                return $isUnique;

            case 'exists':
                $options['dbOps']->add([
                    'type' => 'select',
                    'modelName' => $options['modelName'],
                    'filters' => [
                        'op' => 'and',
                        'conditions' => [
                            [
                                'attribute' => $options['attributeName'],
                                'op' => 'is',
                                'value' => $value,
                            ],
                        ],
                    ],
                ]);

                $response = $options['dbOps']->execute($options['dbOperator']);
                $isUnique = ! empty($response[0]['result']);

                return $isUnique;

                // Add more cases for additional custom validations.
            default:
                throw new RuntimeException("Custom rule '{$rule}' is not implemented.");
        }
    }
}
