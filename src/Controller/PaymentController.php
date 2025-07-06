<?php

namespace App\Controller;

use App\Entity\PurchaseHistory;
use App\Entity\CartItem;
use App\Entity\History;
use App\Entity\Payment;
use App\Entity\TicketType;
use App\Entity\Ticket;
use App\Repository\PaymentRepository;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PaymentController extends AbstractController
{
    //  #[Route('/checkout', name: 'checkout_page')]
    // public function checkout(Request $request, EntityManagerInterface $em): Response
    // {
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user) {
    //         return $this->redirectToRoute('auth_login_form');
    //     }

    //     $cartItems = $em->getRepository(CartItem::class)
    //                    ->findBy(['user' => $user]);

    //     return $this->render('payment/checkout.html.twig', [
    //         'stripe_public_key'  => $_ENV['STRIPE_PUBLIC_KEY'],
    //         'recaptcha_site_key' => $_ENV['RECAPTCHA_SITE_KEY'],
    //         'cartItems'          => $cartItems,
    //     ]);
    // }

        #[Route('/checkout', name: 'checkout_page')]
    public function checkout(
        Request $request,
        CartItemRepository $cartRepo,
        EntityManagerInterface $em
    ): Response {
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login_form');
        }

        // fetch all CartItem entities for this user
        $rawItems = $cartRepo->findBy(['user' => $user]);

        // group by ticket name
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

        return $this->render('payment/checkout.html.twig', [
            'stripe_public_key'  => $_ENV['STRIPE_PUBLIC_KEY'],
            'recaptcha_site_key' => $_ENV['RECAPTCHA_SITE_KEY'],
            'groupedCartItems'   => $groupedCartItems,
        ]);
    }

    #[Route('/create-checkout-session', name: 'create_checkout_session', methods: ['POST'])]
    public function createCheckoutSession(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        // configure Stripe
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        // AUTH
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            return $this->json(['error' => 'User not authenticated.'], 403);
        }

        // LOAD CART
        /** @var CartItem[] $cartItems */
        $cartItems = $em->getRepository(CartItem::class)
                        ->findBy(['user' => $user]);
        if (empty($cartItems)) {
            return $this->json(['error' => 'Cart is empty.'], 400);
        }

        // BUILD LINE ITEMS
        $lineItems = [];
        foreach ($cartItems as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency'     => 'usd',
                    'product_data' => ['name' => $item->getName()],
                    'unit_amount'  => (int) round($item->getPrice() * 100),
                ],
                'quantity' => $item->getQuantity(),
            ];
        }
        $bookingFee = 3.00 * count($cartItems);
        if ($bookingFee > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency'     => 'usd',
                    'product_data' => ['name' => 'Booking Fee'],
                    'unit_amount'  => (int) round($bookingFee * 100),
                ],
                'quantity' => 1,
            ];
        }

        // CREATE STRIPE SESSION
        $session = StripeSession::create([
            'payment_method_types' => ['card'],
            'line_items'           => $lineItems,
            'mode'                 => 'payment',
            'success_url'          => $this->generateUrl(
                                         'checkout_success',
                                         [],
                                         UrlGeneratorInterface::ABSOLUTE_URL
                                     ) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'           => $this->generateUrl(
                                         'checkout_cancel',
                                         [],
                                         UrlGeneratorInterface::ABSOLUTE_URL
                                     ),
        ]);

        // COMPUTE TOTAL PRICE
        $subtotal = array_reduce(
            $cartItems,
            fn($sum, CartItem $i) => $sum + ($i->getPrice() * $i->getQuantity()),
            0
        );
        $total = $subtotal + $bookingFee;

        // PERSIST Payment with session ID
        $payment = (new Payment())
            ->setUser($user)
            ->setTotalPrice($total)
            ->setPaymentMethod('stripe')
            ->setPaymentDateTime(new \DateTime())
            ->setSessionId($session->id)
            ->setStatus('pending');

        $em->persist($payment);
        $em->flush();

        // PERSIST History with the same session ID
        $history = (new History())
            ->setUser($user)
            ->setPayment($payment)
            ->setAction('Checkout session created')
            ->setTimestamp(new \DateTime())
            ->setSessionId($session->id)
            ->setStatus('pending');

        $em->persist($history);
        $em->flush();

        // STORE for /success
        $request->getSession()->set('last_payment_id', $payment->getId());

        return $this->json(['id' => $session->id]);
    }

