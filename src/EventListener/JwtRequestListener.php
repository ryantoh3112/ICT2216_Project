<?php
# EventListener that intercepts incoming requests and perform JWT validation 
# before the request reaches your controllers.
# Is like a Middleware. 
# Those with logger is for debugging purposes, you can remove them if you don't need it.
namespace App\EventListener;

use App\Entity\User;
use App\Entity\JWTSession;
use App\Service\JwtService;
use App\Entity\JwtToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Log\LoggerInterface;

class JwtRequestListener
{
    private JwtService $jwtService;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(JwtService $jwtService, EntityManagerInterface $em, LoggerInterface $logger)
    {
        $this->jwtService = $jwtService;
        $this->em = $em;
        $this->logger = $logger;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->cookies->has('JWT')) return;

        $jwt = $request->cookies->get('JWT');
        $this->logger->info('🔍 JWT cookie found: ' . $jwt);

        # Validate the JWT token
        try {
            $payload = $this->jwtService->verifyToken($jwt);
            $this->logger->info('✅ Token verified. Payload: ', $payload);
        } catch (\Exception $e) {
            $this->logger->error('❌ JWT verification failed: ' . $e->getMessage());
            throw new AccessDeniedHttpException('Invalid token');
        }
        
        # Fetches user from DB based on the ID in the JWT payload
        $user = $this->em->getRepository(User::class)->find($payload['id']);
        if (!$user) {
            $this->logger->warning('❌ User not found for ID: ' . $payload['id']);
            throw new AccessDeniedHttpException('User not found');
        }
        if (!isset($payload['iat']) || !is_numeric($payload['iat'])) {
            $this->logger->error('❌ Missing or invalid "iat" in JWT payload.');
            throw new AccessDeniedHttpException('Token is missing issue time.');
        }

        $issuedAt = (new \DateTime())->setTimestamp($payload['iat']);
        $this->logger->info('🕒 Looking for JWTSession within ±2s of ' . $issuedAt->format('Y-m-d H:i:s'));

        $iatStart = (new \DateTime())->setTimestamp($payload['iat'] - 2);
        $iatEnd = (new \DateTime())->setTimestamp($payload['iat'] + 2);

        # Searches for matching JWTSession entity in DB
        $qb = $this->em->getRepository(JWTSession::class)->createQueryBuilder('j');
        $qb->where('j.user = :user')
        ->andWhere('j.revokedAt IS NULL')
        ->andWhere('j.issuedAt BETWEEN :start AND :end')
        ->setParameter('user', $user)
        ->setParameter('start', $iatStart)
        ->setParameter('end', $iatEnd);

        $tokenRecord = $qb->getQuery()->getOneOrNullResult();



        if (!$tokenRecord) {
            $this->logger->warning('❌ No matching JWTSession found for user ID: ' . $user->getId() . ' with issuedAt = ' . $issuedAt->format('Y-m-d H:i:s'));
        } elseif ($tokenRecord->getExpiresAt() < new \DateTime()) {
            $this->logger->warning('❌ JWTSession expired at: ' . $tokenRecord->getExpiresAt()->format('Y-m-d H:i:s'));
        }

        if (!$tokenRecord || $tokenRecord->getExpiresAt() < new \DateTime()) {
            throw new AccessDeniedHttpException('Token expired or revoked');
        }

        $this->logger->info('✅ JWTSession valid. Injecting user into request.');
        $request->attributes->set('jwt_user', $user);
    }

}
?>