<?php

namespace OroB2B\Bundle\CustomerBundle\Mailer;

use Oro\Bundle\UserBundle\Mailer\BaseProcessor;

use OroB2B\Bundle\CustomerBundle\Entity\AccountUser;

class Processor extends BaseProcessor
{
    const WELCOME_EMAIL_TEMPLATE_NAME = 'welcome_email';
    const CONFIRMATION_EMAIL_TEMPLATE_NAME = 'confirmation_email';

    /**
     * @param AccountUser $accountUser
     * @param string $password
     * @return int
     */
    public function sendWelcomeNotification(AccountUser $accountUser, $password)
    {
        return $this->getEmailTemplateAndSendEmail(
            $accountUser,
            static::WELCOME_EMAIL_TEMPLATE_NAME,
            ['entity' => $accountUser, 'password' => $password]
        );
    }

    /**
     * @param AccountUser $accountUser
     * @param $confirmationToken
     * @return int
     */
    public function sendConfirmationEmail(AccountUser $accountUser, $confirmationToken)
    {
        return $this->getEmailTemplateAndSendEmail(
            $accountUser,
            static::CONFIRMATION_EMAIL_TEMPLATE_NAME,
            ['entity' => $accountUser, 'token' => $confirmationToken]
        );
    }
}
