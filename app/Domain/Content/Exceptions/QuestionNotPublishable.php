<?php

declare(strict_types=1);

namespace App\Domain\Content\Exceptions;

use DomainException;

class QuestionNotPublishable extends DomainException
{
    /**
     * @param  list<string>  $blockers  human-readable reasons, shown to the admin
     */
    public function __construct(public readonly array $blockers)
    {
        parent::__construct(__('This item can’t be published yet: :reasons', ['reasons' => implode(' ', $blockers)]));
    }
}
