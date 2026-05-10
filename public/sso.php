<?php

declare(strict_types=1);

/**
 * Kerberos SSO endpoint.
 *
 * Apache enforces SPNEGO on this URL (see docker/apache.conf).
 * On a successful Kerberos negotiation Apache sets REMOTE_USER before PHP runs.
 * This script verifies group membership, creates the PHP session, and redirects
 * to the application root.
 *
 * Unauthenticated users are redirected here by index.php so the browser performs
 * a full top-level navigation. Apache challenges with 401+WWW-Authenticate:Negotiate;
 * domain-joined browsers respond automatically. Non-Kerberos clients get 401 which
 * Apache maps to index.php via ErrorDocument, showing the LDAPS login form.
 */

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Exception/AuthenticationException.php';
require_once __DIR__ . '/../src/Exception/AuthorizationException.php';
require_once __DIR__ . '/../src/Auth/Authenticator.php';

App\Env::load(__DIR__ . '/../.env');

$authEnabled = strtolower(trim(App\Env::get('AUTH_ENABLED') ?? 'true')) !== 'false';

if (!$authEnabled) {
    http_response_code(204);
    exit;
}

if (!isset($_SERVER['REMOTE_USER'])) {
    http_response_code(401);
    exit;
}

session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();

$auth = new App\Auth\Authenticator([
    'LDAP_HOST'             => App\Env::require('LDAP_HOST'),
    'LDAP_PORT'             => App\Env::require('LDAP_PORT'),
    'LDAP_DOMAIN'           => App\Env::require('LDAP_DOMAIN'),
    'LDAP_BASE_DN'          => App\Env::require('LDAP_BASE_DN'),
    'LDAP_SERVICE_DN'       => App\Env::require('LDAP_SERVICE_DN'),
    'LDAP_SERVICE_PASSWORD' => App\Env::require('LDAP_SERVICE_PASSWORD'),
    'LDAP_REQUIRED_GROUP'   => App\Env::require('LDAP_REQUIRED_GROUP'),
    'LDAP_CA_CERT'          => App\Env::get('LDAP_CA_CERT') ?? '',
]);

if ($auth->getAuthenticatedUser() !== null) {
    header('Location: /');
    exit;
}

try {
    $auth->verifyGroupMembership((string) $_SERVER['REMOTE_USER']);
    $auth->startSession((string) $_SERVER['REMOTE_USER']);
    header('Location: /');
    exit;
} catch (App\Exception\AuthorizationException $e) {
    error_log('[Daybook] SSO authorization failed: ' . $e->getMessage());
    http_response_code(403);
} catch (App\Exception\AuthenticationException $e) {
    error_log('[Daybook] SSO authentication error: ' . $e->getMessage());
    http_response_code(503);
}
