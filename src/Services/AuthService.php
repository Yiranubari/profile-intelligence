<?php

namespace App\Services;

use App\Exceptions\OAuthException;
use App\Exceptions\UnauthorizedException;
use App\Helpers\PkceHelper;
use App\Helpers\UuidHelper;
use App\Repositories\RefreshTokenRepository;
use App\Repositories\UserRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PDO;
use Psr\Log\LoggerInterface;

class AuthService
{
    public function __construct(
        private UserRepository $users,
        private RefreshTokenRepository $tokens,
        private TokenService $tokenService,
        private PDO $pdo,
        private LoggerInterface $logger,
        private Client $http,
        private string $githubClientId,
        private string $githubClientSecret,
        private string $githubRedirectUri,
        private int $refreshTtl
    ) {}

    public function startOAuthFlow(string $clientType, ?string $codeChallenge = null): string
    {
        if (!in_array($clientType, ['web', 'cli'], true)) {
            throw new UnauthorizedException('Invalid client type');
        }

        if ($clientType === 'cli' && $codeChallenge === null) {
            throw new UnauthorizedException('Missing code challenge');
        }

        $state = bin2hex(random_bytes(32));
        $now = $this->nowUtc();
        $expiresAt = $this->utcPlusSeconds(600);

        $stmt = $this->pdo->prepare(
            'INSERT INTO auth_sessions (state, code_challenge, client_type, expires_at, created_at)
			 VALUES (:state, :code_challenge, :client_type, :expires_at, :created_at)'
        );
        $stmt->execute([
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'client_type' => $clientType,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

        $params = http_build_query([
            'client_id' => $this->githubClientId,
            'redirect_uri' => $this->githubRedirectUri,
            'scope' => 'read:user user:email',
            'state' => $state,
        ]);

        return "https://github.com/login/oauth/authorize?{$params}";
    }

    public function handleCallback(string $code, string $state): array
    {
        $session = $this->findSessionByState($state);
        if ($session === null || $this->isExpired($session['expires_at']) || (int) $session['consumed'] === 1) {
            throw new UnauthorizedException('Invalid or expired auth session');
        }

        $githubToken = $this->exchangeGithubCode($code);
        $githubUser = $this->fetchGithubUser($githubToken);
        $email = $this->resolveGithubEmail($githubToken, $githubUser);

        $githubId = isset($githubUser['id']) ? (string) $githubUser['id'] : null;
        $username = $githubUser['login'] ?? null;
        $avatarUrl = $githubUser['avatar_url'] ?? null;

        if ($githubId === null || $username === null) {
            throw new OAuthException('Invalid GitHub user response');
        }

        $user = $this->users->findByGithubId($githubId);
        if ($user !== null) {
            $user = $this->users->updateProfile($user['id'], [
                'username' => $username,
                'email' => $email,
                'avatar_url' => $avatarUrl,
            ]);
            $this->users->updateLastLogin($user['id']);
            $user = $this->users->findById($user['id']) ?? $user;
        } else {
            $user = $this->users->create([
                'id' => UuidHelper::generate(),
                'github_id' => $githubId,
                'username' => $username,
                'email' => $email,
                'avatar_url' => $avatarUrl,
                'role' => 'analyst',
                'created_at' => $this->nowUtc(),
            ]);
            $this->users->updateLastLogin($user['id']);
            $user = $this->users->findById($user['id']) ?? $user;
        }

        if ((int) ($user['is_active'] ?? 1) === 0) {
            throw new UnauthorizedException('Account is disabled');
        }

        if ($session['client_type'] === 'web') {
            $stmt = $this->pdo->prepare('UPDATE auth_sessions SET consumed = 1 WHERE state = :state');
            $stmt->execute(['state' => $state]);

            $issued = $this->issueTokens($user);
            return [
                'client_type' => 'web',
                'user' => $user,
                'access_token' => $issued['access_token'],
                'refresh_token' => $issued['refresh_token'],
            ];
        }

        $authCode = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare('UPDATE auth_sessions SET auth_code = :auth_code, user_id = :user_id WHERE state = :state');
        $stmt->execute([
            'auth_code' => $authCode,
            'user_id' => $user['id'],
            'state' => $state,
        ]);

        return [
            'client_type' => 'cli',
            'auth_code' => $authCode,
        ];
    }

    public function exchangeAuthCode(string $authCode, string $codeVerifier): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM auth_sessions WHERE auth_code = :auth_code');
        $stmt->execute(['auth_code' => $authCode]);
        $session = $stmt->fetch() ?: null;

        if (
            $session === null
            || $this->isExpired($session['expires_at'])
            || (int) $session['consumed'] === 1
            || empty($session['user_id'])
        ) {
            throw new UnauthorizedException('Invalid or expired auth session');
        }

        $challenge = $session['code_challenge'] ?? '';
        if ($challenge === '' || !PkceHelper::verifyChallenge($codeVerifier, $challenge)) {
            throw new UnauthorizedException('Invalid code verifier');
        }

        $markConsumed = $this->pdo->prepare('UPDATE auth_sessions SET consumed = 1 WHERE state = :state');
        $markConsumed->execute(['state' => $session['state']]);

        $user = $this->users->findById($session['user_id']);
        if ($user === null) {
            throw new UnauthorizedException('Invalid user');
        }

        if ((int) ($user['is_active'] ?? 1) === 0) {
            throw new UnauthorizedException('Account is disabled');
        }

        $issued = $this->issueTokens($user);

        return [
            'user' => $user,
            'access_token' => $issued['access_token'],
            'refresh_token' => $issued['refresh_token'],
        ];
    }

    public function refresh(string $rawRefreshToken): array
    {
        $hash = $this->tokenService->hashRefreshToken($rawRefreshToken);
        $stored = $this->tokens->findByHash($hash);

        if ($stored === null) {
            throw new UnauthorizedException('Invalid refresh token');
        }

        if ($this->isExpired($stored['expires_at'])) {
            throw new UnauthorizedException('Refresh token expired');
        }

        $this->tokens->revoke($hash);

        $user = $this->users->findById($stored['user_id']);
        if ($user === null) {
            throw new UnauthorizedException('Invalid refresh token');
        }

        if ((int) ($user['is_active'] ?? 1) === 0) {
            throw new UnauthorizedException('Account is disabled');
        }

        return $this->issueTokens($user);
    }

    public function logout(string $rawRefreshToken): void
    {
        $hash = $this->tokenService->hashRefreshToken($rawRefreshToken);
        $this->tokens->revoke($hash);
    }

    private function issueTokens(array $user): array
    {
        $accessToken = $this->tokenService->generateAccessToken($user);
        $refreshToken = $this->tokenService->generateRefreshToken();
        $refreshHash = $this->tokenService->hashRefreshToken($refreshToken);

        $this->tokens->create([
            'id' => UuidHelper::generate(),
            'user_id' => $user['id'],
            'token_hash' => $refreshHash,
            'expires_at' => $this->utcPlusSeconds($this->refreshTtl),
            'created_at' => $this->nowUtc(),
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }

    private function findSessionByState(string $state): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM auth_sessions WHERE state = :state');
        $stmt->execute(['state' => $state]);

        return $stmt->fetch() ?: null;
    }

    private function exchangeGithubCode(string $code): string
    {
        try {
            $response = $this->http->post('https://github.com/login/oauth/access_token', [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'form_params' => [
                    'client_id' => $this->githubClientId,
                    'client_secret' => $this->githubClientSecret,
                    'code' => $code,
                    'redirect_uri' => $this->githubRedirectUri,
                ],
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('GitHub token exchange failed', ['error' => $e->getMessage()]);
            throw new OAuthException('Failed to exchange GitHub code');
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (!is_array($payload)) {
            throw new OAuthException('Invalid GitHub token response');
        }

        if (!empty($payload['error'])) {
            throw new OAuthException('GitHub token exchange was rejected');
        }

        $token = $payload['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new OAuthException('Missing GitHub access token');
        }

        return $token;
    }

    private function fetchGithubUser(string $githubToken): array
    {
        try {
            $response = $this->http->get('https://api.github.com/user', [
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => "Bearer {$githubToken}",
                    'User-Agent' => 'profile-intelligence',
                ],
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('GitHub user fetch failed', ['error' => $e->getMessage()]);
            throw new OAuthException('Failed to fetch GitHub user');
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (!is_array($payload)) {
            throw new OAuthException('Invalid GitHub user response');
        }

        return $payload;
    }

    private function resolveGithubEmail(string $githubToken, array $githubUser): ?string
    {
        $directEmail = $githubUser['email'] ?? null;
        if (is_string($directEmail) && $directEmail !== '') {
            return $directEmail;
        }

        try {
            $response = $this->http->get('https://api.github.com/user/emails', [
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => "Bearer {$githubToken}",
                    'User-Agent' => 'profile-intelligence',
                ],
            ]);
        } catch (GuzzleException $e) {
            $this->logger->warning('GitHub email fetch failed', ['error' => $e->getMessage()]);
            return null;
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (!is_array($payload)) {
            return null;
        }

        foreach ($payload as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (($entry['primary'] ?? false) && ($entry['verified'] ?? false) && !empty($entry['email'])) {
                return (string) $entry['email'];
            }
        }

        foreach ($payload as $entry) {
            if (is_array($entry) && !empty($entry['email'])) {
                return (string) $entry['email'];
            }
        }

        return null;
    }

    private function isExpired(string $iso8601): bool
    {
        $time = strtotime($iso8601);
        return $time === false || $time < time();
    }

    private function nowUtc(): string
    {
        return (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\\TH:i:s\\Z');
    }

    private function utcPlusSeconds(int $seconds): string
    {
        return (new \DateTime('now', new \DateTimeZone('UTC')))
            ->modify("+{$seconds} seconds")
            ->format('Y-m-d\\TH:i:s\\Z');
    }
}
