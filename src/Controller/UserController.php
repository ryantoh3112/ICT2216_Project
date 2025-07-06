<?php

namespace App\Controller;

// use App\Entity\History;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Entity\Auth;
use App\Entity\PurchaseHistory;
use App\Repository\UserRepository;
use App\Repository\AuthRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Service\EmailService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[Route('/user', name: 'user_')]
final class UserController extends AbstractController
{
    // #[Route('/profile', name: 'profile')]
    // public function profile(
    //     Request $request,
    //     EntityManagerInterface $em
    // ): Response {
    //     /** @var User|null $user */
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user) {
    //         $this->addFlash('error', 'Please log in.');
    //         return $this->redirectToRoute('auth_login');
    //     }

    //     // fetch email
    //     $auth = $em->getRepository(Auth::class)
    //                ->findOneBy(['user' => $user]);

    //     // load all PurchaseHistory rows for this user, newest first
    //     /** @var PurchaseHistory[] $rows */
    //     $rows = $em->getRepository(PurchaseHistory::class)
    //                ->findBy(['user' => $user], ['purchasedAt' => 'DESC']);

    //     // group by payment
    //     $purchases = [];
    //     foreach ($rows as $row) {
    //         $pid = $row->getPayment()->getId();
    //         if (!isset($purchases[$pid])) {
    //             $payment = $row->getPayment();
    //             $purchases[$pid] = [
    //                 'date'       => $row->getPurchasedAt(),
    //                 'items'      => [],
    //                 'subtotal'   => 0,
    //                 'bookingFee' => 0,                    // placeholder
    //                 'total'      => $payment->getTotalPrice(),
    //             ];
    //         }

    //         $line = $row->getUnitPrice() * $row->getQuantity();
    //         $purchases[$pid]['items'][] = [
    //             'name'     => $row->getProductName(),
    //             'qty'      => $row->getQuantity(),
    //             'unit'     => $row->getUnitPrice(),
    //             'line'     => $line,
    //         ];
    //         $purchases[$pid]['subtotal'] += $line;
    //     }

    //     // now compute bookingFee per payment
    //     foreach ($purchases as &$purchase) {
    //         $purchase['bookingFee'] = max(0, $purchase['total'] - $purchase['subtotal']);
    //     }
    //     unset($purchase);

    //     return $this->render('user/profile.html.twig', [
    //         'user'        => $user,
    //         'email'       => $auth?->getEmail() ?? '—',
    //         'purchases'   => $purchases,
    //     ]);
    // }


       #[Route('/profile', name: 'profile')]
    public function profile(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        /** @var User|null $user */
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            $this->addFlash('error', 'Please log in.');
            return $this->redirectToRoute('auth_login');
        }

        // fetch email
        $auth = $em->getRepository(Auth::class)
                   ->findOneBy(['user' => $user]);

        // load all PurchaseHistory rows for this user, newest first
        /** @var PurchaseHistory[] $rows */
        $rows = $em->getRepository(PurchaseHistory::class)
                   ->findBy(['user' => $user], ['purchasedAt' => 'DESC']);

        // group purchases by payment ID
        $purchases = [];
        foreach ($rows as $row) {
            $pid = $row->getPayment()->getId();
            if (!isset($purchases[$pid])) {
                $payment = $row->getPayment();
                $purchases[$pid] = [
                    'date'       => $row->getPurchasedAt(),
                    'items'      => [],
                    'subtotal'   => 0,
                    'bookingFee' => 0,
                    'total'      => $payment->getTotalPrice(),
                ];
            }

            // group each product name within this payment
            $prod = $row->getProductName();
            if (!isset($purchases[$pid]['items'][$prod])) {
                $purchases[$pid]['items'][$prod] = [
                    'name'     => $prod,
                    'qty'      => 0,
                    'unit'     => $row->getUnitPrice(),
                    'line'     => 0,
                ];
            }

            $qty  = $row->getQuantity();
            $unit = $row->getUnitPrice();
            $line = $unit * $qty;

            $purchases[$pid]['items'][$prod]['qty']  += $qty;
            $purchases[$pid]['items'][$prod]['line'] += $line;
            $purchases[$pid]['subtotal']            += $line;
        }

        // now compute bookingFee and flatten each items array
        foreach ($purchases as &$purchase) {
            $purchase['bookingFee'] = max(0, $purchase['total'] - $purchase['subtotal']);
            // flatten: from name-keyed to indexed array
            $purchase['items'] = array_values($purchase['items']);
        }
        unset($purchase);

        return $this->render('user/profile.html.twig', [
            'user'      => $user,
            'email'     => $auth?->getEmail() ?? '—',
            'purchases' => $purchases,
        ]);
    }
    
    #[Route('/profile/update-username', name: 'update_username', methods: ['POST'])]
    public function updateUsername(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        /** @var User|null $user */
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            $this->addFlash('error', 'Please log in.');
            return $this->redirectToRoute('auth_login');
        }

        $newName = trim($request->request->get('username', ''));
        if ($newName === '') {
            $this->addFlash('error', 'Username cannot be empty.');
            return $this->redirectToRoute('user_profile');
        }

        // Check for uniqueness
        $existing = $em->getRepository(User::class)
                       ->findOneBy(['name' => $newName]);
        if ($existing && $existing->getId() !== $user->getId()) {
            $this->addFlash('error', 'Username already taken.');
            return $this->redirectToRoute('user_profile');
        }

        $user->setName($newName)
             ->setUpdatedAt(new \DateTime());
        $em->flush();

        $this->addFlash('success', 'Username updated.');
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

    #[Route('/profile/change-password', name: 'change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            $this->addFlash('error', 'Please log in first.');
            return $this->redirectToRoute('auth_login');
        }

        $currentPassword = $request->request->get('current_password');
        $newPassword = $request->request->get('new_password');
        $confirmPassword = $request->request->get('confirm_password');

        if (!$passwordHasher->isPasswordValid($user->getAuth(), $currentPassword)) {
            $this->addFlash('password_error', 'Current password is incorrect.');
            return $this->redirectToRoute('user_profile');
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('password_error', 'New passwords do not match.');
            return $this->redirectToRoute('user_profile');
        }

        if (strlen($newPassword) < 8) {
            $this->addFlash('password_error', 'Password must be at least 8 characters.');
            return $this->redirectToRoute('user_profile');
        }

        if ($passwordHasher->isPasswordValid($user->getAuth(), $newPassword)) {
            $this->addFlash('password_error', 'New password must be different from your current password.');
            return $this->redirectToRoute('user_profile');
        }

        if (
            !preg_match('/[A-Z]/', $newPassword) || // Uppercase
            !preg_match('/[a-z]/', $newPassword) || // Lowercase
            !preg_match('/\d/', $newPassword) ||    // Digit
            !preg_match('/[^A-Za-z0-9]/', $newPassword) // Special char
        ) {
            $this->addFlash('password_error', 'Password must contain uppercase, lowercase, number, and special character.');
            return $this->redirectToRoute('user_profile');
        }

        $hashedPassword = $passwordHasher->hashPassword($user->getAuth(), $newPassword);
        $user->getAuth()->setPassword($hashedPassword);
        $entityManager->flush();

        $this->addFlash('success', 'Password changed successfully.');
        return $this->redirectToRoute('user_profile', ['tab' => 'change-password']);
    }
}