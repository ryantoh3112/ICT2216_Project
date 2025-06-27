<?php
namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailOtpService
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function sendOtp(string $toEmail, string $toName, string $otp): void
    {
        #$email = (new Email())
        #    ->from('ethicalhackingstudent818@gmail.com') // Must match the Gmail used
        #    ->to($toEmail)
        #    ->subject('Your OTP Code')
        #    ->html("<p>Hello $toName,<br>Your OTP is: <strong>$otp</strong><br>This code will expire in 10 minutes.</p>");
        #$email = (new Email())
        #    ->from('ethicalhackingstudent818@gmail.com')
        #    ->to($toEmail)
        #    ->subject('Your OTP Code')
        #    ->text("Hello $toName,\nYour OTP is: $otp.\nThis code will expire in 10 minutes.")
        #    ->html("<p>Hello $toName,<br>Your OTP is: <strong>$otp</strong><br>This code will expire in 10 minutes.</p>");
        $email = (new Email())
            ->from('help.optihealth@gmail.com')
            ->to('help.optihealth@gmail.com')  // ← Hardcoded
            ->subject('Test OTP Email')
            ->text("Your OTP is 123456. This code will expire in 10 minutes.")
            ->html("<p>Your OTP is: <strong>123456</strong><br>This code will expire in 10 minutes.</p>");

        $this->mailer->send($email);
    }
}
?>