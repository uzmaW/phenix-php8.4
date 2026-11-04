<?php
declare(strict_types=1);
namespace Phoenix\Auth;

use Phoenix\Core\Result;

/**
 * Pure-PHP JWT service (HS256 by default).
 *
 * Features:
 *  - create()             – issue access token
 *  - createRefreshToken() – issue long-lived refresh token
 *  - validate()           – verify signature, expiry, nbf, revocation
 *  - refresh()            – exchange refresh token for a new access token
 *  - revoke()             – add a token's JTI to the in-memory blacklist
 *  - getClaims()          – decode without validation (unsafe, use with care)
 *
 * Note: The blacklist is in-memory only. For production use, persist it to
 * a shared cache (Redis, database, etc.).
 */
final class JwtService
{
    /** @var list<string> JTIs of revoked tokens */
    private array $blacklist = [];

    public function __construct(
        private readonly string $secret,
        private readonly int    $ttl        = 3600,
        private readonly int    $refreshTtl = 86400,
        private readonly string $algorithm  = 'HS256',
    ) {}

    // -------------------------------------------------------------------------
    // Token creation
    // -------------------------------------------------------------------------

    /**
     * Issue an access token with the given user claims.
     * The standard JWT registered claims (iat, exp, nbf, jti) are always
     * appended and will override any same-named key in $claims.
     */
    public function create(array $claims = []): string
    {
        $now     = time();
        $payload = array_merge($claims, [
            'iat' => $now,
            'exp' => $now + $this->ttl,
            'nbf' => $now,
            'jti' => bin2hex(random_bytes(16)),
        ]);
        return $this->encode($payload);
    }

    /**
     * Issue a long-lived refresh token. Includes `"type": "refresh"` claim
     * so that validate() + refresh() can distinguish it from access tokens.
     */
    public function createRefreshToken(array $claims = []): string
    {
        $now     = time();
        $payload = array_merge($claims, [
            'iat'  => $now,
            'exp'  => $now + $this->refreshTtl,
            'type' => 'refresh',
            'jti'  => bin2hex(random_bytes(16)),
        ]);
        return $this->encode($payload);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /**
     * Fully validate a token: structure, signature, expiry, nbf, revocation.
     *
     * @return Result Ok(value is array<string,mixed> claims) or Err(value is reason string)
     */
    public function validate(string $token): Result
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return Result::err('Invalid token structure');
            }

            [$header64, $payload64, $sig64] = $parts;

            // Verify signature
            $expectedSig = $this->sign($header64 . '.' . $payload64);
            if (!hash_equals($expectedSig, $sig64)) {
                return Result::err('Invalid signature');
            }

            // Decode payload
            $payload = json_decode($this->base64UrlDecode($payload64), true);
            if (!is_array($payload)) {
                return Result::err('Invalid payload');
            }

            $now = time();

            if (isset($payload['exp']) && $payload['exp'] < $now) {
                return Result::err('Token expired');
            }
            if (isset($payload['nbf']) && $payload['nbf'] > $now) {
                return Result::err('Token not yet valid');
            }
            if (isset($payload['jti']) && in_array($payload['jti'], $this->blacklist, true)) {
                return Result::err('Token has been revoked');
            }

            return Result::ok($payload);
        } catch (\Throwable $e) {
            return Result::err('Token validation failed: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Refresh
    // -------------------------------------------------------------------------

    /**
     * Exchange a valid refresh token for a new access token.
     *
     * @return Result Ok(value is the new access token string) or Err(value is reason)
     */
    public function refresh(string $refreshToken): Result
    {
        $result = $this->validate($refreshToken);
        if ($result->isErr()) {
            return $result;
        }

        $claims = $result->unwrap();
        if (($claims['type'] ?? '') !== 'refresh') {
            return Result::err('Not a refresh token');
        }

        // Strip standard JWT/refresh-specific claims; keep user claims only
        $userClaims = array_diff_key($claims, array_flip(['iat', 'exp', 'nbf', 'jti', 'type']));
        return Result::ok($this->create($userClaims));
    }

    // -------------------------------------------------------------------------
    // Revocation
    // -------------------------------------------------------------------------

    /**
     * Add the token's JTI to the in-memory blacklist.
     * Subsequent calls to validate() for this token will return Err.
     */
    public function revoke(string $token): void
    {
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            $payload = json_decode($this->base64UrlDecode($parts[1]), true);
            if (isset($payload['jti'])) {
                $this->blacklist[] = $payload['jti'];
            }
        }
    }

    // -------------------------------------------------------------------------
    // Unsafe decode (no validation)
    // -------------------------------------------------------------------------

    /**
     * Decode and return claims WITHOUT verifying signature or expiry.
     * Useful for reading non-sensitive information (e.g. user id) from an
     * already-validated token.  Never trust the output of this method alone.
     *
     * @return array<string, mixed>
     */
    public function getClaims(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return [];
        }
        return json_decode($this->base64UrlDecode($parts[1]), true) ?? [];
    }

    // -------------------------------------------------------------------------
    // Encoding helpers
    // -------------------------------------------------------------------------

    private function encode(array $payload): string
    {
        $header = $this->base64UrlEncode(
            (string) json_encode(['alg' => $this->algorithm, 'typ' => 'JWT'])
        );
        $body = $this->base64UrlEncode((string) json_encode($payload));
        $sig  = $this->sign($header . '.' . $body);
        return $header . '.' . $body . '.' . $sig;
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(
            hash_hmac('sha256', $data, $this->secret, true)
        );
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        // Re-pad to a multiple of 4 characters, then decode
        $padded = $data . str_repeat('=', 3 - (3 + strlen($data)) % 4);
        return (string) base64_decode(strtr($padded, '-_', '+/'));
    }
}
