<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Thrown when a user is authenticated but not authorized to access the application.
 *
 * Typically raised when the user's account is not a member of the required AD group.
 */
class AuthorizationException extends \RuntimeException
{
}
