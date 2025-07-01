<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Entity\CartItem;
use Doctrine\ORM\EntityManagerInterface;

final class PaymentController extends AbstractController
{
    #[Route('/checkout', name: 'checkout_page')]
    public function checkout(Request $request, EntityManagerInterface $em): Response
    {
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login_form');
        }

        $cartItems = $em->getRepository(CartItem::class)->findBy(['user' => $user]);

        return $this->render('payment/checkout.html.twig', [
            'stripe_public_key' => $_ENV['STRIPE_PUBLIC_KEY'],
            'recaptcha_site_key' => $_ENV['RECAPTCHA_SITE_KEY'],
            'cartItems' => $cartItems,
        ]);
    }

    #[Route('/create-checkout-session', name: 'create_checkout_session', methods: ['POST'])]
    public function createCheckoutSession(Request $request, EntityManagerInterface $em): JsonResponse
    {
        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            return $this->json(['error' => 'User not authenticated.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $recaptchaToken = $data['recaptchaToken'] ?? '';

        // ✅ reCAPTCHA verification
        $captchaResponse = file_get_contents(
            'https://www.google.com/recaptcha/api/siteverify?secret=' . $_ENV['RECAPTCHA_SECRET_KEY'] . '&response=' . $recaptchaToken
        );
        $captchaResult = json_decode($captchaResponse, true);
        if (!($captchaResult['success'] ?? false)) {
            return $this->json(['error' => 'CAPTCHA failed. Please try again.'], 400);
        }

        // ✅ Fetch user's cart items
        $cartItems = $em->getRepository(CartItem::class)->findBy(['user' => $user]);
        if (!$cartItems) {
            return $this->json(['error' => 'Cart is empty.'], 400);
        }

        $lineItems = [];
        foreach ($cartItems as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $item->getName(),
                    ],
                    'unit_amount' => (int) round($item->getPrice() * 100),
                ],
                'quantity' => $item->getQuantity(),
            ];
        }

        // Add booking fee line item
        $bookingFeePerItem = 3.00; // $3 per item
        $bookingFeeTotal = $bookingFeePerItem * count($cartItems);

        if ($bookingFeeTotal > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => 'Booking Fee',
                    ],
                    'unit_amount' => (int) round($bookingFeeTotal * 100),
                ],
                'quantity' => 1,
            ];
        }

        // ✅ Create session
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $this->generateUrl('checkout_success', [], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->generateUrl('checkout_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        return $this->json(['id' => $session->id]);
    }

    #[Route('/success', name: 'checkout_success')]
    public function success(Request $request): Response
    {
        \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        $sessionId = $request->query->get('session_id');

        if (!$sessionId) {
            return $this->redirectToRoute('checkout_page');
        }

        $session = \Stripe\Checkout\Session::retrieve($sessionId);
        $lineItem = \Stripe\Checkout\Session::allLineItems($sessionId, ['limit' => 1])->data[0];

        $quantity = $lineItem['quantity'] ?? 1;
        $unitAmount = $lineItem['price']['unit_amount'] ?? 2500; // cents
        $total = number_format(($quantity * $unitAmount) / 100, 2);

        return $this->render('payment/success.html.twig', [
            'quantity' => $quantity,
            'total' => $total
        ]);
    }

    #[Route('/cancel', name: 'checkout_cancel')]
    public function cancel(): Response
    {
        return $this->render('payment/cancel.html.twig');
    }
}
