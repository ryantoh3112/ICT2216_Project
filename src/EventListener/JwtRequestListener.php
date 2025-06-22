<?php

namespace App\EventListener;

use App\Entity\User;
use App\Entity\JWTBlacklist;
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
        $request->attributes->set('jwt_user', $user);

        $tokenRecord = $this->em->getRepository(JWTBlacklist::class)
            ->findOneBy(['user' => $user]);

        if (!$tokenRecord ||
            $tokenRecord->getRevokedAt() !== null ||
            $tokenRecord->getExpiresAt() < new \DateTime()) {
            throw new AccessDeniedHttpException('Token expired or revoked');
        }
        // ✅ Make user available to controller
        $request->attributes->set('jwt_user', $user);
    }
}
?>