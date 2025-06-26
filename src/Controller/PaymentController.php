<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PaymentController extends AbstractController
{
    #[Route('/checkout', name: 'checkout_page')]
    public function checkout(): Response
    {
        return $this->render('payment/checkout.html.twig', [
            'stripe_public_key' => $_ENV['STRIPE_PUBLIC_KEY'],
            'recaptcha_site_key' => $_ENV['RECAPTCHA_SITE_KEY'],

        ]);
    }

#[Route('/create-checkout-session', name: 'create_checkout_session', methods: ['POST'])]
public function createCheckoutSession(Request $request): JsonResponse
{
    \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

    $data = json_decode($request->getContent(), true);
    $quantity = isset($data['quantity']) ? max(1, (int)$data['quantity']) : 1;
    $recaptchaToken = $data['recaptchaToken'] ?? '';

    // ✅ Step 1: Verify reCAPTCHA v2 token
    $captchaResponse = file_get_contents(
        'https://www.google.com/recaptcha/api/siteverify?secret=' . $_ENV['RECAPTCHA_SECRET_KEY'] . '&response=' . $recaptchaToken
    );
    $captchaResult = json_decode($captchaResponse, true);

    // Debugging: Uncomment these lines to save debug info, Stats wont show when testing in localhost
    // file_put_contents(__DIR__ . '/debug_token.txt', $recaptchaToken);
    // file_put_contents(__DIR__ . '/debug_result.json', json_encode($captchaResult, JSON_PRETTY_PRINT));

    if (!($captchaResult['success'] ?? false)) {
        return $this->json(['error' => 'CAPTCHA failed. Please try again.'], 400);
    }

    // ✅ Step 2: Proceed with Stripe Checkout
    $unitPrice = 28 * 100; // USD cents

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => 'GoTix Concert Ticket + Booking Fee',
                ],
                'unit_amount' => $unitPrice,
            ],
            'quantity' => $quantity,
        ]],
        'mode' => 'payment',
        'success_url' => $this->generateUrl('checkout_success', [], 0) . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $this->generateUrl('checkout_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
    ]);

    return $this->json(['id' => $session->id]);
}


    // #[Route('/success', name: 'checkout_success')]
    // public function success(): Response
    // {
    //     return new Response('<h1>✅ Payment Successful!</h1>');
    // }
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


    // #[Route('/cancel', name: 'checkout_cancel')]
    // public function cancel(): Response
    // {
    //     return new Response('<h1>❌ Payment Cancelled.</h1>');
    // }

    #[Route('/cancel', name: 'checkout_cancel')]
    public function cancel(): Response
    {
        return $this->render('payment/cancel.html.twig');
    }
}
