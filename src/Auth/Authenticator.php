<?php

declare(strict_types=1);

namespace App\Auth;

use App\Exception\AuthenticationException;
use App\Exception\AuthorizationException;

/**
 * Handles Kerberos SSO trust and LDAPS form authentication against Active Directory.
 *
 * Two authentication paths exist:
 *   - SSO: Apache sets $_SERVER['REMOTE_USER'] after successful GSSAPI negotiation.
 *          PHP calls verifyGroupMembership() to enforce AD group membership.
 *   - Form: User submits username + password. authenticateWithLdap() binds to LDAPS
 *           and calls verifyGroupMembership() on success.
 *
 * Required config keys: LDAP_HOST, LDAP_PORT (int), LDAP_DOMAIN, LDAP_BASE_DN,
 * LDAP_SERVICE_DN, LDAP_SERVICE_PASSWORD, LDAP_REQUIRED_GROUP.
 */
class Authenticator
{
    private const SESSION_KEY = 'daybook_user';

    /** @param array<string, string> $config */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Returns the authenticated username from the session, or null.
     */
    public function getAuthenticatedUser(): string|null
    {
        $value = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Binds to LDAPS with the user's credentials, then verifies group membership.
     *
     * Accepts both plain usernames ("alice") and UPN format ("alice@company.com").
     * Returns the normalized sAMAccountName (without domain) on success so callers
     * can store a consistent value in the session regardless of what the user typed.
     * Returns false on wrong credentials.
     * Throws AuthenticationException on LDAP connection or configuration errors.
     * Throws AuthorizationException when authenticated but not in the required group.
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function authenticateWithLdap(string $username, string $password): string|false
    {
        if ($username === '' || $password === '') {
            return false;
        }

        $samAccountName = $this->normalizeSamAccountName($username);
        $conn           = $this->connect();

        set_error_handler(static fn (): bool => true);
        $bound = ldap_bind($conn, $this->buildUpn($username), $password);
        restore_error_handler();

        if (!$bound) {
            $diagnostic = '';
            ldap_get_option($conn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diagnostic);
            ldap_unbind($conn);
            $msg = is_string($diagnostic) && $diagnostic !== '' ? $diagnostic : 'no diagnostic message';
            error_log('[Daybook] LDAP user bind failed - ' . $msg);

            return false;
        }

        ldap_unbind($conn);

        $this->verifyGroupMembership($samAccountName);

        return $samAccountName;
    }

    /**
     * Verifies that a username belongs to the required AD group via a service account search.
     *
     * Uses LDAP_MATCHING_RULE_IN_CHAIN (OID 1.2.840.113556.1.4.1941) to resolve
     * nested group membership in Active Directory.
     *
     * @throws AuthenticationException On LDAP connection or service-account bind errors.
     * @throws AuthorizationException  When the user is not a member of the required group.
     */
    public function verifyGroupMembership(string $username): void
    {
        $conn = $this->connect();
        $this->bindService($conn);

        $filter  = $this->buildGroupFilter($username);
        $result  = ldap_search($conn, (string) $this->config['LDAP_BASE_DN'], $filter, ['dn'], 0, 1);

        if (!($result instanceof \LDAP\Result)) {
            ldap_unbind($conn);
            throw new AuthenticationException('LDAP group search failed.');
        }

        $entries = ldap_get_entries($conn, $result);
        ldap_unbind($conn);

        $count = isset($entries['count']) ? (int) $entries['count'] : 0;
        if ($count === 0) {
            throw new AuthorizationException(
                sprintf('User "%s" is not a member of the required group.', $username)
            );
        }
    }

    /**
     * Stores the authenticated username in the session.
     * Regenerates the session ID to prevent session fixation attacks.
     */
    public function startSession(string $username): void
    {
        $_SESSION[self::SESSION_KEY] = $username;
        session_regenerate_id(true);
    }

    /**
     * Destroys the current session cleanly.
     */
    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Opens an LDAPS connection to the configured host.
     *
     * @throws AuthenticationException If the connection cannot be established.
     */
    private function connect(): \LDAP\Connection
    {
        // Must be set globally on null before ldap_connect() — the TLS context
        // is initialised at connect time and ignores per-connection options set later.
        // /etc/ldap/ldap.conf (mounted at runtime) is the primary mechanism;
        // this is a belt-and-suspenders fallback for environments where the
        // LDAP_CA_CERT env variable is explicitly set.
        $caCert = $this->config['LDAP_CA_CERT'] ?? '';
        if ($caCert !== '' && file_exists($caCert)) {
            ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, $caCert);
        }

        $uri  = sprintf('ldaps://%s:%d', $this->config['LDAP_HOST'], (int) $this->config['LDAP_PORT']);
        $conn = ldap_connect($uri);

        if ($conn === false) {
            throw new AuthenticationException(
                sprintf('Cannot connect to LDAP server: %s', $uri)
            );
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

        return $conn;
    }

    /**
     * Binds the connection using the configured service account.
     *
     * @throws AuthenticationException If the service account bind fails.
     */
    private function bindService(\LDAP\Connection $conn): void
    {
        set_error_handler(static fn (): bool => true);
        $bound = ldap_bind(
            $conn,
            (string) $this->config['LDAP_SERVICE_DN'],
            (string) $this->config['LDAP_SERVICE_PASSWORD']
        );
        restore_error_handler();

        if (!$bound) {
            ldap_unbind($conn);
            throw new AuthenticationException(
                'Service account bind failed. Check LDAP_SERVICE_DN and LDAP_SERVICE_PASSWORD.'
            );
        }
    }

    /**
     * Builds the UPN used for the LDAP bind.
     *
     * If the input already contains "@" (e.g. "alice@company.com"), it is used
     * as-is. Otherwise LDAP_DOMAIN is appended (e.g. "alice" -> "alice@company.com").
     */
    private function buildUpn(string $username): string
    {
        return str_contains($username, '@')
            ? $username
            : $username . '@' . $this->config['LDAP_DOMAIN'];
    }

    /**
     * Strips the domain suffix from a username if present.
     *
     * "alice@company.com" -> "alice"
     * "alice"             -> "alice"
     */
    private function normalizeSamAccountName(string $username): string
    {
        $atPos = strpos($username, '@');

        return $atPos !== false ? substr($username, 0, $atPos) : $username;
    }

    /**
     * Builds the LDAP filter for recursive group membership check.
     * All values are escaped to prevent LDAP injection.
     */
    private function buildGroupFilter(string $username): string
    {
        return sprintf(
            '(&(sAMAccountName=%s)(memberOf:1.2.840.113556.1.4.1941:=%s))',
            ldap_escape($username, '', LDAP_ESCAPE_FILTER),
            ldap_escape((string) $this->config['LDAP_REQUIRED_GROUP'], '', LDAP_ESCAPE_FILTER)
        );
    }
}
