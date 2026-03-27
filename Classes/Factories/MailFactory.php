<?php

declare(strict_types=1);

namespace FormatD\Mailer\Factories;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MailFactory
{
    /**
     * @param string $subject
     * @param Address[]|Address|string $to
     * @param Address|string $from
     * @param string|null $text
     * @param string|null $html
     * @param Address[]|Address|string|null $replyTo
     * @param Address[]|Address|string|null $cc
     * @param Address[]|Address|string|null $bcc
     * @return Email
     */
    public function createMail(
        string $subject,
        array|Address|string $to,
        Address|string $from,
        string $text = null,
        string $html = null,
        array|Address|string $replyTo = null,
        array|Address|string $cc = null,
        array|Address|string $bcc = null
    ): Email {
        $mail = new Email();

        $mail
            ->from($from)
            ->subject($subject);

        if (is_array($to)) {
            $mail->to(...$to);
        } else {
            $mail->to($to);
        }

        if ($replyTo) {
            if (is_array($replyTo)) {
                $mail->replyTo(...$replyTo);
            } else {
                $mail->replyTo($replyTo);
            }
        }

        if ($cc) {
            if (is_array($cc)) {
                $mail->cc(...$cc);
            } else {
                $mail->cc($cc);
            }
        }

        if ($bcc) {
            if (is_array($bcc)) {
                $mail->bcc(...$bcc);
            } else {
                $mail->bcc($bcc);
            }
        }

        if ($text) {
            $mail->text($text);
        }

        if ($html) {
            $mail->html($html);
        }

        return $mail;
    }
}
