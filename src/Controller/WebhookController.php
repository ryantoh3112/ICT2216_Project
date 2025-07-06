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

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $payment = $payments->findOneBy(['sessionId' => $session->id]);
                if ($payment) {
                    $payment
                        ->setStatus('completed')
                        ->setPaymentDateTime((new \DateTime())->setTimestamp($session->created));
                    $em->persist($payment);

                    $hist = new History();
                    $hist
                      ->setUser($payment->getUser())
                      ->setPayment($payment)
                      ->setAction('Payment completed via webhook')
                      ->setTimestamp(new \DateTime())
                      ->setSessionId($session->id)
                      ->setStatus('completed');
                    $em->persist($hist);

                    // record PurchaseHistory & clear cart…
                    $cartItems = $em->getRepository(CartItem::class)
                                    ->findBy(['user' => $payment->getUser()]);
                    foreach ($cartItems as $ci) {
                        $ph = new PurchaseHistory();
                        $ph
                          ->setUser($payment->getUser())
                          ->setPayment($payment)
                          ->setProductName($ci->getName())
                          ->setUnitPrice((string)$ci->getPrice())
                          ->setQuantity($ci->getQuantity());
                        $em->persist($ph);
                        $em->remove($ci);
                    }
                }
                break;

            case 'checkout.session.expired':
                $session = $event->data->object;
                $payment = $payments->findOneBy(['sessionId' => $session->id]);
                if ($payment && $payment->getStatus() === 'pending') {
                    $payment->setStatus('cancelled');
                    $em->persist($payment);

                    $hist = new History();
                    $hist
                      ->setUser($payment->getUser())
                      ->setPayment($payment)
                      ->setAction('Session expired / cancelled')
                      ->setTimestamp(new \DateTime())
                      ->setSessionId($session->id)
                      ->setStatus('cancelled');
                    $em->persist($hist);
                }
                break;

            // you can also handle `checkout.session.async_payment_failed` if you like…

            default:
                // ignore other events
        }

        $em->flush();

        return new Response('OK', 200);
    }
}
