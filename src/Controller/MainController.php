<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

final class MainController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('index/index.html.twig', [
            'controller_name' => 'IndexController',
        ]);
    }

    // #[Route('/events', name: 'events_page')]
    // public function event(): Response
    // {
    //     return $this->render('events/events.html.twig', [
    //         'controller_name' => 'IndexController',
    //     ]);
    // }

    // #[Route('/login', name: 'login_page')]
    // public function login(): Response
    // {
    //     return $this->render('authentication/login.html.twig', [
    //         'controller_name' => 'IndexController',
    //     ]);
    // }

    // #[Route('/register', name: 'register_page')]
    // public function register(): Response
    // {
    //     return $this->render('authentication/register.html.twig', [
    //         'controller_name' => 'IndexController',
    //     ]);
    // }

    // Payment routes

    #[Route('/checkout', name: 'checkout_page')]    
    public function checkout(): Response
    {
        return $this->render('payment/checkout.html.twig', [
            'stripe_public_key' => $_ENV['STRIPE_PUBLIC_KEY'] // or use parameter bag / env var
        ]);
    }

    // #[Route('/success', name: 'checkout_success')]
    // public function success(): Response
    // {
    //     return $this->render('payment/success.html.twig');
    // }

    // PaymentController.php

    // #[Route('/success', name: 'checkout_success')]
    // public function success(Request $request): Response
    // {
    //     \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

    //     $sessionId = $request->query->get('session_id');
    //     $session = \Stripe\Checkout\Session::retrieve($sessionId);
    //     $lineItems = \Stripe\Checkout\Session::allLineItems($sessionId, ['limit' => 5]);

    //     return $this->render('payment/success.html.twig', [
    //         'session' => $session,
    //         'line_items' => $lineItems,
    //     ]);
    // }


    // #[Route('/cancel', name: 'checkout_cancel')]
    // public function cancel(): Response
    // {
    //     return $this->render('payment/cancel.html.twig');
    // }


}
