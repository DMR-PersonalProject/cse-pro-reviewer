<?php

declare(strict_types=1);

namespace App\Domain\Attempts\Exceptions;

use DomainException;

/**
 * The server would not take this change; the message is shown to the user.
 */
class AnswerRefused extends DomainException {}
