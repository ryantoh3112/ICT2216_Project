<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;
use App\Service\JwtService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Entity\Auth;
use App\Entity\JWTSession;
use App\Repository\UserRepository;
use App\Repository\AuthRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#Conroller-level prefix for all routes in this controller
#Everything below is part of /auth/...
#[Route('/auth', name: 'auth_')]
final class AuthController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        AuthRepository $authRepository
    ): Response
    {
        if ($request->isMethod('POST')) {
        $name = $request->request->get('username');
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $confirmPassword = $request->request->get('confirm_password');
        
        // Check if there is an existing email in database / email has been registered already
        $existingAuth = $authRepository->findOneBy(['email' => $email]);
        if ($existingAuth !== null) {
            $this->addFlash('error', 'Email has already been registered.');
            return $this->redirectToRoute('auth_register');
        }

        // Check if there is an existing username in database / username has been registered already
        $existingUser = $userRepository->findOneBy(['name' => $name]);
        if ($existingUser !== null) {
            $this->addFlash('error', 'Username is already taken.');
            return $this->redirectToRoute('auth_register');
        }

        // Check if password and confirm password matches
        if ($password !== $confirmPassword) {
            $this->addFlash('error', 'Passwords do not match.');
            return $this->redirectToRoute('auth_register');
        }

        // Create User entity
        $user = new User();
        $user->setName($name);
        $user->setRole('ROLE_USER');
        $user->setCreatedAt(new \DateTime('now', new \DateTimeZone('Asia/Singapore')));

        // Persist User first
        $entityManager->persist($user);
        $entityManager->flush(); // needed to generate ID for relation

        // Create Auth entity
        $auth = new Auth();
        $auth->setUser($user);
        $auth->setEmail($email);
        $auth->setPassword(
            $passwordHasher->hashPassword($auth, $password)
        );

        // Persist Auth
        $entityManager->persist($auth);
        $entityManager->flush();

        $this->addFlash('success', 'Registration successful!');
        return $this->redirectToRoute('auth_login_form');
        }

        // Render the registration page (GET)
        return $this->render('auth/register.html.twig', [
            'controller_name' => 'AuthController',
        ]);
    }

// #[Route('/login', name: 'login_form', methods: ['GET'])]
// public function loginForm(Request $request): Response
// {
//     $showCaptcha = false;

//     // Check for login fail count cookie
//     $failCount = $request->cookies->get('login_fail_count');
//     if ($failCount !== null && (int)$failCount >= 3) {
//         $showCaptcha = true;
//     }

//     return $this->render('auth/login.html.twig', [
//         'show_captcha' => $showCaptcha,
//         'recaptcha_site_key' => $_ENV['RECAPTCHA_SITE_KEY'], // or use parameter('recaptcha.site_key')
//     ]);
// }

#[Route('/login', name: 'login_form', methods: ['GET'])]
public function loginForm(
    Request $request,
    AuthRepository $authRepository
): Response {
    // get last email from session (or null)
    $email = $request->getSession()->get('last_login_email');

    $showCaptcha = false;
    if ($email) {
        $auth = $authRepository->findOneBy(['email' => $email]);
        if ($auth && ($auth->getUser()->getFailedLoginCount() ?? 0) >= 3) {
            $showCaptcha = true;
        }
    }

    return $this->render('auth/login.html.twig', [
        'show_captcha'      => $showCaptcha,
        'recaptcha_site_key'=> $_ENV['RECAPTCHA_SITE_KEY'],
        'last_email'        => $email,
    ]);
}



