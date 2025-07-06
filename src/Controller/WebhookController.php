<?php
// src/Controller/WebhookController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;            
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\PaymentRepository;
use App\Entity\History;
use Stripe\Webhook;
use App\Entity\CartItem;    
use App\Entity\PurchaseHistory;
use App\Entity\TicketType;
use App\Entity\Ticket;

class WebhookController extends AbstractController
{
    // #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    // public function __invoke(
    //     Request $request,
    //     PaymentRepository $payments,
    //     EntityManagerInterface $em
    // ): Response {
    //     $payload   = $request->getContent();
    //     $sigHeader = $request->headers->get('stripe-signature');
    //     $secret    = $_ENV['STRIPE_WEBHOOK_SECRET'];

    //     try {
    //         $event = Webhook::constructEvent($payload, $sigHeader, $secret);
    //     } catch (\Exception $e) {
    //         return new Response('Invalid payload or signature', 400);
    //     }

    //     if ($event->type === 'checkout.session.completed') {
    //         $session = $event->data->object; // \Stripe\Checkout\Session

    //         // 1) Find & mark Payment completed
    //         $payment = $payments->findOneBy(['sessionId' => $session->id]);
    //         if (!$payment) {
    //             return new Response('No matching payment', 404);
    //         }

    //         $payment
    //             ->setStatus('completed')
    //             ->setPaymentDateTime((new \DateTime())->setTimestamp($session->created));
    //         $em->flush();

    //         // 2) Log a normal History entry
    //         $hist = (new History())
    //             ->setUser($payment->getUser())
    //             ->setPayment($payment)
    //             ->setAction('Payment completed via webhook')
    //             ->setTimestamp(new \DateTime())
    //             ->setSessionId($session->id)
    //             ->setStatus('completed');
    //         $em->persist($hist);

    //         // 3) Record each CartItem into PurchaseHistory
    //         $cartItems = $em->getRepository(CartItem::class)
    //                         ->findBy(['user' => $payment->getUser()]);
    //         foreach ($cartItems as $ci) {
    //             $ph = (new PurchaseHistory())
    //                 ->setUser($payment->getUser())
    //                 ->setPayment($payment)
    //                 ->setProductName($ci->getName())
    //                 ->setUnitPrice((string)$ci->getPrice())
    //                 ->setQuantity($ci->getQuantity());
    //             $em->persist($ph);
    //             // then remove the cart‐item
    //             $em->remove($ci);
    //         }

    //         $em->flush();
    //     }

    //     return new Response('OK', 200);
    // }

    //     #[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    // public function __invoke(
    //     Request $request,
    //     PaymentRepository $payments,
    //     EntityManagerInterface $em
    // ): Response {
    //     $payload   = $request->getContent();
    //     $sigHeader = $request->headers->get('stripe-signature');
    //     $secret    = $_ENV['STRIPE_WEBHOOK_SECRET'];

    //     try {
    //         $event = Webhook::constructEvent($payload, $sigHeader, $secret);
    //     } catch (\Exception $e) {
    //         return new Response('Invalid payload or signature', 400);
    //     }

    //     switch ($event->type) {
    //         case 'checkout.session.completed':
    //             $session = $event->data->object;
    //             $payment = $payments->findOneBy(['sessionId' => $session->id]);
    //             if ($payment) {
    //                 $payment
    //                     ->setStatus('completed')
    //                     ->setPaymentDateTime((new \DateTime())->setTimestamp($session->created));
    //                 $em->persist($payment);

    //                 $hist = new History();
    //                 $hist
    //                   ->setUser($payment->getUser())
    //                   ->setPayment($payment)
    //                   ->setAction('Payment completed via webhook')
    //                   ->setTimestamp(new \DateTime())
    //                   ->setSessionId($session->id)
    //                   ->setStatus('completed');
    //                 $em->persist($hist);

