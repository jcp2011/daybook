<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Thrown when the authentication infrastructure is unavailable or misconfigured.
 *
 * Covers LDAP connection failures and service-account bind errors.
 * Wrong credentials are not an authentication exception - they return false.
 */
class AuthenticationException extends \RuntimeException
{
}
