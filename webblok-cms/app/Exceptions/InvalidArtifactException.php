<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a community artifact fails validation or attempts an illegal
 * lifecycle transition.
 */
final class InvalidArtifactException extends RuntimeException {}
