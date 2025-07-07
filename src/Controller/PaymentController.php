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



//   #[Route('/create-checkout-session', name: 'create_checkout_session', methods: ['POST'])]
//     public function createCheckoutSession(
//         Request $request,
//         EntityManagerInterface $em,
//         CartItemRepository $cartRepo,
//         UrlGeneratorInterface $urlGenerator
//     ): JsonResponse {
//         // 1) Auth
//         $user = $request->attributes->get('jwt_user');
//         if (!$user) {
//             return $this->json(['error' => 'User not authenticated.'], 403);
//         }

//         // 2) Check for existing, still-valid session
//         $now = new \DateTime();
//         $existing = $em->getRepository(Payment::class)
//             ->findOneBy(['user' => $user, 'status' => 'pending']);

//         if ($existing && $existing->getExpiresAt() > $now) {
//             return $this->json(['sessionId' => $existing->getSessionId()]);
//         }

//         // (Optional) If existing has expired, mark it cancelled & log:
//         if ($existing && $existing->getExpiresAt() <= $now) {
//             $existing->setStatus('cancelled');
//             $em->persist($existing);
//             $em->persist((new History())
//                 ->setUser($existing->getUser())
//                 ->setPayment($existing)
//                 ->setAction('Automatically expired (30m timeout)')
//                 ->setTimestamp(new \DateTime())
//                 ->setSessionId($existing->getSessionId())
//                 ->setStatus('cancelled')
//             );
//             $em->flush();
//         }

//         // 3) Build Line Items
//         $rawItems = $cartRepo->findBy(['user' => $user]);
//         if (empty($rawItems)) {
//             return $this->json(['error' => 'Cart is empty.'], 400);
//         }

//         $lineItems = [];
//         $subtotal   = 0;
//         foreach ($rawItems as $ci) {
//             $unitAmt = (int) round($ci->getPrice() * 100);
//             $qty     = $ci->getQuantity();
//             $subtotal += $ci->getPrice() * $qty;

//             $lineItems[] = [
//                 'price_data' => [
//                     'currency'     => 'usd',
//                     'product_data'=> ['name' => $ci->getName()],
//                     'unit_amount' => $unitAmt,
//                 ],
//                 'quantity'   => $qty,
//             ];
//         }

//         // 4) Add Booking Fee
//         $bookingFeeAmt = 3.00 * count($lineItems);
//         if ($bookingFeeAmt > 0) {
//             $lineItems[] = [
//                 'price_data' => [
//                     'currency'     => 'usd',
//                     'product_data'=> ['name' => 'Booking Fee'],
//                     'unit_amount' => (int) round($bookingFeeAmt * 100),
//                 ],
//                 'quantity'   => 1,
//             ];
//             $subtotal += $bookingFeeAmt;
//         }

//         // 5) Create Stripe Session with 30-minute expires_at
//         Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
//         $session = StripeSession::create([
//             'payment_method_types' => ['card'],
//             'line_items'           => $lineItems,
//             'mode'                 => 'payment',
//             'expires_at'           => time() + 1800,
//             'success_url'          => $urlGenerator
//                 ->generate('checkout_success', [], UrlGeneratorInterface::ABSOLUTE_URL)
//                 . '?session_id={CHECKOUT_SESSION_ID}',
//             'cancel_url'           => $urlGenerator
//                 ->generate('checkout_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL)
//                 . '?session_id={CHECKOUT_SESSION_ID}',
//         ]);

//         // 6) Persist new Payment + History
//         $payment = (new Payment())
//             ->setUser($user)
//             ->setTotalPrice($subtotal)
//             ->setPaymentMethod('stripe')
//             ->setSessionId($session->id)
//             ->setStatus('pending')
//             ->setPaymentDateTime(new \DateTime())
//             ->setExpiresAt((new \DateTime())->setTimestamp($session->expires_at));
//         $em->persist($payment);

//         $history = (new History())
//             ->setUser($user)
//             ->setPayment($payment)
//             ->setAction('Checkout session created')
//             ->setSessionId($session->id)
//             ->setStatus('pending')
//             ->setTimestamp(new \DateTime());
//         $em->persist($history);

//         $em->flush();

//         // 7) Return the session ID
//         return $this->json(['sessionId' => $session->id]);
//     }

