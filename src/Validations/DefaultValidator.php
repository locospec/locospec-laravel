<?php

namespace Locospec\LLCS\Validations;

use Illuminate\Support\Facades\Validator;
use Locospec\Engine\Registry\ValidatorInterface;

class DefaultValidator implements ValidatorInterface
{
    /**
     * Validate input data based on a given JSON schema.
     *
     * @param  array  $input  The input data to validate.
     * @param  array  $attributes  The JSON attributes with validation rules.
     * @param  string  $currentOperation  The current operation (e.g., "_create", "_update", etc.).
     * @return mixed True if validation passes, or a collection of errors.
     */
    public function validate(array $input, array $attributes, string $currentOperation)
    {
        $rules = [];
        $messages = [];

        // dump(["input" => $input, "schema" => $schema]);

        foreach ($attributes as $field => $definition) {
            // dump(["field" => $field, "definition" => $definition]);
            $fieldRules = [];
            $validations = $definition->getValidations();
            if (isset($validations) && is_array($validations)) {
                foreach ($validations as $validation) {
                    // If the validation has an 'operations' key, only apply if the current operation is allowed.
                    if (isset($validation->operations) && is_array($validation->operations)) {
                        if (! in_array($currentOperation, $validation->operations)) {
                            continue;
                        }
                    }

                    // Allow users to directly specify a Laravel validation rule
                    if (isset($validation->type)) {
                        $fieldRules[] = $validation->type;
                        // Determine the rule name (e.g., "min" from "min:3") for message assignment.
                        $ruleName = explode(':', $validation->type)[0];
                        if (isset($validation->message)) {
                            $messages["{$field}.{$ruleName}"] = $validation->message;
                        }
                    }
                }
            }
            if (! empty($fieldRules)) {
                // Join multiple rules with a pipe (e.g., "required|min:3|regex:pattern").
                $rules[$field] = implode('|', $fieldRules);
            }
        }
        // dump(["input" => $input, "rules" => $rules, "messages" => $messages]);
        // Create the Laravel validator instance.
        $validator = Validator::make($input, $rules, $messages);
        // dump(["validator" => $validator]);

        if ($validator->fails()) {
            // Return the validation errors.
            return $validator->errors();
        }

        return true;
    }
}
