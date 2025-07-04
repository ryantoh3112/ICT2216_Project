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
use App\Entity\Captcha;
use App\Entity\JWTSession;
use App\Repository\CaptchaRepository;
use App\Repository\UserRepository;
use App\Repository\AuthRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid; 
use Symfony\Contracts\HttpClient\HttpClientInterface;

#Conroller-level prefix for all routes in this controller
#Everything below is part of /auth/...
#[Route('/auth', name: 'auth_')]
final class AuthController extends AbstractController
{
    // #[Route('/register', name: 'register', methods: ['GET', 'POST'])]
    // public function register(
    //     Request $request,
    //     EntityManagerInterface $em,
    //     UserPasswordHasherInterface $passwordHasher,
    //     AuthRepository $authRepository
    // ): Response {
    //     if ($request->isMethod('POST')) {
    //         $name            = $request->request->get('username');
    //         $email           = $request->request->get('email');
    //         $password        = $request->request->get('password');
    //         $confirmPassword = $request->request->get('confirm_password');

    //         // existing email?
    //         if ($authRepository->findOneBy(['email' => $email])) {
    //             $this->addFlash('error', 'Email already registered.');
    //             return $this->redirectToRoute('auth_register');
    //         }
    //         // password match?
    //         if ($password !== $confirmPassword) {
    //             $this->addFlash('error', 'Passwords do not match.');
    //             return $this->redirectToRoute('auth_register');
    //         }

    //         // create User + Auth...
    //         $user = new \App\Entity\User();
    //         $user->setName($name)
    //              ->setRole('ROLE_USER')
    //              ->setCreatedAt(new \DateTime())
    //              ->setAccountStatus('active')
    //              ->setOtpEnabled(false);
    //         $em->persist($user);
    //         $em->flush();

    //         $auth = new Auth();
    //         $auth->setUser($user)
    //              ->setEmail($email)
    //              ->setPassword($passwordHasher->hashPassword($auth, $password));
    //         $em->persist($auth);
    //         $em->flush();

    //         $this->addFlash('success', 'Registration successful.');
    //         return $this->redirectToRoute('auth_login_form');
    //     }

    //     return $this->render('auth/register.html.twig');
    // }
    #[Route('/register', name: 'register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        AuthRepository $authRepository,
        CaptchaRepository $captchaRepo,
        HttpClientInterface $httpClient
    ): Response {
        $ip          = $request->getClientIp();
        $fingerprint = substr(sha1((string)$request->headers->get('User-Agent')), 0, 32);

        // 1) fetch-or-create our tracker
        $attempt = $captchaRepo->findOneBy([
            'ipAddress'         => $ip,
            'deviceFingerprint' => $fingerprint,
        ]) ?? new Captcha($ip, $fingerprint);

        $now        = new \DateTimeImmutable();
        $oneHourAgo = $now->sub(new \DateInterval('PT1H'));

        // 2) if the last attempt was more than an hour ago, zero out its counter
        if ($attempt->getLastAttemptAt() < $oneHourAgo) {
            $attempt->reset();
        }

        $showCaptcha = $attempt->getAttemptCount() >= 3;

        // Handle POST
        if ($request->isMethod('POST')) {
            // 3) if we've already submitted 3+ times in the last hour, require CAPTCHA
            if ($showCaptcha) {
                $resp = $httpClient->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
                    'body' => [
                        'secret'   => $_ENV['RECAPTCHA_SECRET_KEY'],
                        'response' => $request->request->get('g-recaptcha-response', ''),
                        'remoteip' => $ip,
                    ],
                ]);
                if (empty($resp->toArray()['success'])) {
                    // count this failed CAPTCHA attempt
                    $attempt->incrementAttemptCount();
                    $em->persist($attempt);
                    $em->flush();

                    $this->addFlash('error', 'Please complete the CAPTCHA.');
                    return $this->redirectToRoute('auth_register');
                }
            }

            // 4) count **every** submission toward the 3-per-hour total
            $attempt->incrementAttemptCount();
            $em->persist($attempt);
            $em->flush();

            // now normal registration logic
            $name            = $request->request->get('username');
            $email           = $request->request->get('email');
            $password        = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            // existing email?
            if ($authRepository->findOneBy(['email' => $email])) {
                $this->addFlash('error', 'Email already registered.');
                return $this->redirectToRoute('auth_register');
            }
            // password match?
            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Passwords do not match.');
                return $this->redirectToRoute('auth_register');
            }

            // create User + Auth
            $user = new User();
            $user->setName($name)
                 ->setRole('ROLE_USER')
                 ->setCreatedAt(new \DateTime())
                 ->setAccountStatus('active')
                 ->setOtpEnabled(false);

            $em->persist($user);
            $em->flush();

            $auth = new Auth();
            $auth->setUser($user)
                 ->setEmail($email)
                 ->setPassword($passwordHasher->hashPassword($auth, $password));

            $em->persist($auth);
            $em->flush();

            $this->addFlash('success', 'Registration successful.');
            // note: we do NOT reset the CAPTCHA counter here; it will auto-reset only after 1h
            return $this->redirectToRoute('auth_login_form');
        }

        // GET: render form
        return $this->render('auth/register.html.twig', [
            'show_captcha'       => $showCaptcha,
            'recaptcha_site_key' => $_ENV['RECAPTCHA_SITE_KEY'],
        ]);
    }

    

 #[Route('/login', name: 'login_form', methods: ['GET'])]
