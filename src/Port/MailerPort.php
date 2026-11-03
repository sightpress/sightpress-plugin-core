<?php

declare(strict_types=1);

namespace SightPress\Plugin\Core\Port;

use SightPress\Plugin\Core\Dto\TransactionalMail;

/** The core's service email, for transactional mail someone asked for. Events go through NotifierPort. */
interface MailerPort
{
    /** Queues the message: mail is always sent by a job, never in the request. */
    public function send(TransactionalMail $mail): void;
}
