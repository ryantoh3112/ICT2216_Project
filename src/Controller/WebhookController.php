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

        $session = $event->data->object;
        $payment = $payments->findOneBy(['sessionId' => $session->id]);

        if (! $payment) {
            return new Response('No matching payment; ignoring', 200);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                // 1) mark payment completed
                $payment
                    ->setStatus('completed')
                    ->setPaymentDateTime((new \DateTime())->setTimestamp($session->created));
                $em->persist($payment);

                // 2) log history
                $em->persist((new History())
                    ->setUser($payment->getUser())
                    ->setPayment($payment)
                    ->setAction('Payment completed via webhook')
                    ->setTimestamp(new \DateTime())
                    ->setSessionId($session->id)
                    ->setStatus('completed')
                );

                // 3) move CartItems → PurchaseHistory & clear cart
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
                        ->setUnitPrice((float)$ci->getPrice())
                        ->setQuantity($ci->getQuantity())
                    );
                    $em->remove($ci);
                }
                // 4) assign and QR‐code tickets
                foreach ($counts as $ttId => $qty) {
                    $tt = $em->getRepository(TicketType::class)->find($ttId);
                    if (!$tt) {
                        continue;
                    }

                    $unsold = $em->getRepository(Ticket::class)
                                ->findBy(['ticketType' => $tt, 'payment' => null], null, $qty);
                    foreach ($unsold as $t) {
                        $t->setPayment($payment)
                        ->setQrToken(bin2hex(random_bytes(32)));

                        // expire at the *end* of the event day, not midnight
                        $eventDate = $t->getEvent()?->getEventDate();
                        if ($eventDate instanceof \DateTimeInterface) {
                            // clone so we don’t mutate the original
                            $expiresAt = (clone $eventDate)
                                ->setTime(23, 59, 59);
                            $t->setQrExpiresAt($expiresAt);
                        } else {
                            // fallback to 24h TTL
                            $t->setQrExpiresAt((new \DateTimeImmutable())->modify('+1 day'));
                        }

                        $em->persist($t);
                    }
                }
                break;

            case 'checkout.session.expired':
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
                return new Response('Event ignored: ' . $event->type, 200);
        }

        $em->flush();
        return new Response('Webhook handled successfully', 200);
    }
}
