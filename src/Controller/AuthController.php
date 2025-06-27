<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Entity\Auth;
use App\Repository\UserRepository;
use App\Repository\AuthRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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
        return $this->redirectToRoute('auth_login');
        }

        // Render the registration page (GET)
        return $this->render('auth/register.html.twig', [
            'controller_name' => 'AuthController',
        ]);
    }

    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        AuthRepository $authRepository,
        UserPasswordHasherInterface $passwordHasher
    ): Response
    {
        if ($request->isMethod('POST')) {
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $role = $request->request->get('role');

        $auth = $authRepository->findOneBy(['email' => $email]);

        if (!$auth) {
            $this->addFlash('error', 'Invalid email or password.');
            return $this->redirectToRoute('auth_login');
        }

        if (!$passwordHasher->isPasswordValid($auth, $password)) {
            $this->addFlash('error', 'Invalid email or password.');
            return $this->redirectToRoute('auth_login');
        }

        // Set session data (you can customize this)
        $user = $auth->getUser();
        $session = $request->getSession();
        $session->set('user_id', $user->getId());
        $session->set('user_name', $user->getName());
        $session->set('user_role', $user->getRole());

        if ($user->getRole() === 'ROLE_USER') {
            $this->addFlash('success', 'Logged in successfully!');
            return $this->redirectToRoute('app_home');
        } elseif($user->getRole() === 'ROLE_ADMIN') {
            $this->addFlash('success', 'Logged in successfully!');
            return $this->redirectToRoute('admin_dashboard');
        }
        
        }

        return $this->render('auth/login.html.twig', [
            'controller_name' => 'AuthController',
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(Request $request): Response
    {
        $request->getSession()->clear();
        $this->addFlash('success', 'You have been logged out.');
        return $this->redirectToRoute('auth_login');
    }
}