#[Route('/create-checkout-session', name: 'create_checkout_session', methods: ['POST'])]
public function createCheckoutSession(
    Request $request,
    EntityManagerInterface $em,
    CartItemRepository $cartRepo,
    UrlGeneratorInterface $urlGenerator
): JsonResponse {
    // 1) Ensure user is authenticated
    $user = $request->attributes->get('jwt_user');
    if (!$user) {
        return $this->json(['error' => 'User not authenticated.'], 403);
    }

    // 2) See if there's already a pending, unexpired session for this user
    $now      = new \DateTime();
    $existing = $em->getRepository(Payment::class)
                   ->findOneBy(['user' => $user, 'status' => 'pending']);

    if ($existing) {
        if ($existing->getExpiresAt() > $now) {
            // still valid—reuse it
            return $this->json(['sessionId' => $existing->getSessionId()]);
        } else {
            // expired—mark cancelled and log it
            $existing->setStatus('cancelled');
            $em->persist($existing);
            $em->persist((new History())
                ->setUser($existing->getUser())
                ->setPayment($existing)
                ->setAction('Automatically expired (30m timeout)')
                ->setTimestamp(new \DateTime())
                ->setSessionId($existing->getSessionId())
                ->setStatus('cancelled')
            );
            // **no flush here**
        }
    }

    // 3) Build Stripe line items from the cart
    $rawItems = $cartRepo->findBy(['user' => $user]);
    if (empty($rawItems)) {
        return $this->json(['error' => 'Cart is empty.'], 400);
    }

    $lineItems = [];
    $subtotal   = 0;
    foreach ($rawItems as $ci) {
        $unitAmt   = (int) round($ci->getPrice() * 100);
        $qty       = $ci->getQuantity();
        $subtotal += $ci->getPrice() * $qty;

        $lineItems[] = [
            'price_data' => [
                'currency'     => 'usd',
                'product_data'=> ['name' => $ci->getName()],
                'unit_amount' => $unitAmt,
            ],
            'quantity'   => $qty,
        ];
    }

    // 4) Add a flat booking fee line
    $bookingFeeAmt = 3.00 * count($lineItems);
    if ($bookingFeeAmt > 0) {
        $lineItems[] = [
            'price_data' => [
                'currency'     => 'usd',
                'product_data'=> ['name' => 'Booking Fee'],
                'unit_amount' => (int) round($bookingFeeAmt * 100),
            ],
            'quantity'   => 1,
        ];
        $subtotal += $bookingFeeAmt;
    }

    // 5) Create a new Stripe Checkout Session
    Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
    $session = StripeSession::create([
        'payment_method_types' => ['card'],
        'line_items'           => $lineItems,
        'mode'                 => 'payment',
        'expires_at'           => time() + 1800, // 30 minutes
        'success_url'          => $urlGenerator
            ->generate('checkout_success', [], UrlGeneratorInterface::ABSOLUTE_URL)
            . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'           => $urlGenerator
            ->generate('checkout_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL)
            . '?session_id={CHECKOUT_SESSION_ID}',
    ]);

    // 6) Persist the new Payment record (with Stripe's expires_at) + History
    $payment = (new Payment())
        ->setUser($user)
        ->setTotalPrice($subtotal)
        ->setPaymentMethod('stripe')
        ->setSessionId($session->id)
        ->setStatus('pending')
        ->setPaymentDateTime(new \DateTime())
        ->setExpiresAt((new \DateTime())->setTimestamp($session->expires_at));
    $em->persist($payment);

    $history = (new History())
        ->setUser($user)
        ->setPayment($payment)
        ->setAction('Checkout session created')
        ->setSessionId($session->id)
        ->setStatus('pending')
        ->setTimestamp(new \DateTime());
    $em->persist($history);

    // **7) Single flush to commit both the cancellation (if any) and the new session**
    $em->flush();

    //Store sessionId for later guard checks
    $request->getSession()->set('last_stripe_session', $session->id);

    // 8) Return the session ID for the client to redirect
    return $this->json(['sessionId' => $session->id]);
}




    //     #[Route('/checkout_cancel', name: 'checkout_cancel')]
    // public function cancel(
    //     Request $request,
    //     EntityManagerInterface $em,
    //     CartItemRepository $cartRepo
    // ): Response {
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user) {
    //         return $this->redirectToRoute('auth_login_form');
    //     }

    //     // 1) If they arrived via ?session_id=…, guard on that
    //     if ($sessionId = $request->query->get('session_id')) {
    //         $payment = $em->getRepository(Payment::class)
    //                       ->findOneBy(['sessionId' => $sessionId]);

    //         if ($payment && $payment->getUser() === $user) {
    //             switch ($payment->getStatus()) {
    //                 case 'completed':
    //                     // Already paid → forward to success
    //                     return $this->redirectToRoute('checkout_success', [
    //                         'session_id' => $sessionId,
    //                     ]);

    //                 case 'cancelled':
    //                 case 'expired':
    //                     // Already cancelled/expired → back to cart with an error
    //                     $this->addFlash('error', 'This checkout session has already expired or been cancelled.');
    //                     return $this->redirectToRoute('checkout_page');

    //                 // if status === 'pending', fall through to show cancel page
    //             }
    //         }
    //     }

    //     // 2) No valid pending session → re-group the cart for display
    //     $rawItems = $cartRepo->findBy(['user' => $user]);
    //     $grouped  = [];
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
    //         'grouped'   => $grouped,
    //         'cartItems' => $rawItems,
    //     ]);
    // }
#[Route('/checkout_cancel', name: 'checkout_cancel')]
public function cancel(
    Request $request,
    EntityManagerInterface $em,
    CartItemRepository $cartRepo,
    PaymentRepository $payments
): Response {
    $user = $request->attributes->get('jwt_user');
    if (!$user) {
        return $this->redirectToRoute('auth_login_form');
    }

    $session = $request->getSession();

    // 1) If Stripe just redirected with ?session_id=…, seed it into PHP session & bounce to clean URL
    if ($sidParam = $request->query->get('session_id')) {
        $session->set('last_stripe_session', $sidParam);

        // Redirect to the same route without any query-string
        return $this->redirectToRoute('checkout_cancel');
    }

    // 2) Grab it back out of PHP session
    $sid = $session->get('last_stripe_session');

    // 3) Lookup & guard: must exist, be ours, still pending & unexpired
    $payment = $sid
        ? $payments->findOneBy(['sessionId' => $sid, 'user' => $user])
        : null;

    if (
        ! $payment ||
        $payment->getStatus() !== 'pending' ||
        $payment->getExpiresAt() < new \DateTime()
    ) {
        $this->addFlash('error', 'Your payment session has expired or is invalid.');
        return $this->redirectToRoute('checkout_page');
    }

    // 4) Still pending/unexpired → show the cancel page
    $rawItems = $cartRepo->findBy(['user' => $user]);
    $grouped  = [];

    foreach ($rawItems as $item) {
        $tid = $item->getTicketType()->getId();
        if (! isset($grouped[$tid])) {
            $grouped[$tid] = [
                'name'     => $item->getName(),
                'price'    => $item->getPrice(),
                'quantity' => 0,
            ];
        }
        $grouped[$tid]['quantity'] += $item->getQuantity();
    }

    return $this->render('payment/cancel.html.twig', [
        'grouped'   => $grouped,
        'cartItems' => $rawItems,
    ]);
}




//  #[Route('/checkout_success', name: 'checkout_success')]
//     public function success(
//         Request $request,
//         EntityManagerInterface $em,
//         PaymentRepository $payments
//     ): Response {
//         // 1) Auth & lookup by session_id
//         $user      = $request->attributes->get('jwt_user');
//         $sessionId = $request->query->get('session_id');
//         if (!$user || !$sessionId) {
//             return $this->redirectToRoute('checkout_page');
//         }

//         $payment = $payments->findOneBy(['sessionId' => $sessionId]);
//         if (!$payment || $payment->getUser() !== $user || $payment->getStatus() !== 'completed') {
//             return $this->redirectToRoute('checkout_page');
//         }

//         // 2) Load & group purchase history
//         $records = $em->getRepository(PurchaseHistory::class)
//                       ->findBy(['payment' => $payment]);

//         $grouped  = [];
//         $subtotal = 0;
//         foreach ($records as $rec) {
//             $name = $rec->getProductName();
//             if (!isset($grouped[$name])) {
//                 $grouped[$name] = [
//                     'productName' => $name,
//                     'unitPrice'   => $rec->getUnitPrice(),
//                     'quantity'    => 0,
//                 ];
//             }
//             $grouped[$name]['quantity'] += $rec->getQuantity();
//         }

//         $bought = [];
//         foreach ($grouped as $info) {
//             $line     = $info['unitPrice'] * $info['quantity'];
//             $subtotal += $line;
//             $bought[] = [
//                 'productName' => $info['productName'],
//                 'unitPrice'   => $info['unitPrice'],
//                 'quantity'    => $info['quantity'],
//                 'line'        => $line,
//             ];
//         }

//         $total      = $payment->getTotalPrice();
//         $bookingFee = max(0, $total - $subtotal);

//         return $this->render('payment/success.html.twig', [
//             'bought'     => $bought,
//             'subtotal'   => number_format($subtotal, 2),
//             'bookingFee' => number_format($bookingFee, 2),
//             'total'      => number_format($total, 2),
//         ]);
//     }

#[Route('/checkout_success', name: 'checkout_success')]
public function success(
    Request $request,
    EntityManagerInterface $em,
    PaymentRepository $payments
): Response {
    $user = $request->attributes->get('jwt_user');
    if (!$user) {
        return $this->redirectToRoute('auth_login_form');
    }

    // 1) If Stripe just redirected with ?session_id=…, stash and clean URL
    if ($sidParam = $request->query->get('session_id')) {
        $request->getSession()->set('last_stripe_session', $sidParam);
        return $this->redirectToRoute('checkout_success');
    }

    // 2) Grab the session ID out of PHP session
    $sid = $request->getSession()->get('last_stripe_session');

    // 3) Lookup & guard: must exist, belong to this user, be completed, and not expired
    $payment = $sid
        ? $payments->findOneBy(['sessionId' => $sid, 'user' => $user])
        : null;

    if (
        ! $payment ||
        $payment->getStatus() !== 'completed' ||
        $payment->getExpiresAt() < new \DateTime()
    ) {
        $this->addFlash('error', 'Your payment session has expired or is invalid.');
        return $this->redirectToRoute('checkout_page');
    }

    // 4) Load & group purchase history
    $records  = $em->getRepository(PurchaseHistory::class)
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

    // 5) Render success page
    return $this->render('payment/success.html.twig', [
        'bought'     => $bought,
        'subtotal'   => number_format($subtotal, 2),
        'bookingFee' => number_format($bookingFee, 2),
        'total'      => number_format($total, 2),
    ]);
}

}
