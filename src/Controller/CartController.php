<?php

namespace App\Controller;

use App\Entity\CartItem;
use App\Entity\User;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
// Inject Security via constructor if needed

use Symfony\Component\Security\Core\Security;
class CartController extends AbstractController
{
    // #[Route('/sync-cart', name: 'sync_cart', methods: ['POST'])]
    // public function syncCart(Request $request, EntityManagerInterface $em): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);

    //     if (!isset($data['name'], $data['price'])) {
    //         return $this->json(['success' => false, 'error' => 'Invalid data'], 400);
    //     }

    //     /** @var User|null $user */
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user instanceof User) {
    //         return $this->json(['success' => false, 'error' => 'User not authenticated'], 401);
    //     }

    //     $item = new CartItem();
    //     $item->setName($data['name']);
    //     $item->setPrice($data['price']);
    //     $item->setImage($data['image'] ?? null);
    //     $item->setQuantity($data['quantity'] ?? 1);
    //     $item->setUser($user); // ✅ Set user from JWT

    //     $em->persist($item);
    //     $em->flush();

    //     return $this->json(['success' => true]);
    // }

        #[Route('/sync-cart', name: 'sync_cart', methods: ['POST'])]
    public function syncCart(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'], $data['price'])) {
            return $this->json(['success' => false, 'error' => 'Invalid data'], 400);
        }

        /** @var User|null $user */
        $user = $request->attributes->get('jwt_user');
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'error' => 'User not authenticated'], 401);
        }

        $item = new CartItem();
        $item->setName($data['name']);
        $item->setPrice($data['price']);
        $item->setImage($data['image'] ?? null);
        $item->setQuantity($data['quantity'] ?? 1);
        $item->setUser($user);

        $em->persist($item);
        $em->flush();

        return $this->json(['success' => true]);
    }

    //   #[Route('/checkout', name: 'checkout_page')]
    // public function checkout(Request $request, CartItemRepository $cartRepo, string $stripe_public_key): Response
    // {
    //     /** @var User $user */
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user) {
    //         return $this->redirectToRoute('auth_login');
    //     }

    //     $cartItems = $cartRepo->findBy(['user' => $user]);

    //     return $this->render('payment/checkout.html.twig', [
    //         'cartItems'         => $cartItems,
    //         'stripe_public_key' => $stripe_public_key,
    //     ]);
    // }

     #[Route('/checkout', name: 'checkout_page')]
    public function checkout(
        Request $request,
        CartItemRepository $cartRepo,
        string $stripe_public_key
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login');
        }

        // 1) fetch all cart items for this user
        $rawItems = $cartRepo->findBy(['user' => $user]);

        // 2) group by ticket name
        $groupedCartItems = [];
        foreach ($rawItems as $item) {
            $key = $item->getName();
            if (!isset($groupedCartItems[$key])) {
                $groupedCartItems[$key] = [
                    'name'     => $item->getName(),
                    'price'    => $item->getPrice(),
                    'quantity' => $item->getQuantity(),
                ];
            } else {
                $groupedCartItems[$key]['quantity'] += $item->getQuantity();
            }
        }

        // 3) render the Twig template, passing groupedCartItems
        return $this->render('payment/checkout.html.twig', [
            'groupedCartItems'  => $groupedCartItems,
            'stripe_public_key' => $stripe_public_key,
        ]);
    }
}