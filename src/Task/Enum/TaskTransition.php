<?php

declare(strict_types=1);

namespace App\Task\Enum;

enum TaskTransition: string
{
    case START = 'start';
    case SUBMIT_FOR_REVIEW = 'submit_for_review';
    case COMPLETE = 'complete';
    case CANCEL = 'cancel';
}
