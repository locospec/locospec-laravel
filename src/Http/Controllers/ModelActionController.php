<?php

namespace Locospec\LLCS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Locospec\LLCS\Facades\LLCS;

class ModelActionController extends Controller
{
    /**
     * Handle all model actions
     */
    public function handle(Request $request, string $spec, string $action)
    {
        try {
            $input = $request->all();

            // Convert hyphenated names to LCS format
            $specName = $this->convertToModelName($spec);
            $actionName = $this->convertToActionName($action);

            $isPermissionsEnabled = config('locospec-laravel.enablePermissions', false);

            $input['locospecPermissions'] = [
                'isPermissionsEnabled' => $isPermissionsEnabled,
                'isUserAllowed' => $isPermissionsEnabled === true ? $request->user()->can($specName) : false,
            ];

            // Execute the action via LLCS facade
            $result = LLCS::executeModelAction(
                LLCS::getDefaultValidator(),
                LLCS::getDefaultGenerator(),
                $specName,
                $actionName,
                $input
            );

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'meta' => $result['meta'] ?? [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $this->parseErrorMessage($e->getMessage()),
                // 'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], $this->getHttpStatusCode($e));
        }
    }

    /**
     * Convert hyphenated model name to LCS format
     */
    protected function convertToModelName(string $model): string
    {
        return Str::snake($model);
    }

    /**
     * Convert hyphenated action name to LCS format
     */
    protected function convertToActionName(string $action): string
    {
        return Str::snake($action);
    }

    /**
     * Parse error message to handle both string and JSON formats
     *
     * @return array|string
     */
    private function parseErrorMessage(string $message)
    {
        if (empty($message)) {
            return 'Unknown error occurred';
        }

        $decoded = json_decode($message, true);

        // If json_decode returns null and the original message wasn't null,
        // it means the message wasn't valid JSON, so return the original message
        if ($decoded === null && json_last_error() === JSON_ERROR_NONE) {
            return $message;
        }

        // If json_decode failed, return the original message
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $message;
        }

        return $decoded;
    }

    /**
     * Get appropriate HTTP status code for the exception
     */
    private function getHttpStatusCode(\Exception $e): int
    {
        return match (true) {
            $e instanceof \LCSEngine\Exceptions\PermissionDeniedException => 403,
            $e instanceof \LCSEngine\Exceptions\ValidationException => 422,
            default => 400,
        };
    }
}
