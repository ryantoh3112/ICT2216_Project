<?php
// src/Controller/UserController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Entity\Auth;
use App\Entity\PurchaseHistory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

#[Route('/user', name: 'user_')]
final class UserController extends AbstractController
{
    #[Route('/profile', name: 'profile')]
    public function profile(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User|null $user */
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            $this->addFlash('error', 'Please log in.');
            return $this->redirectToRoute('auth_login_form');
        }

        // fetch email
        $auth = $em->getRepository(Auth::class)
                   ->findOneBy(['user' => $user]);

        // load all PurchaseHistory rows for this user, newest first
        /** @var PurchaseHistory[] $rows */
        $rows = $em->getRepository(PurchaseHistory::class)
                   ->findBy(['user' => $user], ['purchasedAt' => 'DESC']);

        // group by payment
        $purchases = [];
        foreach ($rows as $row) {
            $pid = $row->getPayment()->getId();
            if (!isset($purchases[$pid])) {
                $payment = $row->getPayment();
                $purchases[$pid] = [
                    'date'       => $row->getPurchasedAt(),
                    'items'      => [],
                    'subtotal'   => 0,
                    'bookingFee' => 0,    // placeholder
                    'total'      => $payment->getTotalPrice(),
                ];
            }

            $line = $row->getUnitPrice() * $row->getQuantity();
            $purchases[$pid]['items'][] = [
                'name' => $row->getProductName(),
                'qty'  => $row->getQuantity(),
                'unit' => $row->getUnitPrice(),
                'line' => $line,
            ];
            $purchases[$pid]['subtotal'] += $line;
        }

        // compute bookingFee per payment
        foreach ($purchases as &$purchase) {
            $purchase['bookingFee'] = max(0, $purchase['total'] - $purchase['subtotal']);
        }
        unset($purchase);

        return $this->render('user/profile.html.twig', [
            'user'      => $user,
            'email'     => $auth?->getEmail() ?? '—',
            'purchases' => $purchases,
        ]);
    }

    #[Route('/profile/update-username', name: 'update_username', methods: ['POST'])]
    public function updateUsername(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User|null $user */
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            $this->addFlash('error', 'Please log in.');
            return $this->redirectToRoute('auth_login_form');
        }

        $newName = trim($request->request->get('username', ''));
        if ($newName === '') {
            $this->addFlash('error', 'Username cannot be empty.');
            return $this->redirectToRoute('user_profile');
        }

        // Check uniqueness
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
    public function request2FAToggle(Request $request, EntityManagerInterface $em, EmailService $emailService): Response
    {
        /** @var User|null $user */
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            $this->addFlash('error', 'Please log in first.');
            return $this->redirectToRoute('auth_login_form');
        }

        $requested2FAState = $request->request->getBoolean('otp_enabled');
        if ($user->isOtpEnabled() === $requested2FAState) {
            $this->addFlash('info', 'No changes made to your 2FA settings.');
            return $this->redirectToRoute('user_profile');
        }

        // store pending state
        $request->getSession()->set('pending_2fa_toggle_state', $requested2FAState);

        // generate & email OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->setOtpCode($otp)
             ->setOtpExpiresAt((new \DateTimeImmutable())->add(new \DateInterval('PT5M')));
        $em->flush();

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
