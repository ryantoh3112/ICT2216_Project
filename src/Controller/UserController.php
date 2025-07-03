<?php

namespace App\Controller;

use App\Entity\Auth;
use App\Entity\PurchaseHistory;

// use App\Entity\History;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/user', name: 'user_')]
final class UserController extends AbstractController
{
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
                    'bookingFee' => 0,                    // placeholder
                    'total'      => $payment->getTotalPrice(),
                ];
            }

            $line = $row->getUnitPrice() * $row->getQuantity();
            $purchases[$pid]['items'][] = [
                'name'     => $row->getProductName(),
                'qty'      => $row->getQuantity(),
                'unit'     => $row->getUnitPrice(),
                'line'     => $line,
            ];
            $purchases[$pid]['subtotal'] += $line;
        }

        // now compute bookingFee per payment
        foreach ($purchases as &$purchase) {
            $purchase['bookingFee'] = max(0, $purchase['total'] - $purchase['subtotal']);
        }
        unset($purchase);

        return $this->render('user/profile.html.twig', [
            'user'        => $user,
            'email'       => $auth?->getEmail() ?? '—',
            'purchases'   => $purchases,
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
}
