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
use Symfony\Component\HttpFoundation\RedirectResponse;

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

    //    #[Route('/checkout', name: 'checkout_page')]
    // public function checkout(
    //     Request $request,
    //     CartItemRepository $cartRepo,
    //     EntityManagerInterface $em,
    //     string $stripe_public_key
    // ): Response {
    //     // 1) Ensure user is logged in
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user) {
    //         return $this->redirectToRoute('auth_login_form');
    //     }

    //     // 2) Load all CartItems for this user
    //     /** @var CartItem[] $items */
    //     $items = $cartRepo->findBy(['user' => $user]);

    //     // 3) Build an availability map per CartItem
    //     $availability = [];
    //     foreach ($items as $item) {
    //         // find the matching TicketType entity
    //         $tt = $em->getRepository(TicketType::class)
    //                  ->findOneBy(['name' => $item->getName()]);

    //         // count all tickets of that type WITHOUT a payment assigned
    //         $availability[$item->getId()] = $tt
    //             ? $em->getRepository(Ticket::class)->count([
    //                 'ticketType' => $tt,
    //                 'payment'    => null,
    //             ])
    //             : 0;
    //     }

    //     // 4) Render the Twig template exactly as it expects:
    //     return $this->render('payment/checkout.html.twig', [
    //         'items'             => $items,
    //         'availability'      => $availability,
    //         'stripe_public_key' => $stripe_public_key,
    //     ]);
    // }


        #[Route('/checkout', name: 'checkout_page')]
    public function checkout(
        Request $request,
        CartItemRepository $cartRepo,
        EntityManagerInterface $em,
        string $stripe_public_key
    ): Response {
        // 1) Ensure user is logged in
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            return $this->redirectToRoute('auth_login_form');
        }

        // 2) Fetch all CartItem entities for this user
        /** @var CartItem[] $rawItems */
        $rawItems = $cartRepo->findBy(['user' => $user]);

        // 3) Group items by ticketType ID and sum quantities
        $grouped = [];
        foreach ($rawItems as $item) {
            $tt   = $item->getTicketType();
            $tid  = $tt->getId();
            if (!isset($grouped[$tid])) {
                $grouped[$tid] = [
                    'ticketType' => $tt,
                    'name'       => $item->getName(),
                    'price'      => $item->getPrice(),
                    'quantity'   => 0,
                ];
            }
            $grouped[$tid]['quantity'] += $item->getQuantity();
        }

        // 4) Compute availability per ticket type
        $availability = [];
        foreach ($grouped as $tid => $info) {
            /** @var \App\Entity\TicketType $tt */
            $tt = $info['ticketType'];

            $availability[$tid] = $em
                ->getRepository(Ticket::class)
                ->count([
                    'ticketType' => $tt,
                    'payment'    => null,
                ]);
        }

        // 5) Prepare flat list for Twig
        $items = [];
        foreach ($grouped as $tid => $info) {
            $qty  = $info['quantity'];
            $line = $info['price'] * $qty;

            $items[] = [
                'id'       => $tid,
                'name'     => $info['name'],
                'price'    => $info['price'],
                'quantity' => $qty,
                'avail'    => $availability[$tid] ?? 0,
                'line'     => $line,
            ];
        }

        // 6) Compute totals
        $subtotal   = array_sum(array_column($items, 'line'));
        $bookingFee = 3 * count($items);
        $total      = $subtotal + $bookingFee;

        // 7) Render the grouped-cart template
        return $this->render('payment/checkout.html.twig', [
            'items'             => $items,
            'subtotal'          => $subtotal,
            'booking_fee'       => $bookingFee,
            'total'             => $total,
            'stripe_public_key' => $stripe_public_key,
        ]);
    }


    // #[Route('/create-checkout-session', name: 'create_checkout_session', methods: ['POST'])]
    // public function createCheckoutSession(
    //     Request $request,
    //     EntityManagerInterface $em
    // ): JsonResponse {
    //     // configure Stripe
    //     Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

    //     // AUTH
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user) {
    //         return $this->json(['error' => 'User not authenticated.'], 403);
    //     }

    //     // LOAD CART
    //     /** @var CartItem[] $cartItems */
    //     $cartItems = $em->getRepository(CartItem::class)
    //                     ->findBy(['user' => $user]);
    //     if (empty($cartItems)) {
    //         return $this->json(['error' => 'Cart is empty.'], 400);
    //     }

    //     // BUILD LINE ITEMS
    //     $lineItems = [];
    //     foreach ($cartItems as $item) {
    //         $lineItems[] = [
    //             'price_data' => [
    //                 'currency'     => 'usd',
    //                 'product_data' => ['name' => $item->getName()],
    //                 'unit_amount'  => (int) round($item->getPrice() * 100),
    //             ],
    //             'quantity' => $item->getQuantity(),
    //         ];
    //     }
    //     $bookingFee = 3.00 * count($cartItems);
    //     if ($bookingFee > 0) {
    //         $lineItems[] = [
    //             'price_data' => [
    //                 'currency'     => 'usd',
    //                 'product_data' => ['name' => 'Booking Fee'],
    //                 'unit_amount'  => (int) round($bookingFee * 100),
    //             ],
    //             'quantity' => 1,
    //         ];
    //     }

    //     // CREATE STRIPE SESSION
    //     $session = StripeSession::create([
    //         'payment_method_types' => ['card'],
    //         'line_items'           => $lineItems,
    //         'mode'                 => 'payment',
    //         'success_url'          => $this->generateUrl(
    //                                      'checkout_success',
    //                                      [],
    //                                      UrlGeneratorInterface::ABSOLUTE_URL
    //                                  ) . '?session_id={CHECKOUT_SESSION_ID}',
    //         'cancel_url'           => $this->generateUrl(
    //                                      'checkout_cancel',
    //                                      [],
    //                                      UrlGeneratorInterface::ABSOLUTE_URL
    //                                  ),
    //     ]);

    //     // COMPUTE TOTAL PRICE
    //     $subtotal = array_reduce(
    //         $cartItems,
    //         fn($sum, CartItem $i) => $sum + ($i->getPrice() * $i->getQuantity()),
    //         0
    //     );
    //     $total = $subtotal + $bookingFee;

    //     // PERSIST Payment with session ID
    //     $payment = (new Payment())
    //         ->setUser($user)
    //         ->setTotalPrice($total)
    //         ->setPaymentMethod('stripe')
    //         ->setPaymentDateTime(new \DateTime())
    //         ->setSessionId($session->id)
    //         ->setStatus('pending');

    //     $em->persist($payment);
    //     $em->flush();

    //     // PERSIST History with the same session ID
    //     $history = (new History())
    //         ->setUser($user)
    //         ->setPayment($payment)
    //         ->setAction('Checkout session created')
    //         ->setTimestamp(new \DateTime())
    //         ->setSessionId($session->id)
    //         ->setStatus('pending');

    //     $em->persist($history);
    //     $em->flush();

    //     // STORE for /success
    //     $request->getSession()->set('last_payment_id', $payment->getId());

    //     return $this->json(['id' => $session->id]);
    // }

    //    #[Route('/create-checkout-session', name: 'create_checkout_session', methods: ['POST'])]
    // public function createCheckoutSession(
    //     Request $request,
    //     EntityManagerInterface $em,
    //     CartItemRepository $cartRepo
    // ): JsonResponse {
    //     // 1) Configure Stripe
    //     Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

    //     // 2) Ensure user is authenticated
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user) {
    //         return $this->json(['error' => 'User not authenticated.'], 403);
    //     }

    //     // 3) Load raw CartItems
    //     /** @var CartItem[] $rawItems */
    //     $rawItems = $cartRepo->findBy(['user' => $user]);
    //     if (empty($rawItems)) {
    //         return $this->json(['error' => 'Cart is empty.'], 400);
    //     }

    //     // 4) Group by ticketType and sum quantities
    //     $grouped = [];
    //     foreach ($rawItems as $item) {
    //         $tt  = $item->getTicketType();
    //         $tid = $tt->getId();
    //         if (!isset($grouped[$tid])) {
    //             $grouped[$tid] = [
    //                 'name'     => $item->getName(),
    //                 'price'    => $item->getPrice(),
    //                 'quantity' => 0,
    //             ];
    //         }
    //         $grouped[$tid]['quantity'] += $item->getQuantity();
    //     }

    //     // 5) Build Stripe line_items from grouped data
    //     $lineItems  = [];
    //     $subtotal   = 0;
    //     foreach ($grouped as $info) {
    //         $qty       = $info['quantity'];
    //         $unitAmt   = (int) round($info['price'] * 100);
    //         $lineTotal = $info['price'] * $qty;
    //         $subtotal += $lineTotal;

    //         $lineItems[] = [
    //             'price_data' => [
    //                 'currency'     => 'usd',
    //                 'product_data'=> ['name' => $info['name']],
    //                 'unit_amount' => $unitAmt,
    //             ],
    //             'quantity'   => $qty,
    //         ];
    //     }

    //     // 6) Add booking fee as single line
    //     $bookingFee = 3.00 * count($grouped);
    //     if ($bookingFee > 0) {
    //         $lineItems[] = [
    //             'price_data' => [
    //                 'currency'     => 'usd',
    //                 'product_data'=> ['name' => 'Booking Fee'],
    //                 'unit_amount' => (int) round($bookingFee * 100),
    //             ],
    //             'quantity'   => 1,
    //         ];
    //     }

    //     // 7) Create Stripe session
    //     $session = StripeSession::create([
    //         'payment_method_types' => ['card'],
    //         'line_items'           => $lineItems,
    //         'mode'                 => 'payment',
    //         'success_url'          => $this->generateUrl(
    //                                       'checkout_success',
    //                                       [],
    //                                       UrlGeneratorInterface::ABSOLUTE_URL
    //                                   ) . '?session_id={CHECKOUT_SESSION_ID}',
    //         'cancel_url'           => $this->generateUrl(
    //                                       'checkout_cancel',
    //                                       [],
    //                                       UrlGeneratorInterface::ABSOLUTE_URL
    //                                   ),
    //     ]);

    //     // 8) Persist Payment & History (unchanged)
    //     $total = $subtotal + $bookingFee;
    //     $payment = (new Payment())
    //         ->setUser($user)
    //         ->setTotalPrice($total)
    //         ->setPaymentMethod('stripe')
    //         ->setPaymentDateTime(new \DateTime())
    //         ->setSessionId($session->id)
    //         ->setStatus('pending');
    //     $em->persist($payment);
    //     $em->flush();

    //     $history = (new History())
    //         ->setUser($user)
    //         ->setPayment($payment)
    //         ->setAction('Checkout session created')
    //         ->setTimestamp(new \DateTime())
    //         ->setSessionId($session->id)
    //         ->setStatus('pending');
    //     $em->persist($history);
    //     $em->flush();

    //     // 9) Store last payment and return session ID
    //     $request->getSession()->set('last_payment_id', $payment->getId());
    //     return $this->json(['id' => $session->id]);
    // }

  #[Route('/create-checkout-session', name: 'create_checkout_session', methods: ['POST'])]
    public function createCheckoutSession(
        Request $request,
        EntityManagerInterface $em,
        CartItemRepository $cartRepo,
        UrlGeneratorInterface $urlGenerator
    ): JsonResponse {
        // 1) Configure Stripe
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        // 2) Ensure user is authenticated
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            return $this->json(['error' => 'User not authenticated.'], 403);
        }

        // 3) Load & group cart items
        $rawItems = $cartRepo->findBy(['user' => $user]);
        if (empty($rawItems)) {
            return $this->json(['error' => 'Cart is empty.'], 400);
        }

        $grouped  = [];
        $subtotal = 0;
        foreach ($rawItems as $item) {
            $tid      = $item->getTicketType()->getId();
            $price    = $item->getPrice();
            $quantity = $item->getQuantity();

            if (!isset($grouped[$tid])) {
                $grouped[$tid] = [
                    'name'     => $item->getName(),
                    'price'    => $price,
                    'quantity' => 0,
                ];
            }
            $grouped[$tid]['quantity'] += $quantity;
        }

        $lineItems = [];
        foreach ($grouped as $info) {
            $unitAmt   = (int) round($info['price'] * 100);
            $lineTotal = $info['price'] * $info['quantity'];
            $subtotal += $lineTotal;

            $lineItems[] = [
                'price_data' => [
                    'currency'     => 'usd',
                    'product_data'=> ['name' => $info['name']],
                    'unit_amount' => $unitAmt,
                ],
                'quantity'   => $info['quantity'],
            ];
        }

        // 4) Add booking fee
        $bookingFee = 3.00 * count($grouped);
        if ($bookingFee > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency'     => 'usd',
                    'product_data'=> ['name' => 'Booking Fee'],
                    'unit_amount' => (int) round($bookingFee * 100),
                ],
                'quantity'   => 1,
            ];
        }

        // 5) Create Stripe session with 30-minute minimum expiry
        try {
            $session = StripeSession::create([
                'payment_method_types' => ['card'],
                'line_items'           => $lineItems,
                'mode'                 => 'payment',
                'expires_at'           => time() + 1800, // 30 minutes
                'success_url'          => $urlGenerator->generate(
                                              'checkout_success',
                                              [],
                                              UrlGeneratorInterface::ABSOLUTE_URL
                                          ) . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'           => $urlGenerator->generate(
                                              'checkout_cancel',
                                              [],
                                              UrlGeneratorInterface::ABSOLUTE_URL
                                          ) . '?session_id={CHECKOUT_SESSION_ID}',
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        // 6) Persist Payment + History
        $total   = $subtotal + $bookingFee;
        $payment = (new Payment())
            ->setUser($user)
            ->setTotalPrice($total)
            ->setPaymentMethod('stripe')
            ->setSessionId($session->id)
            ->setStatus('pending')
            ->setPaymentDateTime(new \DateTime())
        ;
        $em->persist($payment);

        $hist = (new History())
            ->setUser($user)
            ->setPayment($payment)
            ->setAction('Checkout session created')
            ->setSessionId($session->id)
            ->setStatus('pending')
            ->setTimestamp(new \DateTime())
        ;
        $em->persist($hist);
        $em->flush();

        // 7) Return the sessionId for the client-side redirect
        return $this->json(['sessionId' => $session->id]);
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


    // #[Route('/success', name: 'checkout_success')]
    // public function success(
    //     Request $request,
    //     EntityManagerInterface $em,
    //     PaymentRepository $payments
    // ): Response {
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user) {
    //         return $this->redirectToRoute('auth_login_form');
    //     }
    //     $paymentId = $request->getSession()->get('last_payment_id');
    //     if (!$paymentId) {
    //         return $this->redirectToRoute('checkout_page');
    //     }
    //     $payment = $payments->find($paymentId);
    //     if (!$payment || $payment->getUser() !== $user || $payment->getStatus() !== 'completed') {
    //         return $this->redirectToRoute('checkout_page');
    //     }

    //     /** @var PurchaseHistory[] $records */
    //     $records = $em->getRepository(PurchaseHistory::class)
    //                   ->findBy(['payment' => $payment]);

    //     // Deduct tickets: mark actual Ticket entities as sold
    //     foreach ($records as $rec) {
    //         $productName = $rec->getProductName();
    //         $qtyBought   = $rec->getQuantity();

    //         /** @var TicketType|null $tt */
    //         $tt = $em->getRepository(TicketType::class)
    //                  ->findOneBy(['name' => $productName]);

    //         if (!$tt) {
    //             continue;
    //         }

    //         // fetch unsold tickets of this type
    //         $unsold = $em->getRepository(Ticket::class)
    //             ->findBy(
    //                 ['ticketType' => $tt, 'payment' => null],
    //                 null,
    //                 $qtyBought
    //             );

    //         foreach ($unsold as $ticket) {
    //             $ticket->setPayment($payment);
    //             $em->persist($ticket);
    //         }
    //     }

    //     $em->flush();

    //     // prepare display data
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
    //     $total      = $payment->getTotalPrice();
    //     $bookingFee = max(0, $total - $subtotal);

    //     return $this->render('payment/success.html.twig', [
    //         'bought'     => $bought,
    //         'subtotal'   => number_format($subtotal, 2),
    //         'bookingFee' => number_format($bookingFee, 2),
    //         'total'      => number_format($total, 2),
    //     ]);
    // }

    // #[Route('/cancel', name: 'checkout_cancel')]
    // public function cancel(Request $request, EntityManagerInterface $em): Response
    // {
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user) {
    //         return $this->redirectToRoute('auth_login_form');
    //     }

    //     $cartItems = $em->getRepository(CartItem::class)
    //                    ->findBy(['user' => $user]);

    //     return $this->render('payment/cancel.html.twig', [
    //         'cartItems'          => $cartItems,
    //         'stripe_public_key'  => $_ENV['STRIPE_PUBLIC_KEY'],
    //     ]);
    // }

    //   #[Route('/checkout_cancel', name: 'checkout_cancel')]
    // public function cancel(
    //     Request $request,
    //     EntityManagerInterface $em,
    //     CartItemRepository $cartRepo
    // ): Response {
    //     // 1) Auth
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user) {
    //         return $this->redirectToRoute('auth_login_form');
    //     }

    //     // 2) If we have a session_id, mark that payment cancelled
    //     if ($sessionId = $request->query->get('session_id')) {
    //         $payment = $em->getRepository(Payment::class)
    //                       ->findOneBy(['sessionId' => $sessionId]);
    //         if ($payment && $payment->getStatus() === 'pending') {
    //             $payment->setStatus('cancelled');
    //             // $em->flush();
    //                $history = (new History())
    //             ->setUser($user)
    //             ->setPayment($payment)
    //             ->setAction('Checkout session cancelled by user')
    //             ->setSessionId($sessionId)
    //             ->setTimestamp(new \DateTime())
    //             ->setStatus('cancelled')
    //         ;
    //         $em->persist($history);
    //         $em->flush();
    //         }
    //     }

    //     // 3) Re-group the cart for display
    //     $rawItems = $cartRepo->findBy(['user' => $user]);
    //     $grouped   = [];
    //     foreach ($rawItems as $item) {
    //         $tid = $item->getTicketType()->getId();
    //         if (!isset($grouped[$tid])) {
    //             $grouped[$tid] = [
    //                 'name'     => $item->getName(),
    //                 'price'    => $item->getPrice(),
    //                 'quantity' => 0,
    //             ];
    //         }
    //         $grouped[$tid]['quantity'] += $item->getQuantity();
    //     }

    //     return $this->render('payment/cancel.html.twig', [
    //         'grouped' => $grouped,
    //         'cartItems' => $rawItems,      // <— add this

    //     ]);
    // }
    #[Route('/checkout_cancel', name: 'checkout_cancel')]
public function cancel(
    Request $request,
    EntityManagerInterface $em,
    CartItemRepository $cartRepo
): Response {
    $user = $request->attributes->get('jwt_user');
    if (!$user) {
        return $this->redirectToRoute('auth_login_form');
    }

    if ($sessionId = $request->query->get('session_id')) {
        /** @var Payment|null $payment */
        $payment = $em->getRepository(Payment::class)
                      ->findOneBy(['sessionId' => $sessionId]);

        if ($payment && $payment->getStatus() === 'pending') {
            // 1) mark payment cancelled
            $payment->setStatus('cancelled');
            $em->persist($payment);

            // 2) log a matching History row
            $hist = new History();
            $hist
              ->setUser($user)
              ->setPayment($payment)
              ->setAction('Checkout session cancelled by user')
              ->setSessionId($sessionId)
              ->setTimestamp(new \DateTime())
              ->setStatus('cancelled')
            ;
            $em->persist($hist);

            $em->flush();
        }
    }

    // …then re-group cartItems for display…
    $rawItems = $cartRepo->findBy(['user' => $user]);

    return $this->render('payment/cancel.html.twig', [
        'cartItems' => $rawItems,
    ]);
}

//     #[Route('/success', name: 'checkout_success')]
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

//     // — GROUP by product name and sum quantity & compute line totals —
//     $grouped = [];
//     foreach ($records as $rec) {
//         $name  = $rec->getProductName();
//         $unit  = $rec->getUnitPrice();
//         $qty   = $rec->getQuantity();
//         if (!isset($grouped[$name])) {
//             $grouped[$name] = [
//                 'productName' => $name,
//                 'unitPrice'   => $unit,
//                 'quantity'    => 0,
//             ];
//         }
//         $grouped[$name]['quantity'] += $qty;
//     }

//     // — BUILD final ‘bought’ array & compute subtotal —
//     $bought   = [];
//     $subtotal = 0;
//     foreach ($grouped as $info) {
//         $line = $info['unitPrice'] * $info['quantity'];
//         $bought[] = [
//             'productName' => $info['productName'],
//             'quantity'    => $info['quantity'],
//             'unitPrice'   => $info['unitPrice'],
//             'line'        => $line,
//         ];
//         $subtotal += $line;
//     }

//     // — DERIVE BOOKING FEE & TOTAL —
//     $total      = $payment->getTotalPrice();
//     $bookingFee = max(0, $total - $subtotal);

//     return $this->render('payment/success.html.twig', [
//         'bought'     => $bought,
//         'subtotal'   => number_format($subtotal, 2),
//         'bookingFee' => number_format($bookingFee, 2),
//         'total'      => number_format($total, 2),
//     ]);
// }


 #[Route('/checkout_success', name: 'checkout_success')]
    public function success(
        Request $request,
        EntityManagerInterface $em,
        PaymentRepository $payments
    ): Response {
        // 1) Auth & lookup by session_id
        $user      = $request->attributes->get('jwt_user');
        $sessionId = $request->query->get('session_id');
        if (!$user || !$sessionId) {
            return $this->redirectToRoute('checkout_page');
        }

        $payment = $payments->findOneBy(['sessionId' => $sessionId]);
        if (!$payment || $payment->getUser() !== $user || $payment->getStatus() !== 'completed') {
            return $this->redirectToRoute('checkout_page');
        }

        // 2) Load & group purchase history
        $records = $em->getRepository(PurchaseHistory::class)
                      ->findBy(['payment' => $payment]);

        $grouped  = [];
        $subtotal = 0;
        foreach ($records as $rec) {
            $name = $rec->getProductName();
            if (!isset($grouped[$name])) {
                $grouped[$name] = [
                    'productName' => $name,
                    'unitPrice'   => $rec->getUnitPrice(),
                    'quantity'    => 0,
                ];
            }
            $grouped[$name]['quantity'] += $rec->getQuantity();
        }

        $bought = [];
        foreach ($grouped as $info) {
            $line     = $info['unitPrice'] * $info['quantity'];
            $subtotal += $line;
            $bought[] = [
                'productName' => $info['productName'],
                'unitPrice'   => $info['unitPrice'],
                'quantity'    => $info['quantity'],
                'line'        => $line,
            ];
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

}
