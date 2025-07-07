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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
// Inject Security via constructor if needed

use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

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

    //  #[Route('/checkout', name: 'checkout_page')]
    // public function checkout(
    //     Request $request,
    //     CartItemRepository $cartRepo,
    //     string $stripe_public_key
    // ): Response {
    //     /** @var \App\Entity\User $user */
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user) {
    //         return $this->redirectToRoute('auth_login');
    //     }

    //     // 1) fetch all cart items for this user
    //     $rawItems = $cartRepo->findBy(['user' => $user]);

    //     // 2) group by ticket name
    //     $groupedCartItems = [];
    //     foreach ($rawItems as $item) {
    //         $key = $item->getName();
    //         if (!isset($groupedCartItems[$key])) {
    //             $groupedCartItems[$key] = [
    //                 'name'     => $item->getName(),
    //                 'price'    => $item->getPrice(),
    //                 'quantity' => $item->getQuantity(),
    //             ];
    //         } else {
    //             $groupedCartItems[$key]['quantity'] += $item->getQuantity();
    //         }
    //     }

    //     // 3) render the Twig template, passing groupedCartItems
    //     return $this->render('payment/checkout.html.twig', [
    //         'groupedCartItems'  => $groupedCartItems,
    //         'stripe_public_key' => $stripe_public_key,
    //     ]);
    // }
    //     #[Route('/cart/update/{id}', name: 'cart_update', methods: ['POST'])]
    // public function updateItem(
    //     int $id,
    //     Request $request,
    //     CartItemRepository $repo,
    //     EntityManagerInterface $em
    // ): RedirectResponse {
    //     // 1) Ensure user is logged in via your JWT listener
    //     /** @var User|null $user */
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user) {
    //         return $this->redirectToRoute('auth_login');
    //     }

    //     // 2) Load the CartItem and verify ownership
    //     $item = $repo->find($id);
    //     if (!$item || $item->getUser() !== $user) {
    //         throw $this->createNotFoundException();
    //     }

    //     // 3) Clamp and set the new quantity
    //     $newQty = max(1, (int)$request->request->get('quantity', 1));
    //     $item->setQuantity($newQty);

    //     $em->flush();

    //     // 4) Redirect back to the same checkout page
    //     return $this->redirectToRoute('checkout_page');
    // }

    //     #[Route('/cart/update/{ticketTypeId}', name: 'cart_update', methods: ['POST'])]
    // public function updateItem(
    //     int $ticketTypeId,
    //     Request $request,
    //     CartItemRepository $repo,
    //     EntityManagerInterface $em
    // ): RedirectResponse {
    //     // 1) Ensure user is logged in
    //     /** @var User|null $user */
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user) {
    //         return $this->redirectToRoute('auth_login');
    //     }

    //     // 2) Parse & clamp the requested quantity
    //     $newQty = max(1, (int) $request->request->get('quantity', 1));

    //     // 3) Fetch all CartItem rows for this user + ticketType
    //     /** @var CartItem[] $items */
    //     $items = $repo->findBy([
    //         'user'       => $user,
    //         'ticketType' => $em->getReference(\App\Entity\TicketType::class, $ticketTypeId),
    //     ]);

    //     if (empty($items)) {
    //         throw $this->createNotFoundException('No cart items for that ticket type.');
    //     }

    //     // 4) Remove the old rows
    //     foreach ($items as $i) {
    //         $em->remove($i);
    //     }

    //     // 5) Re-create exactly one CartItem with the new quantity
    //     $tt = $items[0]->getTicketType();
    //     $item = new CartItem();
    //     $item
    //         ->setUser($user)
    //         ->setTicketType($tt)
    //         ->setName($tt->getName())
    //         ->setPrice($tt->getPrice())
    //         ->setQuantity($newQty);
    //     $em->persist($item);

    //     $em->flush();

    //     // 6) Go back to checkout
    //     return $this->redirectToRoute('checkout_page');
    // }
#[Route('/cart/update/{ticketTypeId}', name: 'cart_update', methods: ['POST'])]
public function updateItem(
    int $ticketTypeId,
    Request $request,
    CartItemRepository $repo,
    EntityManagerInterface $em
): RedirectResponse {
    // 1) Ensure user is logged in
    /** @var User|null $user */
    $user = $request->attributes->get('jwt_user');
    if (!$user) {
        return $this->redirectToRoute('auth_login');
    }

    // 2) Validate Symfony CSRF token
    $submitted = $request->request->get('_csrf_token', '');
    if (!$this->isCsrfTokenValid('cart_update_' . $ticketTypeId, $submitted)) {
    throw $this->createAccessDeniedException('Invalid CSRF token');
}

    // 3) Clamp & persist the new quantity
    $newQty = max(1, (int) $request->request->get('quantity', 1));

    /** @var CartItem[] $items */
    $items = $repo->findBy([
        'user'       => $user,
        'ticketType' => $em->getReference(\App\Entity\TicketType::class, $ticketTypeId),
    ]);

    if (empty($items)) {
        throw $this->createNotFoundException('No cart items for that ticket type.');
    }

    foreach ($items as $i) {
        $em->remove($i);
    }

    $tt   = $items[0]->getTicketType();
    $item = new CartItem();
    $item
        ->setUser($user)
        ->setTicketType($tt)
        ->setName($tt->getName())
        ->setPrice($tt->getPrice())
        ->setQuantity($newQty);

    $em->persist($item);
    $em->flush();

    // 4) Go back to checkout
    return $this->redirectToRoute('checkout_page');
}


    //     #[Route('/cart/remove/{ticketTypeId}', name: 'cart_remove', methods: ['POST'])]
    // public function removeItem(
    //     int $ticketTypeId,
    //     Request $request,
    //     CartItemRepository $repo,
    //     EntityManagerInterface $em
    // ): RedirectResponse {
    //     // 1) Ensure user is logged in
    //     /** @var User|null $user */
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user) {
    //         return $this->redirectToRoute('auth_login');
    //     }

    //     // 2) Load all CartItem rows for this user + ticketType
    //     $ttRef = $em->getReference(\App\Entity\TicketType::class, $ticketTypeId);
    //     $items = $repo->findBy([
    //         'user'       => $user,
    //         'ticketType' => $ttRef,
    //     ]);

    //     // 3) Remove them
    //     foreach ($items as $item) {
    //         $em->remove($item);
    //     }
    //     $em->flush();

    //     // 4) Back to checkout
    //     return $this->redirectToRoute('checkout_page');
    // }
    #[Route('/cart/remove/{ticketTypeId}', name: 'cart_remove', methods: ['POST'])]
public function removeItem(
    int $ticketTypeId,
    Request $request,
    CartItemRepository $repo,
    EntityManagerInterface $em
): RedirectResponse {
    // 1) Ensure user is logged in
    /** @var User|null $user */
    $user = $request->attributes->get('jwt_user');
    if (!$user) {
        return $this->redirectToRoute('auth_login');
    }

    // 2) Validate Symfony CSRF token
    $submitted = $request->request->get('_csrf_token', '');
    if (!$this->isCsrfTokenValid('cart_remove_' . $ticketTypeId, $submitted)) {
        throw new AccessDeniedException('Invalid CSRF token');
    }

    // 3) Remove all matching CartItem rows
    $ttRef = $em->getReference(\App\Entity\TicketType::class, $ticketTypeId);
    $items = $repo->findBy([
        'user'       => $user,
        'ticketType' => $ttRef,
    ]);

    foreach ($items as $item) {
        $em->remove($item);
    }

    $em->flush();

    // 4) Back to checkout
    return $this->redirectToRoute('checkout_page');
}
    
}