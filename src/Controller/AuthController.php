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
use App\Entity\JWTBlacklist;
use App\Repository\UserRepository;
use App\Repository\AuthRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
#[Route('/login', name: 'login_form', methods: ['GET'])]
public function loginForm(): Response
{
    return $this->render('auth/login.html.twig');
}

#[Route('/login', name: 'login', methods: ['POST'])]
public function login(
    Request $request,
    AuthRepository $authRepository,
    UserPasswordHasherInterface $passwordHasher,
    JwtService $jwtService,
    EntityManagerInterface $em
): Response {
    $email = $request->request->get('email');
    $password = $request->request->get('password');

    // Step 1: Validate credentials
    $auth = $authRepository->findOneBy(['email' => $email]);
    if (!$auth || !$passwordHasher->isPasswordValid($auth, $password)) {
        return new JsonResponse(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
    }

    // Step 2: Generate JWT
    $token = $jwtService->createToken([
        'id' => $auth->getUser()->getId(),
        'email' => $auth->getEmail()
    ]);

    // Step 3: Track JWT in database
    $expiresAt = (new \DateTime())->add(new \DateInterval('PT1H')); // expires in 1 hour

    $jwtEntity = new JWTBlacklist();
    $jwtEntity->setUser($auth->getUser());
    $jwtEntity->setExpiresAt($expiresAt);

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


#[Route('/logout', name: 'logout')]
public function logout(Request $request): Response
{
    // Clear session (if used)
    $request->getSession()->clear();

    // Invalidate the JWT cookie by setting it to empty and expiring it
    $expiredCookie = Cookie::create('JWT')
        ->withValue('')
        ->withExpires(new \DateTime('-1 day'))
        ->withPath('/')
        ->withHttpOnly(true)
        ->withSecure(false)   // Set to true if using HTTPS
        ->withSameSite('Lax');

    // Redirect and attach the cookie
    $response = $this->redirectToRoute('auth_login_form');
    $response->headers->setCookie($expiredCookie);

    // Optional flash message
    $this->addFlash('success', 'You have been logged out.');

    return $response;
}


}