public function loginForm(
    Request $request,
    CaptchaRepository $captchaRepo
): Response {
    $ip          = $request->getClientIp();
    $fingerprint = substr(sha1((string)$request->headers->get('User-Agent')), 0, 32);

    // 1) fetch-or-create tracker
    $attempt = $captchaRepo->findOneBy([
        'ipAddress'         => $ip,
        'deviceFingerprint' => $fingerprint,
    ]) ?? new Captcha($ip, $fingerprint);

    $now        = new \DateTimeImmutable();
    $oneHourAgo = $now->sub(new \DateInterval('PT1H'));

    // 2) reset **only** if last attempt was >1h ago
    if ($attempt->getLastAttemptAt() < $oneHourAgo) {
        $attempt->reset();
    }

    $showCaptcha = $attempt->getAttemptCount() >= 3;

    return $this->render('auth/login.html.twig', [
        'show_captcha'       => $showCaptcha,
        'recaptcha_site_key' => $_ENV['RECAPTCHA_SITE_KEY'],
    ]);
}

   #[Route('/login', name: 'login', methods: ['POST'])]
public function login(
    Request $request,
    AuthRepository $authRepository,
    UserPasswordHasherInterface $passwordHasher,
    JwtService $jwtService,
    EmailService $emailService,
    EntityManagerInterface $em,
    CaptchaRepository $captchaRepo,
    HttpClientInterface $httpClient
): Response {
    $ip          = $request->getClientIp();
    $fingerprint = substr(sha1((string)$request->headers->get('User-Agent')), 0, 32);

    // 1) Fetch-or-create the Captcha tracker
    $attempt = $captchaRepo->findOneBy([
        'ipAddress'         => $ip,
        'deviceFingerprint' => $fingerprint,
    ]) ?? new Captcha($ip, $fingerprint);

    $now        = new \DateTimeImmutable();
    $oneHourAgo = $now->sub(new \DateInterval('PT1H'));

    // 2) If last attempt >1h ago, reset the counter
    if ($attempt->getLastAttemptAt() < $oneHourAgo) {
        $attempt->reset();
    }

    // 3) If ≥3 attempts in past hour, require CAPTCHA
    if ($attempt->getAttemptCount() >= 3) {
        $resp = $httpClient->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => $_ENV['RECAPTCHA_SECRET_KEY'],
                'response' => $request->request->get('g-recaptcha-response', ''),
                'remoteip' => $ip,
            ],
        ]);
        if (empty($resp->toArray()['success'])) {
            // count this failed CAPTCHA attempt
            $attempt->incrementAttemptCount();
            $em->persist($attempt);
            $em->flush();

            $this->addFlash('error', 'Please complete the CAPTCHA.');
            return $this->redirectToRoute('auth_login_form');
        }
    }

    // 4) Count **every** submission toward the 3/hour total
    $attempt->incrementAttemptCount();
    $em->persist($attempt);
    $em->flush();

    // 5) Now validate credentials
    $email    = $request->request->get('email', '');
    $password = $request->request->get('password', '');
    $auth     = $authRepository->findOneBy(['email' => $email]);

    if (!$auth || ! $passwordHasher->isPasswordValid($auth, $password)) {
        // Invalid → bump the **User** failedLoginCount too
        if ($auth) {
            $user   = $auth->getUser();
            $fails  = $user->getFailedLoginCount() ?? 0;
            $user->setFailedLoginCount($fails + 1);
            // optionally lock at some higher threshold...
            $em->persist($user);
        }
        $em->flush();

        $this->addFlash('error', 'Invalid credentials.');
        return $this->redirectToRoute('auth_login_form');
    }

    // 6) Successful login → reset **User** failedLoginCount, but **do not** reset Captcha
    $user = $auth->getUser();
    $user->setFailedLoginCount(0)
         ->setLastLoginAt(new \DateTime())
         ->setAccountStatus('active');
    $em->persist($user);
    $em->flush();

    // 2FA: if enabled, send OTP and redirect to verification
    if ($user->isOtpEnabled()) {
        // generate 6-digit code
        $otp = random_int(100000, 999999);
        $user->setOtpCode((string) $otp)
             ->setOtpExpiresAt(new \DateTimeImmutable('+5 minutes'));
        $em->persist($user);
        $em->flush();

        // send via EmailService
        $emailService->sendOtp($auth->getEmail(), $user->getName(), $otp);

        // store pending user id for verification
        $request->getSession()->set('pending_2fa_user_id', $user->getId());

        return $this->redirectToRoute('auth_verify_otp_form');
    }

    // 7) Issue JWT as before
    $token   = $jwtService->createToken([
        'id'    => $user->getId(),
        'email' => $auth->getEmail(),
    ]);
    $payload = $jwtService->verifyToken($token);
    $issued  = (new \DateTime())->setTimestamp($payload['iat']);
    $expires = (new \DateTime())->setTimestamp($payload['exp']);

    $jwtSession = (new JWTSession())
        ->setUser($user)
        ->setIssuedAt($issued)
        ->setExpiresAt($expires);
    $em->persist($jwtSession);
    $em->flush();

    $cookie = Cookie::create('JWT')
        ->withValue($token)
        ->withExpires($expires)
        ->withHttpOnly(true)
        ->withPath('/')
        ->withSameSite('Lax');

    $response = new RedirectResponse($this->generateUrl('user_profile'));
    $response->headers->setCookie($cookie);
    return $response;
}


    // #[Route('/login', name: 'login_form', methods: ['GET'])]
    // public function loginForm(
    //     Request $request,
    //     AuthRepository $authRepository
    // ): Response {
    //     // get last email from session (or null)
    //     $email = $request->getSession()->get('last_login_email');

    //     $showCaptcha = false;
    //     if ($email) {
    //         $auth = $authRepository->findOneBy(['email' => $email]);
    //         if ($auth && ($auth->getUser()->getFailedLoginCount() ?? 0) >= 3) {
    //             $showCaptcha = true;
    //         }
    //     }

    //     return $this->render('auth/login.html.twig', [
    //         'show_captcha'      => $showCaptcha,
    //         'recaptcha_site_key'=> $_ENV['RECAPTCHA_SITE_KEY'],
    //         'last_email'        => $email,
    //     ]);
    // }

    // #[Route('/login', name: 'login', methods: ['POST'])]
    // public function login(
    //     Request $request,
    //     AuthRepository $authRepository,
    //     UserPasswordHasherInterface $passwordHasher,
    //     JwtService $jwtService,
    //     EmailService $emailService,
    //     EntityManagerInterface $em,
    // HttpClientInterface $httpClient   
    // ): Response {
    //     $email = $request->request->get('email');
    //     $password = $request->request->get('password');

    //     // Step 1: Validate credentials
    //     $auth = $authRepository->findOneBy(['email' => $email]);
    //     $needsCaptcha  = $auth && ($auth->getUser()->getFailedLoginCount() ?? 0) >= 3;

    //     if ($needsCaptcha) {
    //         $recaptchaResponse = $request->request->get('g-recaptcha-response', '');
    //         $resp = $httpClient->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
    //             'body' => [
    //                 'secret'   => $_ENV['RECAPTCHA_SECRET_KEY'],
    //                 'response' => $recaptchaResponse,
    //                 'remoteip' => $request->server->get('REMOTE_ADDR'),
    //             ],
    //         ]);
    //         $data = $resp->toArray();
    //         if (empty($data['success'])) {
    //             $this->addFlash('error', 'Please complete the CAPTCHA.');
    //             // store the last email in session
    //             $request->getSession()->set('last_login_email', $email);

    //             return $this->redirectToRoute('auth_login_form');            
    //         }
    //     }
    
    //     // Check if account is locked
    //     if ($auth && $auth->getUser()->getAccountStatus() === 'locked') {
    //         $this->addFlash('error', 'Account is locked. Please contact support.');
    //         return $this->redirectToRoute('auth_login_form'); // Or your login form route name
    //     }
        
    //     if (!$auth || !$passwordHasher->isPasswordValid($auth, $password)) {
    //         // Increment failed_login_count if user exists
    //         if ($auth) {
    //             $user = $auth->getUser();
    //             $current = $user->getFailedLoginCount() ?? 0;
    //             $user->setFailedLoginCount($current + 1);

    //             // Lock the account if attempts exceed 10
    //             if ($current + 1 >= 1000) {
    //                 $user->setAccountStatus('locked');
    //                 $user->setLockedAt(new \DateTime());
    //             }
    //             $em->persist($user);
    //             $em->flush();
    //         }
    //         $this->addFlash('error','Invalid credentials');
    //         $request->getSession()->set('last_login_email', $email);
    //         return $this->redirectToRoute('auth_login_form');
    //     }

    //     # If credentials are valid, reset failed_login_count
    //     $user = $auth->getUser();
    //     $user->setFailedLoginCount(0);
    //     $user->setLastLoginAt(new \DateTime());
    //     $user->setAccountStatus('active');

    //     // If 2FA is enabled, delay JWT until OTP is verified
    //     if ($user->isOtpEnabled()) {
    //         $otp = random_int(100000, 999999);
    //         $user->setOtpCode((string) $otp);
    //         $user->setOtpExpiresAt(new \DateTimeImmutable('+5 minutes'));

    //         $em->persist($user);
    //         $em->flush();

    //         // Send OTP email using sendOtp in EmailService
    //         $emailService->sendOtp($auth->getEmail(), $user->getName(), $otp);

    //         // Store user in session for later OTP verification
    //         $request->getSession()->set('pending_2fa_user_id', $user->getId());

    //         return $this->redirectToRoute('auth_verify_otp_form');
    //     }

    //     // If 2FA is not enabled, issue JWT immediately
    //     // Step 2: Generate JWT
    //     $token = $jwtService->createToken([
    //         'id' => $auth->getUser()->getId(),
    //         'email' => $auth->getEmail()
    //     ]);
    //     $decodedPayload = $jwtService->verifyToken($token); // decode to get iat
    //     $issuedAt = (new \DateTime())->setTimestamp($decodedPayload['iat']);
    //     $expiresAt = (new \DateTime())->setTimestamp($decodedPayload['exp']);

    //     // Step 3: Track JWT in database
    //     $jwtEntity = new JWTSession();
    //     $jwtEntity->setUser($auth->getUser());
    //     $jwtEntity->setExpiresAt($expiresAt);
    //     $jwtEntity->setIssuedAt($issuedAt);  // New Field added

    //     $em->persist($jwtEntity);
    //     $em->flush();

    //     // Step 4: Set JWT as HttpOnly cookie
    //     $cookie = Cookie::create('JWT')
    //         ->withValue($token)
    //         ->withExpires($expiresAt)
    //         ->withHttpOnly(true)
    //         ->withSecure(false)
    //         ->withPath('/')
    //         ->withSameSite('Lax');

    //     $response = new RedirectResponse($this->generateUrl('auth_login_success')); // or another route
    //     $response->headers->setCookie($cookie);
    //     return $response;
    // }

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
    
    // #[Route('/forgot_pwd', name: 'forgot_password_form', methods: ['GET'])]
    // public function showForgotPasswordForm(Request $request): Response
    // {
    //     $user = $request->attributes->get('jwt_user');

    //     if ($user) {
    //         // Redirect authenticated users to their user profile page
    //         return $this->redirectToRoute('user_profile');
    //     }
    //     $user = $request->attributes->get('jwt_user');

    //     if ($user) {
    //         // Redirect authenticated users to their user profile page
    //         return $this->redirectToRoute('user_profile');
    //     }
    //     return $this->render('auth/forgot_pwd.html.twig');
    // }
    
    // #[Route('/forgot_pwd', name: 'forgot_password', methods: ['POST'])]
    // public function forgotPassword(
    //     Request $request,
    //     AuthRepository $authRepository,
    //     EntityManagerInterface $em,
    //     EmailService $emailService
    // ): Response {
    //     $email = $request->request->get('email');
    //     $auth = $authRepository->findOneBy(['email' => $email]);
    //     $user = $auth?->getUser();

    //     if ($user) {
    //         $token = Uuid::v4()->toRfc4122(); // Generate secure unique token
    //         $expiresAt = new \DateTimeImmutable('+15 minutes');

    //         $user->setResetToken($token);
    //         $user->setResetTokenExpiresAt($expiresAt);
    //         $em->flush();

    //         // Send email with token link
    //         $emailService->sendResetPasswordLink($auth->getEmail(), $user->getName(), $token);
    //     }

    //     // Flash message (same response for both cases to protect privacy)
    //     $this->addFlash('success', 'If this email is registered, a reset link has been sent.');

    //     return $this->redirectToRoute('auth_forgot_password_form');
    // }

       #[Route('/forgot_pwd', name: 'forgot_password_form', methods: ['GET'])]
    public function showForgotPasswordForm(
        Request $request,
        CaptchaRepository $captchaRepo
    ): Response {
        // if user already logged in, bounce them back
        if ($request->attributes->get('jwt_user')) {
            return $this->redirectToRoute('user_profile');
        }

        $ip          = $request->getClientIp();
        $fingerprint = substr(sha1((string)$request->headers->get('User-Agent')), 0, 32);

        $attempt = $captchaRepo->findOneBy([
            'ipAddress'         => $ip,
            'deviceFingerprint' => $fingerprint,
        ]) ?? new Captcha($ip, $fingerprint);

        $showCaptcha = $attempt->getAttemptCount() >= 3;

        return $this->render('auth/forgot_pwd.html.twig', [
            'show_captcha'       => $showCaptcha,
            'recaptcha_site_key' => $_ENV['RECAPTCHA_SITE_KEY'],
        ]);
    }

    #[Route('/forgot_pwd', name: 'forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        AuthRepository $authRepository,
        EntityManagerInterface $em,
        EmailService $emailService,
        HttpClientInterface $httpClient,
        CaptchaRepository $captchaRepo
    ): Response {
        // step 1: fetch or create our CAPTCHA tracker
        $ip          = $request->getClientIp();
        $fingerprint = substr(sha1((string)$request->headers->get('User-Agent')), 0, 32);

        $attempt = $captchaRepo->findOneBy([
            'ipAddress'         => $ip,
            'deviceFingerprint' => $fingerprint,
        ]) ?? new Captcha($ip, $fingerprint);

        $now        = new \DateTimeImmutable();
        $oneHourAgo = $now->sub(new \DateInterval('PT1H'));

        // step 2: reset count if last attempt > 1h ago
        if ($attempt->getLastAttemptAt() < $oneHourAgo) {
            $attempt->reset();
        }

        // step 3: if ≥ 3 attempts in last hour, enforce reCAPTCHA
        if ($attempt->getAttemptCount() >= 3) {
            $resp = $httpClient->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
                'body' => [
                    'secret'   => $_ENV['RECAPTCHA_SECRET_KEY'],
                    'response' => $request->request->get('g-recaptcha-response', ''),
                    'remoteip' => $ip,
                ],
            ]);

            if (empty($resp->toArray()['success'])) {
                $attempt->incrementAttemptCount();
                $em->persist($attempt);
                $em->flush();

                $this->addFlash('error', 'Please complete the CAPTCHA.');
                return $this->redirectToRoute('auth_forgot_password_form');
            }
        }

        // step 4: count this POST (whether valid or not)
        $attempt->incrementAttemptCount();

        // look up the user by email
        $email = $request->request->get('email', '');
        $auth  = $authRepository->findOneBy(['email' => $email]);
        $user  = $auth?->getUser();

        if ($user) {
            // genuine user → generate token and send reset link
            $token     = Uuid::v4()->toRfc4122();
            $expiresAt = $now->add(new \DateInterval('PT15M'));

            $user->setResetToken($token)
                 ->setResetTokenExpiresAt($expiresAt);

            $em->flush();

            $emailService->sendResetPasswordLink(
                $auth->getEmail(),
                $user->getName(),
                $token
            );
        }

        // step 5: persist updated attempt counter
        $em->persist($attempt);
        $em->flush();

        // step 6: privacy‐preserving flash
        $this->addFlash('success', 'If this email is registered, a reset link has been sent.');
        return $this->redirectToRoute('auth_forgot_password_form');
    }

        #[Route('/verify-otp', name: 'verify_otp_form', methods: ['GET'])]
    public function showOtpForm(Request $request): Response
    {
        $session = $request->getSession();
        $isLoginOtp = $session->has('pending_2fa_user_id') && !$session->has('pending_2fa_toggle_state');

        return $this->render('auth/verify_otp.html.twig', [
            'isLoginOtp' => $isLoginOtp
        ]);
    }

    #[Route('/verify-otp', name: 'verify_otp', methods: ['POST'])]
    public function verifyOtp(
        Request $request,
        EntityManagerInterface $em,
        JwtService $jwtService
    ): Response {
        $session = $request->getSession();
        $submittedOtp = $request->request->get('otp');

        // 1. Handle 2FA toggle confirmation
        $pendingToggle = $session->get('pending_2fa_toggle_state');
        $jwtUser = $request->attributes->get('jwt_user');

        if ($pendingToggle !== null && $jwtUser !== null) {
            if (
                $jwtUser->getOtpCode() !== $submittedOtp ||
                $jwtUser->getOtpExpiresAt() < new \DateTimeImmutable()
            ) {
                $this->addFlash('error', 'Invalid or expired OTP code.');
                return $this->redirectToRoute('auth_verify_otp_form');
            }

            // Apply the toggle for 2FA setting
            $jwtUser->setOtpEnabled($pendingToggle);
            $jwtUser->setOtpCode(null);
            $jwtUser->setOtpExpiresAt(null);
            $em->flush();

            $session->remove('pending_2fa_toggle_state');

            $this->addFlash('success', '2FA settings updated successfully.');
            return $this->redirectToRoute('user_profile');
        }

        // 2. Handle login-based 2FA
        $userId = $session->get('pending_2fa_user_id');
        if ($userId !== null) {
            $user = $em->getRepository(\App\Entity\User::class)->find($userId);

            if (
                !$user ||
                $user->getOtpCode() !== $submittedOtp ||
                $user->getOtpExpiresAt() < new \DateTimeImmutable()
            ) {
                $this->addFlash('error', 'Invalid or expired OTP code.');
                return $this->redirectToRoute('auth_verify_otp_form');
            }

            // OTP valid – clear OTP fields
            $user->setOtpCode(null);
            $user->setOtpExpiresAt(null);
            $user->setLastLoginAt(new \DateTime());
            $user->setAccountStatus('active');
            $em->flush();

            $auth = $user->getAuth();

            // Generate JWT
            $token = $jwtService->createToken([
                'id' => $user->getId(),
                'email' => $auth->getEmail()
            ]);
            $decodedPayload = $jwtService->verifyToken($token);
            $issuedAt = (new \DateTime())->setTimestamp($decodedPayload['iat']);
            $expiresAt = (new \DateTime())->setTimestamp($decodedPayload['exp']);

            // Save JWT session
            $jwtEntity = new JWTSession();
            $jwtEntity->setUser($user);
            $jwtEntity->setIssuedAt($issuedAt);
            $jwtEntity->setExpiresAt($expiresAt);
            $em->persist($jwtEntity);
            $em->flush();

            // Set cookie and redirect
            $cookie = Cookie::create('JWT')
                ->withValue($token)
                ->withExpires($expiresAt)
                ->withHttpOnly(true)
                ->withSecure(false)
                ->withPath('/')
                ->withSameSite('Lax');

            $session->remove('pending_2fa_user_id');

            $response = new RedirectResponse(
                $user->getRole() === 'ROLE_ADMIN' ? 
                    $this->generateUrl('admin_dashboard') :
                    $this->generateUrl('user_profile')
            );

            $response->headers->setCookie($cookie);
            return $response;
        }

        // 3. If no known 2FA flow
        $this->addFlash('error', 'Unexpected 2FA context. Please log in again.');
        return $this->redirectToRoute('auth_login_form');
    }

    #[Route('/resend-otp', name: 'resend_otp', methods: ['POST'])]
    public function resendOtp(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        EmailService $emailService
    ): Response {
        $session = $request->getSession();
        $userId = $session->get('pending_2fa_user_id');

        if (!$userId) {
            $this->addFlash('error', 'Session expired. Please log in again.');
            return $this->redirectToRoute('auth_login_form');
        }

        $user = $userRepository->find($userId);

        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('auth_login_form');
        }

        $otp = random_int(100000, 999999);
        $user->setOtpCode((string) $otp);
        $user->setOtpExpiresAt(new \DateTimeImmutable('+5 minutes'));

        $em->flush();

        // Send OTP again
        $email = $user->getAuth()->getEmail();
        $emailService->sendOtp($email, $user->getName(), $otp);

        $this->addFlash('success', 'A new OTP has been sent to your email.');
        return $this->redirectToRoute('auth_verify_otp_form');
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