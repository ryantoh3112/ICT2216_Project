<?php

namespace App\Controller;

use App\Entity\CartItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
// Inject Security via constructor if needed

use Symfony\Component\Security\Core\Security;
class CartController extends AbstractController
{
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
        $item->setUser($user); // ✅ Set user from JWT

        $em->persist($item);
        $em->flush();

        return $this->json(['success' => true]);
    }
}
