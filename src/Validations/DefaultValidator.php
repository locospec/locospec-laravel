<?php

namespace Locospec\LLCS\Validations;

use Illuminate\Support\Facades\Validator;
use Locospec\Engine\Registry\ValidatorInterface;

class DefaultValidator implements ValidatorInterface
{
     /**
     * Validate input data based on a given JSON schema.
     *
     * @param array $input  The input data to validate.
     * @param array $schema The JSON schema with validation rules.
     * @return mixed True if validation passes, or a collection of errors.
     */

    public function validate(array $input, array $schema)
    {
        $rules = [];
        $messages = [];
        
        dump(["input" => $input, "schema" => $schema]);

        foreach ($schema as $field => $definition) {
            dump(["field" => $field, "definition" => $definition]);
            $fieldRules = [];
            if (isset($definition['validations']) && is_array($definition['validations'])) {
                foreach ($definition['validations'] as $validation) {
                    // Allow users to directly specify a Laravel validation rule
                    if (isset($validation['rule'])) {
                        $fieldRules[] = $validation['rule'];
                        // Determine the rule name (e.g., "min" from "min:3") for message assignment.
                        $ruleName = explode(':', $validation['rule'])[0];
                        if (isset($validation['message'])) {
                            $messages["{$field}.{$ruleName}"] = $validation['message'];
                        }
                    } elseif (isset($validation['type'])) {
                        // Testing for custom types 
                        switch ($validation['type']) {
                            case 'required':
                                $fieldRules[] = 'required';
                                $messages["{$field}.required"] = $validation['message'] ?? "{$field} is required.";
                                break;
                            case 'regex':
                                if (isset($validation['pattern'])) {
                                    $fieldRules[] = 'regex:' . $validation['pattern'];
                                    $messages["{$field}.regex"] = $validation['message'] ?? "{$field} format is invalid.";
                                }
                                break;
                        }
                    }
                }
            }
            if (!empty($fieldRules)) {
                // Join multiple rules with a pipe (e.g., "required|min:3|regex:pattern").
                $rules[$field] = implode('|', $fieldRules);
            }
        }
        dump(["input" => $input, "rules" => $rules, "messages" => $messages]);
        // Create the Laravel validator instance.
        $validator = Validator::make($input, $rules, $messages);
        dump(["validator" => $validator]);

        if ($validator->fails()) {
            // Return the validation errors.
            return $validator->errors();
        }

        return true;
    }
}
