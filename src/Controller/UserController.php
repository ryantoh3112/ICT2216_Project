<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Entity\Auth;
use App\Repository\UserRepository;
use App\Repository\AuthRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Service\EmailService;

#[Route('/user', name: 'user_')]
final class UserController extends AbstractController
{
    #[Route('/profile', name: 'profile')]
    public function profile(
        Request $request, 
        AuthRepository $authRepository
    ): Response
    {
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            $this->addFlash('error', 'Please log in.');
            return $this->redirectToRoute('auth_login');
        }

        $auth = $authRepository->findOneBy(['user' => $user]);

        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'email' => $auth->getEmail()
        ]);

    }

    #[Route('/profile/update-username', name: 'update_username', methods: ['POST'])]
    public function updateUsername(
        Request $request, 
        EntityManagerInterface $em, 
        UserRepository $userRepo): Response
    {
        $user = $request->attributes->get('jwt_user');

        if (!$user) {
            $this->addFlash('error', 'Please log in.');
            return $this->redirectToRoute('auth_login');
        }


        if (!$user) {
            throw $this->createNotFoundException('User not found.');
        }

        $newName = trim($request->request->get('username'));

        // Check if the username field is empty
        if (empty($newName)) {
            $this->addFlash('error', 'Username cannot be empty.');
            return $this->redirectToRoute('user_profile');
        }

        // Check if the new username is already taken by another user
        $existingUser = $userRepo->findOneBy(['name' => $newName]);

        if ($existingUser && $existingUser->getId() !== $user->getId()) {
            $this->addFlash('error', 'This username is already taken. Please choose another one.');
            return $this->redirectToRoute('user_profile');
        }

        $user->setName($newName);
        $user->setUpdatedAt(new \DateTime()); // To update the field Updated_At
        $em->flush();
        $this->addFlash('success', 'Username updated successfully.');
        return $this->redirectToRoute('user_profile');
    }

    #[Route('/profile/2fa/request', name: 'request_2fa_toggle', methods: ['POST'])]
    public function request2FAToggle(
        Request $request,
        EntityManagerInterface $em,
        EmailService $emailService
    ): Response {
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            $this->addFlash('error', 'Please log in first.');
            return $this->redirectToRoute('auth_login');
        }

        $requested2FAState = $request->request->getBoolean('otp_enabled');

        // If no change made to 2FA settings, just flash and return
        if ($user->isOtpEnabled() === $requested2FAState) {
            $this->addFlash('info', 'No changes made to your 2FA settings.');
            return $this->redirectToRoute('user_profile');
        }

        // Store the requested state in session
        $session = $request->getSession();
        $session->set('pending_2fa_toggle_state', $requested2FAState);

        // Generate OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->setOtpCode($otp);
        $user->setOtpExpiresAt((new \DateTimeImmutable())->add(new \DateInterval('PT5M')));
        $em->flush();

        // Send 2FA-specific OTP email
        $emailService->send2FAToggleOtp(
            $user->getAuth()->getEmail(),
            $user->getName(),
            $otp,
            $requested2FAState ? 'enable' : 'disable'
        );

        $this->addFlash('success', 'A verification code has been sent to your email.');
        return $this->redirectToRoute('auth_verify_otp_form');
    }
}