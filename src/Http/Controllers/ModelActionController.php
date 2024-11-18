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
     *
     * @param  string  $model  Hyphenated model name from URL
     * @param  string  $action  Hyphenated action name from URL
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, string $model, string $action)
    {
        try {
            // Convert hyphenated names to LCS format
            $modelName = $this->convertToModelName($model);
            $actionName = $this->convertToActionName($action);

            // Get input data from request
            $input = $request->all();

            // Execute the action via LCS
            $result = LLCS::getEngine()->executeModelAction(
                $modelName,
                $actionName,
                $input
            );

            return response()->json([
                'success' => true,
                'data' => $result,
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
     * e.g., 'blog-post' -> 'blogPost'
     */
    protected function convertToModelName(string $model): string
    {
        return Str::camel($model);
    }

    /**
     * Convert hyphenated action name to LCS format
     * e.g., 'read-one' -> 'readOne'
     */
    protected function convertToActionName(string $action): string
    {
        return Str::camel($action);
    }
}
