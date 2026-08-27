<?php

declare(strict_types=1);

namespace Daraja\Enums;

/**
 * Suspend/resume operation for {@see \Daraja\Services\IotSimManagement::suspendOrResumeSubscriber()}.
 */
enum SimSubscriberOperation: string
{
    case Suspend = 'suspend';
    case Resume  = 'resume';
}
