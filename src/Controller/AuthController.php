<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;
use App\Service\JwtService;
use App\Service\EmailService;
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
use Symfony\Component\Uid\Uuid; 

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
        $user->setCreatedAt(new \DateTime());
        $user->setAccountStatus("active");

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
    public function loginForm(Request $request): Response
    {
        $user = $request->attributes->get('jwt_user');
        if ($user && $user->getRole() === 'ROLE_ADMIN') {
            return $this->redirectToRoute('admin_dashboard');
        } elseif ($user && $user->getRole() === 'ROLE_USER') {
            return $this->redirectToRoute('user_profile');
        }
        else {
            return $this->render('auth/login.html.twig');
        }
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
                if ($current + 1 >= 10) {
                    $user->setAccountStatus('locked');
                    $user->setLockedAt(new \DateTime());
                }
                $em->persist($user);
                $em->flush();
            }
            $this->addFlash('error', 'Invalid credentials');
            return $this->redirectToRoute('auth_login_form'); // Or your login form route name
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
    #[Route('/verify-redirect', name: 'login_success')]
    public function verifyJwtAndRedirect(
        Request $request, 
        JwtService $jwtService,
        UserRepository $userRepository,
        AuthRepository $authRepository
    ): Response {
        $jwt = $request->cookies->get('JWT');

        if (!$jwt) {
            $this->addFlash('error', 'Please log in first.');
            return $this->redirectToRoute('auth_login_form');
        }

        try {
            $payload = $jwtService->verifyToken($jwt);
            
            // Get user from DB using the ID from the JWT payload
            $userId = $payload['id'] ?? null;

            if (!$userId) {
                $this->addFlash('error', 'Invalid token payload.');
                return $this->redirectToRoute('auth_login_form');
            }
            $auth = $authRepository->findOneBy(['user' => $userId]);
            // 3. Check roles from DB and redirect accordingly
            #$user = $request->attributes->get('jwt_user');
            
            if (!$auth) {
                $this->addFlash('error', 'Authentication record not found.');
                return $this->redirectToRoute('auth_login_form');
            }

            // Get the actual User entity
            #$user = $auth->getUser();
            $roles = $auth->getRoles();
            if (in_array('ROLE_ADMIN', $roles)) {
                return $this->redirectToRoute('admin_dashboard');
            } elseif (in_array('ROLE_USER', $roles)) {
                return $this->redirectToRoute('user_profile');
            } else {
                $this->addFlash('error', 'Unauthorized access.');
                return $this->redirectToRoute('auth_login_form');
            }
        } 
        
        catch (\Exception $e) {
            $this->addFlash('error', 'Invalid or expired token');
            #echo 'Message: ' .$e->getMessage();
            return $this->redirectToRoute('auth_login_form');
        }
    }
    
    #[Route('/forgot_pwd', name: 'forgot_password_form', methods: ['GET'])]
    public function showForgotPasswordForm(Request $request): Response
    {
        $user = $request->attributes->get('jwt_user');

        if ($user) {
            // Redirect authenticated users to their user profile page
            return $this->redirectToRoute('user_profile');
        }
        return $this->render('auth/forgot_pwd.html.twig');
    }

    
    #[Route('/forgot_pwd', name: 'forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        AuthRepository $authRepository,
        EntityManagerInterface $em,
        EmailService $emailService
    ): Response {
        $email = $request->request->get('email');
        $auth = $authRepository->findOneBy(['email' => $email]);
        $user = $auth?->getUser();

        if ($user) {
            // // 1. Generate OTP
            // $otp = random_int(100000, 999999);
            // $user->setOtpCode((string) $otp);
            // $user->setOtpExpiresAt(new \DateTimeImmutable('+10 minutes'));

            // // 2. Save to DB
            // $em->flush();

            // // 3. Send email using Auth's email, and User's name
            // $emailOtpService->sendOtp(
            //     $auth->getEmail(),
            //     $user->getName(), // or getUsername() if that's your naming
            //     $otp
            // );

            $token = Uuid::v4()->toRfc4122(); // Generate secure unique token
            $expiresAt = new \DateTimeImmutable('+15 minutes');

            $user->setResetToken($token);
            $user->setResetTokenExpiresAt($expiresAt);
            $em->flush();
        }
                    // Send email with token link
        $emailService->sendResetPasswordLink($auth->getEmail(), $user->getName(), $token);

        // 4. Flash message (same response for both cases to protect privacy)
        $this->addFlash('success', 'If this email is registered, a reset link has been sent.');

        return $this->redirectToRoute('auth_forgot_password_form');
    }


    #[Route('/reset-password', name: 'reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $token = $request->query->get('token') ?? $request->request->get('token');

        if (!$token) {
            $this->addFlash('error', 'Missing reset token.');
            return $this->redirectToRoute('auth_forgot_password_form');
        }

        $user = $userRepository->findOneBy(['resetToken' => $token]);

        if (
            !$user ||
            !$user->getResetTokenExpiresAt() ||
            $user->getResetTokenExpiresAt() < new \DateTimeImmutable()
        ) {
            $this->addFlash('error', 'Reset link is invalid or expired.');
            return $this->redirectToRoute('auth_forgot_password_form');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $confirm = $request->request->get('confirm_password');

            if (
                strlen($password) < 8 ||
                !preg_match('/[A-Z]/', $password) ||
                !preg_match('/[a-z]/', $password) ||
                !preg_match('/[0-9]/', $password) ||
                !preg_match('/[\W]/', $password)
            ) {
                $this->addFlash('error', 'Password must be at least 8 characters long and include uppercase, lowercase, number, and special character.');
                return $this->redirectToRoute('auth_reset_password', ['token' => $token]);
            }

            if ($password !== $confirm) {
                $this->addFlash('error', 'Passwords do not match.');
                return $this->redirectToRoute('auth_reset_password', ['token' => $token]);
            }

            // Reset password
            $auth = $user->getAuth();
            if (!$auth) {
                $this->addFlash('error', 'Something went wrong while updating your password.');
                return $this->redirectToRoute('auth_forgot_password_form');
            }
            $auth->setPassword($hasher->hashPassword($auth, $password));

            // Invalidate reset token immediately
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);

            $em->flush();

            // Invalidate the session (if logged in)
            $session = $request->getSession();
            $this->addFlash('success', 'Password successfully reset. You can now log in.');
            $session->invalidate();

            $response = $this->redirectToRoute('auth_login_form');
            $response->headers->clearCookie('JWT'); // Remove the JWT cookie
            return $response;
        }

        return $this->render('auth/reset_pwd.html.twig', [
            'token' => $token,
            'email' => $user->getAuth()->getEmail()
        ]);
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
                $this->addFlash('error', 'Your session has expired or is invalid. Please log in again.');
                return $this->redirectToRoute('auth_login_form');// Token might already be expired or invalid – ignore and continue
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
