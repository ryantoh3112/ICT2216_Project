<?php
namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mime\Address;

class EmailService
{
    private MailerInterface $mailer;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(MailerInterface $mailer, UrlGeneratorInterface $urlGenerator)
    {
        $this->mailer = $mailer;
        $this->urlGenerator = $urlGenerator;
    }

    public function sendOtp(string $toEmail, string $toName, string $otp): void
    {
        #$email = (new Email())
        #    ->from('ethicalhackingstudent818@gmail.com') // Must match the Gmail used
        #    ->to($toEmail)
        #    ->subject('Your OTP Code')
        #    ->html("<p>Hello $toName,<br>Your OTP is: <strong>$otp</strong><br>This code will expire in 10 minutes.</p>");
        $email = (new Email())
            ->from('help.optihealth@gmail.com')
            ->to($toEmail)
            ->subject('Your OTP Code')
            ->text("Hello $toName,\nYour OTP is: $otp.\nThis code will expire in 5 minutes.")
            ->html("<p>Hello $toName,<br>Your OTP is: <strong>$otp</strong><br>This code will expire in 5 minutes.</p>");
        // $email = (new Email())
        //     ->from('help.optihealth@gmail.com')
        //     ->to('help.optihealth@gmail.com')  // ← Hardcoded
        //     ->subject('Test OTP Email')
        //     ->text("Your OTP is 123456. This code will expire in 10 minutes.")
        //     ->html("<p>Your OTP is: <strong>123456</strong><br>This code will expire in 10 minutes.</p>");

        $this->mailer->send($email);
    }

    public function send2FAToggleOtp(string $toEmail, string $toName, string $otp, string $action): void
    {
        $subject = 'Confirm 2FA ' . ucfirst($action);

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@example.com', 'My GoTix'))
            ->to($toEmail)
            ->subject($subject)
            ->htmlTemplate('emails/2fa_settings_email.html.twig')
            ->context([
                'name' => $toName,
                'otp' => $otp,
                'action' => $action,
            ]);

        $this->mailer->send($email);
    }

    public function sendResetPasswordLink(string $email, string $name, string $token): void
    {
        $url = $this->urlGenerator->generate('auth_reset_password', [
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $emailMessage = (new TemplatedEmail())
            ->from(new Address('noreply@example.com', 'My GoTix'))
            ->to($email)
            ->subject('Reset Your GoTix Account Password')
            ->text("Hello $name,\nReset your password by clicking this link: $url.\nThis link will expire in 15 minutes.")
            ->htmlTemplate('emails/reset_pwd_email.html.twig')
            ->context([
                'name' => $name,
                'resetUrl' => $url,
            ]);

        $this->mailer->send($emailMessage);
    }
}
?>