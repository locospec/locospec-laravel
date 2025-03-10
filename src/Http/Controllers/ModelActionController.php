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
    public function handle(Request $request, string $model, string $action)
    {
        try {
            // Convert hyphenated names to LCS format
            $modelName = $this->convertToModelName($model);
            $actionName = $this->convertToActionName($action);

            // Execute the action via LLCS facade
            $result = LLCS::executeModelAction(
                $modelName,
                $actionName,
                $request->all()
            );

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'meta' => $result['meta']??[],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 400);
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
}
