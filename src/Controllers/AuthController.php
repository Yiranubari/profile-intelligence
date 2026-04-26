<?php

namespace App\Controllers;

use App\Exceptions\OAuthException;
use App\Exceptions\UnauthorizedException;
use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class AuthController
{
    public function __construct(
        private AuthService $authService,
        private LoggerInterface $logger,
        private string $webPortalUrl,
        private bool $cookieSecure,
        private int $accessTtl,
        private int $refreshTtl
    ) {}

    public function redirectToGithub(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $clientType = $params['client_type'] ?? 'web';
        $codeChallenge = $params['code_challenge'] ?? null;
        $cliPort = isset($params['cli_port']) ? (int) $params['cli_port'] : null;

        $githubUrl = $this->authService->startOAuthFlow($clientType, $codeChallenge, $cliPort);

        return $response->withHeader('Location', $githubUrl)->withStatus(302);
    }

    public function handleCallback(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $code = $params['code'] ?? null;
        $state = $params['state'] ?? null;

        if (!$code || !$state) {
            return $this->json($response, 400, [
                'status' => 'error',
                'message' => 'Missing code or state',
            ]);
        }

        $result = $this->authService->handleCallback($code, $state);

        if ($result['client_type'] === 'web') {
            $response = $this->setAuthCookies($response, $result['access_token'], $result['refresh_token']);
            return $response
                ->withHeader('Location', $this->webPortalUrl . '/dashboard')
                ->withStatus(302);
        }

        $port = $result['cli_port'];
        $authCode = $result['auth_code'];
        $cliRedirect = "http://localhost:{$port}/callback?auth_code={$authCode}&state={$state}";

        return $response->withHeader('Location', $cliRedirect)->withStatus(302);
    }

    public function exchangeCli(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];
        $authCode = $body['auth_code'] ?? null;
        $codeVerifier = $body['code_verifier'] ?? null;

        if (!$authCode || !$codeVerifier) {
            return $this->json($response, 400, [
                'status' => 'error',
                'message' => 'Missing auth_code or code_verifier',
            ]);
        }

        $result = $this->authService->exchangeAuthCode($authCode, $codeVerifier);

        return $this->json($response, 200, [
            'status' => 'success',
            'user' => [
                'id' => $result['user']['id'],
                'username' => $result['user']['username'],
                'email' => $result['user']['email'],
                'avatar_url' => $result['user']['avatar_url'],
                'role' => $result['user']['role'],
            ],
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
        ]);
    }
    public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];
        $token = $body['refresh_token'] ?? null;

        if (!$token) {
            $cookies = $request->getCookieParams();
            $token = $cookies['refresh_token'] ?? null;
        }

        if (!$token) {
            return $this->json($response, 400, [
                'status' => 'error',
                'message' => 'Missing refresh token',
            ]);
        }

        $result = $this->authService->refresh($token);

        $cookies = $request->getCookieParams();
        if (isset($cookies['refresh_token'])) {
            $response = $this->setAuthCookies($response, $result['access_token'], $result['refresh_token']);
        }

        return $this->json($response, 200, [
            'status' => 'success',
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
        ]);
    }
    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];
        $token = $body['refresh_token'] ?? null;

        if (!$token) {
            $cookies = $request->getCookieParams();
            $token = $cookies['refresh_token'] ?? null;
        }

        if ($token) {
            $this->authService->logout($token);
        }

        $response = $this->clearAuthCookies($response);

        return $this->json($response, 200, [
            'status' => 'success',
            'message' => 'Logged out',
        ]);
    }
    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if (!$user) {
            return $this->json($response, 401, [
                'status' => 'error',
                'message' => 'Unauthorized',
            ]);
        }

        return $this->json($response, 200, [
            'status' => 'success',
            'data' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'avatar_url' => $user['avatar_url'],
                'role' => $user['role'],
            ],
        ]);
    }
    private function setAuthCookies(ResponseInterface $response, string $accessToken, string $refreshToken): ResponseInterface
    {
        $secureFlag = $this->cookieSecure ? '; Secure' : '';
        $csrfToken = bin2hex(random_bytes(32));

        $response = $response->withAddedHeader(
            'Set-Cookie',
            "access_token={$accessToken}; Path=/; HttpOnly; SameSite=Lax; Max-Age={$this->accessTtl}{$secureFlag}"
        );
        $response = $response->withAddedHeader(
            'Set-Cookie',
            "refresh_token={$refreshToken}; Path=/; HttpOnly; SameSite=Lax; Max-Age={$this->refreshTtl}{$secureFlag}"
        );
        $response = $response->withAddedHeader(
            'Set-Cookie',
            "csrf_token={$csrfToken}; Path=/; SameSite=Lax; Max-Age={$this->refreshTtl}{$secureFlag}"
        );

        return $response;
    }

    private function clearAuthCookies(ResponseInterface $response): ResponseInterface
    {
        $secureFlag = $this->cookieSecure ? '; Secure' : '';

        foreach (['access_token', 'refresh_token', 'csrf_token'] as $name) {
            $response = $response->withAddedHeader(
                'Set-Cookie',
                "{$name}=; Path=/; Max-Age=0; SameSite=Lax{$secureFlag}"
            );
        }

        return $response;
    }

    private function json(ResponseInterface $response, int $status, array $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
