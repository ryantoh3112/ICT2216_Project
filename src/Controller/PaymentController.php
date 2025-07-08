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
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Psr\Log\LoggerInterface;

final class PaymentController extends AbstractController
{
 

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


//check out button function to create a Stripe session
#[Route('/create-checkout-session', name: 'create_checkout_session', methods: ['POST'])]
public function createCheckoutSession(
    Request $request,
    EntityManagerInterface $em,
    CartItemRepository $cartRepo,
    UrlGeneratorInterface $urlGenerator,
    RateLimiterFactory $createCheckoutSessionLimiter,
    LoggerInterface $logger
): JsonResponse|Response {
    // 1) Ensure user is authenticated
    /** @var \App\Entity\User|null $user */
    $user = $request->attributes->get('jwt_user');
    if (!$user) {
        return $this->json(['error' => 'User not authenticated.'], 403);
    }

    $csrf = $request->headers->get('X-CSRF-Token', '');
    if (! $this->isCsrfTokenValid('checkout', $csrf)) {
        throw $this->createAccessDeniedException('Invalid CSRF token');
    }

    // 2) RATE LIMIT: max 5 sessions per minute PER USER
    $limiter = $createCheckoutSessionLimiter->create('user_' . $user->getId());
    $limit   = $limiter->consume(1);
    if (false === $limit->isAccepted()) {
        return $this->json([
            'error' => 'Too many checkout attempts. Please wait before retrying.'
        ], Response::HTTP_TOO_MANY_REQUESTS);
    }

    // 3) Build Stripe line items from the current cart
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
                'currency'    => 'usd',
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
                'product_data' => ['name' => 'Booking Fee'],
                'unit_amount'  => (int) round($bookingFeeAmt * 100),
            ],
            'quantity'   => 1,
        ];
        $subtotal += $bookingFeeAmt;
    }

    // 5) Try creating the Stripe session
    try {
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
        $session = StripeSession::create([
            'payment_method_types' => ['card'],
            'line_items'           => $lineItems,
            'mode'                 => 'payment',
            'expires_at'           => time() + 1800,
            'success_url'          => $urlGenerator
                ->generate('checkout_success', [], UrlGeneratorInterface::ABSOLUTE_URL)
                . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'           => $urlGenerator
                ->generate('checkout_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL)
                . '?session_id={CHECKOUT_SESSION_ID}',
        ]);
    } catch (ApiErrorException $e) {
        // Log the error via injected logger
        $logger->error('Stripe API error creating session', [
            'message' => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
        ]);

        // Fallback: redirect back to cart with a flash error
        $this->addFlash('error', 'Unable to start payment. Please try again later.');
        return $this->redirectToRoute('checkout_page');
    }

    // 6) Persist the new Payment record + History
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

    $em->flush();

    // 7) Return the newly created session ID as JSON
    return $this->json(['sessionId' => $session->id]);
}

  
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
