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
//         // Invalid payload or signature → 400
//         return new Response('Invalid payload or signature', 400);
//     }

//     if ($event->type !== 'checkout.session.completed') {
//         // Only process completed sessions
//         // 200 so Stripe won’t retry
//         return new Response('Event ignored: ' . $event->type, 200);
//     }

//     $session = $event->data->object;
//     $payment = $payments->findOneBy(['sessionId' => $session->id]);

//     if (! $payment) {
//         // Return 200 to avoid retries; log missing payment on your side
//         // (Could also log: “No payment found for session {id}”)
//         return new Response('No matching payment; ignoring', 200);
//     }

//     // 1) Mark payment completed
//     $payment
//         ->setStatus('completed')
//         ->setPaymentDateTime((new \DateTime())->setTimestamp($session->created));
//     $em->persist($payment);

//     // 2) Log History
//     $hist = (new History())
//         ->setUser($payment->getUser())
//         ->setPayment($payment)
//         ->setAction('Payment completed via webhook')
//         ->setTimestamp(new \DateTime())
//         ->setSessionId($session->id)
//         ->setStatus('completed');
//     $em->persist($hist);

//     // 3) Build a map of TicketType → quantity from CartItems,
//     //    persist PurchaseHistory and remove CartItems
//     $cartItems  = $em->getRepository(CartItem::class)
//                      ->findBy(['user' => $payment->getUser()]);
//     $qtyByType  = [];
//     foreach ($cartItems as $ci) {
//         $ttId = $ci->getTicketType()->getId();
//         $qtyByType[$ttId] = ($qtyByType[$ttId] ?? 0) + $ci->getQuantity();

//         $ph = (new PurchaseHistory())
//             ->setUser($payment->getUser())
//             ->setPayment($payment)
//             ->setProductName($ci->getName())
//             ->setUnitPrice((string)$ci->getPrice())
//             ->setQuantity($ci->getQuantity());
//         $em->persist($ph);

//         $em->remove($ci);
//     }

//     // 4) Assign unsold tickets in bulk
//     foreach ($qtyByType as $ttId => $count) {
//         $tt = $em->getRepository(TicketType::class)->find($ttId);
//         if (! $tt) {
//             continue;
//         }

//         $unsold = $em->getRepository(Ticket::class)
//             ->findBy(
//                 ['ticketType' => $tt, 'payment' => null],
//                 null,
//                 $count
//             );

//         foreach ($unsold as $ticket) {
//             $ticket->setPayment($payment);
//             $em->persist($ticket);
//         }
//     }

//     // 5) Flush everything in one go
//     $em->flush();

//     return new Response('Webhook handled successfully', 200);
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

        // Look up our Payment by the session ID
        $session = $event->data->object;
        $payment = $payments->findOneBy(['sessionId' => $session->id]);

        // If it's not one we know about, ACK and ignore
        if (! $payment) {
            return new Response('No matching payment; ignoring', 200);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                // mark completed
                $payment
                    ->setStatus('completed')
                    ->setPaymentDateTime((new \DateTime())
                        ->setTimestamp($session->created)
                    );
                $em->persist($payment);

                // log history
                $em->persist((new History())
                    ->setUser($payment->getUser())
                    ->setPayment($payment)
                    ->setAction('Payment completed via webhook')
                    ->setTimestamp(new \DateTime())
                    ->setSessionId($session->id)
                    ->setStatus('completed')
                );

                // move CartItems → PurchaseHistory & clear cart…
                $cartItems = $em->getRepository(CartItem::class)
                                ->findBy(['user' => $payment->getUser()]);
                $counts = [];
                foreach ($cartItems as $ci) {
                    $ttId = $ci->getTicketType()->getId();
                    $counts[$ttId] = ($counts[$ttId] ?? 0) + $ci->getQuantity();

                    $em->persist((new PurchaseHistory())
                        ->setUser($payment->getUser())
                        ->setPayment($payment)
                        ->setProductName($ci->getName())
                        ->setUnitPrice((string)$ci->getPrice())
                        ->setQuantity($ci->getQuantity())
                    );
                    $em->remove($ci);
                }

                // …and assign actual Ticket entities
                foreach ($counts as $ttId => $qty) {
                    $tt = $em->getRepository(TicketType::class)->find($ttId);
                    if (!$tt) continue;
                    $unsold = $em->getRepository(Ticket::class)
                                 ->findBy(['ticketType' => $tt, 'payment' => null], null, $qty);
                    foreach ($unsold as $t) {
                        $t->setPayment($payment);
                        $em->persist($t);
                    }
                }
                break;

            case 'checkout.session.expired':
                // only cancel if still pending
                if ($payment->getStatus() === 'pending') {
                    $payment->setStatus('cancelled');
                    $em->persist($payment);

                    $em->persist((new History())
                        ->setUser($payment->getUser())
                        ->setPayment($payment)
                        ->setAction('Checkout session expired / cancelled')
                        ->setTimestamp(new \DateTime())
                        ->setSessionId($session->id)
                        ->setStatus('cancelled')
                    );
                }
                break;

            default:
                // we deliberately ignore all other event types
                return new Response('Event ignored: ' . $event->type, 200);
        }

        $em->flush();
        return new Response('Webhook handled successfully', 200);
    }

}