#[Route('/login', name: 'login', methods: ['POST'])]
public function login(
    Request $request,
    AuthRepository $authRepository,
    UserPasswordHasherInterface $passwordHasher,
    JwtService $jwtService,
    EntityManagerInterface $em,
    HttpClientInterface $httpClient   
): Response {
    $email = $request->request->get('email');
    $password = $request->request->get('password');

    // Step 1: Validate credentials
    $auth = $authRepository->findOneBy(['email' => $email]);
    $needsCaptcha  = $auth && ($auth->getUser()->getFailedLoginCount() ?? 0) >= 3;

     if ($needsCaptcha) {
            $recaptchaResponse = $request->request->get('g-recaptcha-response', '');
            $resp = $httpClient->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
                'body' => [
                    'secret'   => $_ENV['RECAPTCHA_SECRET_KEY'],
                    'response' => $recaptchaResponse,
                    'remoteip' => $request->server->get('REMOTE_ADDR'),
                ],
            ]);
            $data = $resp->toArray();
            if (empty($data['success'])) {
                $this->addFlash('error', 'Please complete the CAPTCHA.');
                // store the last email in session
                $request->getSession()->set('last_login_email', $email);

                return $this->redirectToRoute('auth_login_form');            }
        }

    // Check if account is locked
    if ($auth && $auth->getUser()->getAccountStatus() === 'locked') {
        $this->addFlash('error', 'Account is locked. Please contact support.');
        return $this->redirectToRoute('auth_login_form'); // Or your login form route name
    }
    
    if (!$auth || !$passwordHasher->isPasswordValid($auth, $password)) {
        // Increment failed_login_count if user exists
        if ($auth) {
            $user = $auth->getUser();
            $current = $user->getFailedLoginCount() ?? 0;
            $user->setFailedLoginCount($current + 1);

            // Lock the account if attempts exceed 10
            if ($current + 1 >= 1000) {
                $user->setAccountStatus('locked');
                $user->setLockedAt(new \DateTime());
            }
            $em->persist($user);
            $em->flush();
        }
        $this->addFlash('error','Invalid credentials');
        $request->getSession()->set('last_login_email', $email);
        return $this->redirectToRoute('auth_login_form');
}
    # If credentials are valid, reset failed_login_count
    $auth->getUser()->setFailedLoginCount(0);
    $em->persist($auth->getUser());
    $em->flush();

    // Step 2: Generate JWT
    $token = $jwtService->createToken([
        'id' => $auth->getUser()->getId(),
        'email' => $auth->getEmail()
    ]);
    $decodedPayload = $jwtService->verifyToken($token); // decode to get iat
    $issuedAt = (new \DateTime())->setTimestamp($decodedPayload['iat']);
    $expiresAt = (new \DateTime())->setTimestamp($decodedPayload['exp']);

    // Step 3: Track JWT in database
    $jwtEntity = new JWTSession();
    $jwtEntity->setUser($auth->getUser());
    $jwtEntity->setExpiresAt($expiresAt);
    $jwtEntity->setIssuedAt($issuedAt);  // New Field added

    // 🔧 Update login metadata
    $user = $auth->getUser();
    $user->setLastLoginAt(new \DateTime());
    $user->setAccountStatus('active');

    $em->persist($jwtEntity);
    $em->flush();

    // Step 4: Set JWT as HttpOnly cookie
    $cookie = Cookie::create('JWT')
        ->withValue($token)
        ->withExpires($expiresAt)
        ->withHttpOnly(true)
        ->withSecure(false)
        ->withPath('/')
        ->withSameSite('Lax');

    $response = new RedirectResponse($this->generateUrl('auth_login_success')); // or another route
    $response->headers->setCookie($cookie);
    return $response;
}


# For testing purposes, this endpoint will return the user information from the JWT cookie
#[Route('/verify-jwt', name: 'login_success')]
public function verifyJwtAndRedirect(Request $request, JwtService $jwtService): Response
{
    $jwt = $request->cookies->get('JWT');

    if (!$jwt) {
        $this->addFlash('error', 'Please log in first.');
        return $this->redirectToRoute('auth_login_form');
    }

    try {
        $payload = $jwtService->verifyToken($jwt);
        // You can optionally store payload in the session or context here if needed
        return $this->redirectToRoute('user_profile');
    } catch (\Exception $e) {
        $this->addFlash('error', 'Invalid or expired token.');
        return $this->redirectToRoute('auth_login_form');
    }
}

# Revoked once User logs out
#[Route('/logout', name: 'logout')]
public function logout(
    Request $request,
    EntityManagerInterface $em,
    JwtService $jwtService
): Response {
    // Step 1: Extract the JWT from cookie
    $jwt = $request->cookies->get('JWT');
    if ($jwt) {
        try {
            // Step 2: Verify and decode the token
            $payload = $jwtService->verifyToken($jwt);
            $issuedAt = (new \DateTime())->setTimestamp($payload['iat']);
            dump([
                'payload_iat' => $payload['iat'],
                'issuedAt_object' => $issuedAt->format('Y-m-d H:i:s'),
            ]);
            // Step 3: Find the matching JWT record in DB
            $repo = $em->getRepository(JWTSession::class);
            $jwtRecord = $em->getRepository(JWTSession::class)->findOneBy([
                'user' => $payload['id'],
                'issuedAt' => $issuedAt,
                'revokedAt' => null
            ]);

            // Step 4: Revoke the token by setting revoked_at
            if ($jwtRecord) {
                $jwtRecord->setRevokedAt(new \DateTime());
                $em->flush();
            }
        } catch (\Exception $e) {
            // Token might already be expired or invalid – ignore and continue
        }
    }

    // Clear session (if used)
    $request->getSession()->clear();

    // Expire JWT cookie
    $expiredCookie = Cookie::create('JWT')
        ->withValue('')
        ->withExpires(new \DateTime('-1 day'))
        ->withPath('/')
        ->withHttpOnly(true)
        ->withSecure(false)
        ->withSameSite('Lax');

    // Redirect and attach the expired cookie
    $response = $this->redirectToRoute('auth_login_form');
    $response->headers->setCookie($expiredCookie);

    $this->addFlash('success', 'You have been logged out.');

    return $response;
    }
}
