<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class SwaggerController extends Controller
{
    /**
     * Display the interactive Swagger UI page.
     */
    public function ui(): View
    {
        return view('api.swagger', [
            'specUrl' => route('api.openapi'),
            'appName' => (string) config('app.name', 'Laravel Stisla'),
        ]);
    }

    /**
     * Return the OpenAPI 3.0.3 JSON specification.
     */
    public function spec(): JsonResponse
    {
        $appName = (string) config('app.name', 'Laravel Stisla');

        return response()->json([
            'openapi' => '3.0.3',
            'info' => [
                'title' => "{$appName} API",
                'version' => '1.0.0',
                'description' => 'REST API documentation using Laravel Sanctum Bearer Token authentication.',
            ],
            'servers' => [
                [
                    'url' => url('/api'),
                    'description' => 'Application API Server',
                ],
            ],
            'tags' => [
                [
                    'name' => 'Authentication',
                    'description' => 'Sanctum authentication endpoints (login, register, forgot/reset password, profile, logout)',
                ],
            ],
            'paths' => $this->paths(),
            'components' => [
                'securitySchemes' => [
                    'sanctum' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Sanctum Token',
                        'description' => 'Enter the Bearer token returned by /api/auth/login or /api/auth/register.',
                    ],
                ],
                'schemas' => $this->schemas(),
            ],
        ]);
    }

    /**
     * Build OpenAPI path definitions.
     *
     * @return array<string, mixed>
     */
    protected function paths(): array
    {
        return [
            '/auth/login' => [
                'post' => [
                    'tags' => ['Authentication'],
                    'summary' => 'Login user',
                    'description' => 'Authenticate a user with email & password and issue a Sanctum personal access token.',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/LoginRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Login successful',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/AuthTokenResponse'],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => 'Invalid credentials',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/MessageResponse'],
                                ],
                            ],
                        ],
                        '403' => [
                            'description' => 'Account blocked or email not verified',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/MessageResponse'],
                                ],
                            ],
                        ],
                        '422' => [
                            'description' => 'Validation error',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ValidationErrorResponse'],
                                ],
                            ],
                        ],
                        '429' => [
                            'description' => 'Too many login attempts',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/MessageResponse'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/auth/register' => [
                'post' => [
                    'tags' => ['Authentication'],
                    'summary' => 'Register new user',
                    'description' => 'Register a new user account and issue a Sanctum personal access token (or send activation email if enabled).',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/RegisterRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Registration successful',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/RegisterResponse'],
                                ],
                            ],
                        ],
                        '403' => [
                            'description' => 'Registration disabled',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/MessageResponse'],
                                ],
                            ],
                        ],
                        '422' => [
                            'description' => 'Validation error',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ValidationErrorResponse'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/auth/forgot-password' => [
                'post' => [
                    'tags' => ['Authentication'],
                    'summary' => 'Request password reset link',
                    'description' => 'Send a password reset link to the specified email address.',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ForgotPasswordRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Reset link sent',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/MessageResponse'],
                                ],
                            ],
                        ],
                        '422' => [
                            'description' => 'Validation error or email not found',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ValidationErrorResponse'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/auth/reset-password' => [
                'post' => [
                    'tags' => ['Authentication'],
                    'summary' => 'Reset password',
                    'description' => 'Reset user password using a valid reset token and revoke existing Sanctum tokens.',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ResetPasswordRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Password reset successful',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/MessageResponse'],
                                ],
                            ],
                        ],
                        '422' => [
                            'description' => 'Invalid token or validation error',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/ValidationErrorResponse'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/auth/me' => [
                'get' => [
                    'tags' => ['Authentication'],
                    'summary' => 'Get authenticated user',
                    'security' => [['sanctum' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Authenticated user profile',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'user' => ['$ref' => '#/components/schemas/User'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => 'Unauthenticated',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/MessageResponse'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/auth/logout' => [
                'post' => [
                    'tags' => ['Authentication'],
                    'summary' => 'Logout user',
                    'description' => 'Revoke the current Sanctum Bearer token.',
                    'security' => [['sanctum' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Logged out successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/MessageResponse'],
                                ],
                            ],
                        ],
                        '401' => [
                            'description' => 'Unauthenticated',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/MessageResponse'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build OpenAPI component schemas.
     *
     * @return array<string, mixed>
     */
    protected function schemas(): array
    {
        return [
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'example' => 1],
                    'uuid' => ['type' => 'string', 'format' => 'uuid', 'example' => '550e8400-e29b-41d4-a716-446655440000'],
                    'name' => ['type' => 'string', 'example' => 'John Doe'],
                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'admin@example.com'],
                    'role' => ['type' => 'string', 'nullable' => true, 'example' => 'user'],
                    'phone' => ['type' => 'string', 'nullable' => true, 'example' => '08123456789'],
                    'avatar_url' => ['type' => 'string', 'format' => 'uri', 'example' => 'http://127.0.0.1:8000/assets/img/avatar/avatar-1.png'],
                    'email_verified_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
            ],
            'LoginRequest' => [
                'type' => 'object',
                'required' => ['email', 'password'],
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'admin@example.com'],
                    'password' => ['type' => 'string', 'format' => 'password', 'example' => 'password'],
                    'device_name' => ['type' => 'string', 'nullable' => true, 'example' => 'Flutter App'],
                ],
            ],
            'RegisterRequest' => [
                'type' => 'object',
                'required' => ['name', 'email', 'password', 'password_confirmation'],
                'properties' => [
                    'name' => ['type' => 'string', 'example' => 'John Doe'],
                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'john@example.com'],
                    'password' => ['type' => 'string', 'format' => 'password', 'example' => 'Password123!'],
                    'password_confirmation' => ['type' => 'string', 'format' => 'password', 'example' => 'Password123!'],
                    'device_name' => ['type' => 'string', 'nullable' => true, 'example' => 'Flutter App'],
                ],
            ],
            'ForgotPasswordRequest' => [
                'type' => 'object',
                'required' => ['email'],
                'properties' => [
                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'admin@example.com'],
                ],
            ],
            'ResetPasswordRequest' => [
                'type' => 'object',
                'required' => ['token', 'email', 'password', 'password_confirmation'],
                'properties' => [
                    'token' => ['type' => 'string', 'example' => 'reset-token-from-email'],
                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'admin@example.com'],
                    'password' => ['type' => 'string', 'format' => 'password', 'example' => 'NewPassword123!'],
                    'password_confirmation' => ['type' => 'string', 'format' => 'password', 'example' => 'NewPassword123!'],
                ],
            ],
            'AuthTokenResponse' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string', 'example' => 'Login successful.'],
                    'token_type' => ['type' => 'string', 'example' => 'Bearer'],
                    'token' => ['type' => 'string', 'example' => '1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'],
                    'user' => ['$ref' => '#/components/schemas/User'],
                ],
            ],
            'RegisterResponse' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string', 'example' => 'Registration successful.'],
                    'requires_activation' => ['type' => 'boolean', 'example' => false],
                    'token_type' => ['type' => 'string', 'nullable' => true, 'example' => 'Bearer'],
                    'token' => ['type' => 'string', 'nullable' => true, 'example' => '1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'],
                    'user' => ['$ref' => '#/components/schemas/User'],
                ],
            ],
            'MessageResponse' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string', 'example' => 'Operation completed successfully.'],
                ],
            ],
            'ValidationErrorResponse' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string', 'example' => 'The given data was invalid.'],
                    'errors' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