// #[Route('/success', name: 'checkout_success')]
// public function success(
//     Request $request,
//     EntityManagerInterface $em,
//     PaymentRepository $payments
// ): Response {
//     // — AUTH & PAYMENT lookup (unchanged) —
//     $user = $request->attributes->get('jwt_user');
//     if (!$user) {
//         return $this->redirectToRoute('auth_login_form');
//     }

//     $paymentId = $request->getSession()->get('last_payment_id');
//     if (!$paymentId) {
//         return $this->redirectToRoute('checkout_page');
//     }

//     $payment = $payments->find($paymentId);
//     if (
//         !$payment ||
//         $payment->getUser() !== $user ||
//         $payment->getStatus() !== 'completed'
//     ) {
//         return $this->redirectToRoute('checkout_page');
//     }

//     // — LOAD PURCHASED ITEMS from PurchaseHistory —
//     /** @var PurchaseHistory[] $records */
//     $records = $em->getRepository(PurchaseHistory::class)
//                   ->findBy(['payment' => $payment]);

//     // — BUILD A SIMPLE ARRAY & CALC SUBTOTAL —
//     $bought   = [];
//     $subtotal = 0;
//     foreach ($records as $rec) {
//         $line = $rec->getUnitPrice() * $rec->getQuantity();
//         $bought[] = [
//             'productName' => $rec->getProductName(),
//             'quantity'    => $rec->getQuantity(),
//             'unitPrice'   => $rec->getUnitPrice(),
//             'line'        => $line,
//         ];
//         $subtotal += $line;
//     }

//     // — DERIVE BOOKING FEE: payment.total − subtotal —
//     $total      = $payment->getTotalPrice();
//     $bookingFee = max(0, $total - $subtotal);

//     // — PASS EVERYTHING INTO TWIG —
//     return $this->render('payment/success.html.twig', [
//         'bought'     => $bought,
//         'subtotal'   => number_format($subtotal, 2),
//         'bookingFee' => number_format($bookingFee, 2),
//         'total'      => number_format($total, 2),
//     ]);
//     }


    #[Route('/success', name: 'checkout_success')]
    public function success(
        Request $request,
        EntityManagerInterface $em,
        PaymentRepository $payments
    ): Response {
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login_form');
        }
        $paymentId = $request->getSession()->get('last_payment_id');
        if (!$paymentId) {
            return $this->redirectToRoute('checkout_page');
        }
        $payment = $payments->find($paymentId);
        if (!$payment || $payment->getUser() !== $user || $payment->getStatus() !== 'completed') {
            return $this->redirectToRoute('checkout_page');
        }

        /** @var PurchaseHistory[] $records */
        $records = $em->getRepository(PurchaseHistory::class)
                      ->findBy(['payment' => $payment]);

        // Deduct tickets: mark actual Ticket entities as sold
        foreach ($records as $rec) {
            $productName = $rec->getProductName();
            $qtyBought   = $rec->getQuantity();

            /** @var TicketType|null $tt */
            $tt = $em->getRepository(TicketType::class)
                     ->findOneBy(['name' => $productName]);

            if (!$tt) {
                continue;
            }

            // fetch unsold tickets of this type
            $unsold = $em->getRepository(Ticket::class)
                ->findBy(
                    ['ticketType' => $tt, 'payment' => null],
                    null,
                    $qtyBought
                );

            foreach ($unsold as $ticket) {
                $ticket->setPayment($payment);
                $em->persist($ticket);
            }
        }

        $em->flush();

        // prepare display data
        $bought   = [];
        $subtotal = 0;
        foreach ($records as $rec) {
            $line = $rec->getUnitPrice() * $rec->getQuantity();
            $bought[] = [
                'productName' => $rec->getProductName(),
                'quantity'    => $rec->getQuantity(),
                'unitPrice'   => $rec->getUnitPrice(),
                'line'        => $line,
            ];
            $subtotal += $line;
        }
        $total      = $payment->getTotalPrice();
        $bookingFee = max(0, $total - $subtotal);

        return $this->render('payment/success.html.twig', [
            'bought'     => $bought,
            'subtotal'   => number_format($subtotal, 2),
            'bookingFee' => number_format($bookingFee, 2),
            'total'      => number_format($total, 2),
        ]);
    }

    #[Route('/cancel', name: 'checkout_cancel')]
    public function cancel(Request $request, EntityManagerInterface $em): Response
    {
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login_form');
        }

        $cartItems = $em->getRepository(CartItem::class)
                       ->findBy(['user' => $user]);

        return $this->render('payment/cancel.html.twig', [
            'cartItems'          => $cartItems,
            'stripe_public_key'  => $_ENV['STRIPE_PUBLIC_KEY'],
        ]);
    }
}
