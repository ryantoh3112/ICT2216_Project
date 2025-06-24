<?php

namespace App\EventListener;

use App\Entity\User;
use App\Entity\JWTSession;
use App\Service\JwtService;
use App\Entity\JwtToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\RequestStack;

class JwtRequestListener
{
    private JwtService $jwtService;
    private EntityManagerInterface $em;

    public function __construct(JwtService $jwtService, EntityManagerInterface $em)
    {
        $this->jwtService = $jwtService;
        $this->em = $em;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->cookies->has('JWT')) return;

        $jwt = $request->cookies->get('JWT');

        try {
            $payload = $this->jwtService->verifyToken($jwt);
        } catch (\Exception $e) {
            throw new AccessDeniedHttpException('Invalid token');
        }

        $user = $this->em->getRepository(User::class)->find($payload['id']);
        if (!$user) {
            throw new AccessDeniedHttpException('User not found');
        }

        $issuedAt = (new \DateTime())->setTimestamp($payload['iat']);

        $tokenRecord = $this->em->getRepository(JWTSession::class)
            ->findOneBy([
                'user' => $user,
                'issuedAt' => $issuedAt,
                'revokedAt' => null
            ]);

        if (!$tokenRecord || $tokenRecord->getExpiresAt() < new \DateTime()) {
            throw new AccessDeniedHttpException('Token expired or revoked');
        }

        // ✅ Make user available to controller
        $request->attributes->set('jwt_user', $user);
    }

}
?>