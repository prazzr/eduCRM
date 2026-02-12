<?php
declare(strict_types=1);

namespace EduCRM\Controllers\Api;

use EduCRM\Controllers\BaseController;
use EduCRM\Services\JwtService;
use EduCRM\Database\Eloquent;
use EduCRM\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Authentication Controller
 * 
 * Phase 2: API-First Architecture
 * Handles login, token refresh, and logout for API clients.
 * 
 * @package EduCRM\Controllers\Api
 * @version 2.0.0
 */
class AuthController extends BaseController
{
    private JwtService $jwtService;

    public function __construct()
    {
        $this->jwtService = new JwtService();
        Eloquent::boot(); // Ensure Eloquent is initialized
    }

    /**
     * Login endpoint
     * 
     * POST /api/v2/auth/login
     * Body: { "email": "...", "password": "..." }
     */
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        // Validate input
        if (empty($email) || empty($password)) {
            return $this->error($response, 'Email and password are required', 400, 'VALIDATION_ERROR');
        }

        // Find user by email
        $user = User::where('email', $email)->first();

        if (!$user) {
            return $this->error($response, 'Invalid credentials', 401, 'INVALID_CREDENTIALS');
        }

        // Verify password
        if (!password_verify($password, $user->password)) {
            return $this->error($response, 'Invalid credentials', 401, 'INVALID_CREDENTIALS');
        }

        // Check if account is active
        if (!$user->is_active) {
            return $this->error($response, 'Account is inactive', 403, 'ACCOUNT_INACTIVE');
        }

        // Get user roles
        $roles = $user->getRoleNames();

        // Generate tokens
        $tokens = $this->jwtService->generateTokens($user->id, $roles);

        return $this->success($response, [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles
            ],
            'tokens' => $tokens
        ], 'Login successful');
    }

    /**
     * Refresh token endpoint
     * 
     * POST /api/v2/auth/refresh
     * Body: { "refresh_token": "..." }
     */
    public function refresh(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $refreshToken = $data['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            return $this->error($response, 'Refresh token is required', 400);
        }

        // Validate refresh token
        $payload = $this->jwtService->validateToken($refreshToken);

        if (!$payload) {
            return $this->error($response, 'Invalid or expired refresh token', 401, 'INVALID_TOKEN');
        }

        // Check token type
        if (($payload->type ?? '') !== 'refresh') {
            return $this->error($response, 'Invalid token type', 401, 'INVALID_TOKEN_TYPE');
        }

        // Get user
        $user = User::find($payload->sub);
        if (!$user) {
            return $this->error($response, 'User not found', 404, 'USER_NOT_FOUND');
        }

        if (!$user->is_active) {
            return $this->error($response, 'Account is inactive', 403, 'ACCOUNT_INACTIVE');
        }

        // Generate new tokens
        $roles = $user->getRoleNames();
        $tokens = $this->jwtService->generateTokens($user->id, $roles);

        return $this->success($response, [
            'tokens' => $tokens
        ], 'Token refreshed successfully');
    }

    /**
     * Get current user info
     * 
     * GET /api/v2/auth/me
     * Requires: Bearer token
     */
    public function me(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        if (!$userId) {
            return $this->error($response, 'Unauthorized', 401);
        }

        $user = User::with('roles', 'branch')->find($userId);

        if (!$user) {
            return $this->error($response, 'User not found', 404);
        }

        return $this->success($response, [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'roles' => $user->getRoleNames(),
            'branch' => $user->branch ? [
                'id' => $user->branch->id,
                'name' => $user->branch->name
            ] : null
        ]);
    }
}
