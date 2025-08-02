<?php

namespace ProcessWire;

/**
 * Duplicator REST API Handler
 *
 * This file is responsible for setting up and handling all REST API endpoints
 * for the Duplicator module.
 *
 */

/**
 * Authenticates the API request using a secret key.
 */
function _duplicator_api_auth() {
    $modules = wire('modules');
    $duplicatorConfig = $modules->getConfig('Duplicator');
    $apiKey = !empty($duplicatorConfig['apiKey']) ? $duplicatorConfig['apiKey'] : null;

    // If API key is not configured in the module, deny access.
    if (empty($apiKey)) {
        http_response_code(503); // Service Unavailable
        echo json_encode(['error' => 'API key is not configured on the server.']);
        wire('process')->halt();
    }

    // Get the key from the request header
    $requestKey = wire('input')->header('X-API-Key');

    if ($requestKey !== $apiKey) {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Invalid or missing API Key.']);
        wire('process')->halt();
    }
}


// Add a hook to the 'init' method of the ProcessPageEdit class, which is a common place to hook for URL segments.
// A more specific hook could be used, but this is a reliable starting point.
wire()->addHookAfter('ProcessPageEdit::init', function (HookEvent $event) {
    $input = wire('input');

    // Check if the URL matches our API endpoint structure: /api/duplicator/
    if ($input->urlSegment1 === 'api' && $input->urlSegment2 === 'duplicator') {

        // Prevent further page processing
        $event->return = true;
        header('Content-Type: application/json');

        // Authenticate the request
        _duplicator_api_auth();

        // Simple router based on the next URL segment
        $action = $input->urlSegment3;

        switch ($action) {
            case 'config':
                if (wire('input')->requestMethod('GET')) {
                    // Handle GET /api/duplicator/config
                    $config = wire('modules')->getConfig('Duplicator');
                    // Unset sensitive data before sending
                    unset($config['apiKey']);
                    echo json_encode($config);

                } elseif (wire('input')->requestMethod('POST')) {
                    // Handle POST /api/duplicator/config
                    $newConfig = json_decode(wire('input')->file('php://input'), true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        http_response_code(400); // Bad Request
                        echo json_encode(['error' => 'Invalid JSON provided.']);
                        break;
                    }

                    // Get existing config to merge with
                    $existingConfig = wire('modules')->getConfig('Duplicator');

                    // Do not allow API key to be updated via this endpoint for security
                    unset($newConfig['apiKey']);

                    $mergedConfig = array_merge($existingConfig, $newConfig);

                    wire('modules')->saveConfig('Duplicator', $mergedConfig);
                    echo json_encode(['status' => 'success', 'message' => 'Configuration saved.']);
                }
                break;

            case 'backup':
                if (wire('input')->requestMethod('POST')) {
                    // Handle POST /api/duplicator/backup
                    $duplicator = wire('modules')->get('Duplicator');
                    $result = $duplicator->___cronJob(); // Use ___cronJob() to call the public method

                    if ($result) {
                        echo json_encode(['status' => 'success', 'message' => 'Backup process initiated successfully.']);
                    } else {
                        http_response_code(500); // Internal Server Error
                        echo json_encode(['error' => 'Failed to initiate backup process. Check Duplicator logs.']);
                    }
                }
                break;

            case 'status':
                if (wire('input')->requestMethod('GET')) {
                    // Handle GET /api/duplicator/status
                    // To be implemented in a later step.
                    echo json_encode(['status' => 'success', 'message' => 'Status endpoint hit']);
                }
                break;

            default:
                // Handle unknown endpoint
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
                break;
        }

        // Stop execution
        wire('process')->halt();
    }
});