    //                 // record PurchaseHistory & clear cart…
    //                 $cartItems = $em->getRepository(CartItem::class)
    //                                 ->findBy(['user' => $payment->getUser()]);
    //                 foreach ($cartItems as $ci) {
    //                     $ph = new PurchaseHistory();
    //                     $ph
    //                       ->setUser($payment->getUser())
    //                       ->setPayment($payment)
    //                       ->setProductName($ci->getName())
    //                       ->setUnitPrice((string)$ci->getPrice())
    //                       ->setQuantity($ci->getQuantity());
    //                     $em->persist($ph);
    //                     $em->remove($ci);
    //                 }
    //             }
    //             break;

    //         case 'checkout.session.expired':
    //             $session = $event->data->object;
    //             $payment = $payments->findOneBy(['sessionId' => $session->id]);
    //             if ($payment && $payment->getStatus() === 'pending') {
    //                 $payment->setStatus('cancelled');
    //                 $em->persist($payment);

    //                 $hist = new History();
    //                 $hist
    //                   ->setUser($payment->getUser())
    //                   ->setPayment($payment)
    //                   ->setAction('Session expired / cancelled')
    //                   ->setTimestamp(new \DateTime())
    //                   ->setSessionId($session->id)
    //                   ->setStatus('cancelled');
    //                 $em->persist($hist);
    //             }
    //             break;

    //         // you can also handle `checkout.session.async_payment_failed` if you like…

    //         default:
    //             // ignore other events
    //     }

    //     $em->flush();

    //     return new Response('OK', 200);
    // }

#[Route('/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
public function __invoke(
    Request $request,
    PaymentRepository $payments,
    EntityManagerInterface $em
): Response {
    $payload   = $request->getContent();
    $sigHeader = $request->headers->get('stripe-signature');
    $secret    = $_ENV['STRIPE_WEBHOOK_SECRET'];

    try {
        $event = Webhook::constructEvent($payload, $sigHeader, $secret);
    } catch (\Exception $e) {
        return new Response('Invalid payload or signature', 400);
    }

    if ($event->type !== 'checkout.session.completed') {
        // we only care about completions here
        return new Response('Ignored', 200);
    }

    $session = $event->data->object;
    $payment = $payments->findOneBy(['sessionId' => $session->id]);

    if (! $payment) {
        return new Response('No matching payment', 404);
    }

    // 1) Mark payment completed
    $payment
        ->setStatus('completed')
        ->setPaymentDateTime((new \DateTime())->setTimestamp($session->created));
    $em->persist($payment);

    // 2) Log History
    $hist = new History();
    $hist
      ->setUser($payment->getUser())
      ->setPayment($payment)
      ->setAction('Payment completed via webhook')
      ->setTimestamp(new \DateTime())
      ->setSessionId($session->id)
      ->setStatus('completed');
    $em->persist($hist);

    // 3) Fetch & clear cart, record PurchaseHistory
    //    We'll also keep a local map of ticketType → quantity
    $cartItems = $em->getRepository(CartItem::class)
                    ->findBy(['user' => $payment->getUser()]);

    $qtyByType = [];
    foreach ($cartItems as $ci) {
        // accumulate how many of each TicketType were bought
        $ttId = $ci->getTicketType()->getId();
        $qtyByType[$ttId] = ($qtyByType[$ttId] ?? 0) + $ci->getQuantity();

        // record purchase history
        $ph = new PurchaseHistory();
        $ph
          ->setUser($payment->getUser())
          ->setPayment($payment)
          ->setProductName($ci->getName())
          ->setUnitPrice((string)$ci->getPrice())
          ->setQuantity($ci->getQuantity());
        $em->persist($ph);

        // remove cart item
        $em->remove($ci);
    }

    // Flush **now** so that PurchaseHistory is in the DB and CartItems are cleared
    $em->flush();

    // 4) For each ticketType, assign that many unsold Ticket rows to this payment
    foreach ($qtyByType as $ttId => $count) {
        /** @var TicketType|null $tt */
        $tt = $em->getRepository(TicketType::class)->find($ttId);
        if (! $tt) {
            continue;
        }

        $unsold = $em->getRepository(Ticket::class)
            ->findBy(
                ['ticketType' => $tt, 'payment' => null],
                null,
                $count
            );

        foreach ($unsold as $ticket) {
            $ticket->setPayment($payment);
            $em->persist($ticket);
        }
    }

    // 5) Final persist
    $em->flush();

    return new Response('OK', 200);
}


}
