<?php
// src/Controller/QrCodeController.php

namespace App\Controller;

use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\JwtService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Writer;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class QrCodeController extends AbstractController
{
    public function __construct(
        private readonly JwtService        $jwtService,
        private readonly UserRepository    $userRepo,
        private readonly TicketRepository  $ticketRepo,
    ) {}

    /**
     * 1) GET /ticket/{token}/qrcode.png
     *    - Generates the QR code PNG, embedding the validation URL.
     */
    #[Route('/ticket/{token}/qrcode.png', name: 'ticket_qrcode', methods: ['GET'])]
    public function qrCode(string $token, Request $request): Response
    {
        // (a) ensure user session is valid
        $jwt = $request->cookies->get('JWT');
        if (!$jwt) {
            throw new AccessDeniedHttpException('Login required to view QR code.');
        }
        try {
            $payload = $this->jwtService->verifyToken($jwt);
        } catch (\Exception) {
            throw new AccessDeniedHttpException('Invalid session token.');
        }

        // (b) build the **validation** URL that will be encoded in the QR
        $validationUrl = $this->generateUrl('validate_ticket', [
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // (c) render 300x300 PNG with margin=10
        $renderer = new ImageRenderer(new RendererStyle(300, 10), new ImagickImageBackEnd());
        $writer   = new Writer($renderer);
        $png      = $writer->writeString($validationUrl);

        return new Response($png, 200, [
            'Content-Type'    => 'image/png',
            'Cache-Control'   => 'no-store, must-revalidate',
            'X-Frame-Options' => 'DENY',
        ]);
    }

    /**
     * 2) GET /ticket/{token}/validate
     *    - Called by scanning apps; returns JSON telling you if the ticket
     *      is valid, what seat, event, and expiry timestamp.
     */
    #[Route('/ticket/{token}/validate', name: 'validate_ticket', methods: ['GET'])]
    public function validate(string $token, Request $request): JsonResponse
    {
        // (a) authenticate exactly the same way,
        //     or drop it if you trust the secret token alone.
        $jwt = $request->cookies->get('JWT');
        if (!$jwt) {
            throw new AccessDeniedHttpException('Login required to validate ticket.');
        }
        try {
            $payload = $this->jwtService->verifyToken($jwt);
        } catch (\Exception) {
            throw new AccessDeniedHttpException('Invalid session token.');
        }

        // (b) load the current User
        $userId = $payload['id'] ?? null;
        $user   = $userId ? $this->userRepo->find($userId) : null;
        if (!$user) {
            throw new AccessDeniedHttpException('User not found.');
        }

        // (c) fetch the Ticket by qrToken **and** ownership
        $qb = $this->ticketRepo->createQueryBuilder('t')
            ->join('t.payment', 'p')
            ->andWhere('t.qrToken = :tok')
            ->andWhere('p.user = :me')
            ->setParameter('tok', $token)
            ->setParameter('me',  $user)
            ->getQuery();

        $ticket = $qb->getOneOrNullResult();
        if (!$ticket) {
            throw new NotFoundHttpException('Ticket not found or not yours.');
        }

        // (d) check expiry vs. now
        $expiresAt = $ticket->getQrExpiresAt();
        $valid     = $expiresAt?->getTimestamp() >= time();

        // (e) return structured JSON
        return new JsonResponse([
            'valid'     => $valid,
            'event'     => $ticket->getEvent()->getName(),
            'seat'      => $ticket->getSeatNumber(),
            'expiresAt' => $expiresAt?->format(\DateTime::ATOM),
        ]);
    }
}
