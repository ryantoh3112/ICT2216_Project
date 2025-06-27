<?php

namespace App\Controller;

// use App\Entity\Admin;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UserRepository;
use App\Repository\AuthRepository;
use Symfony\Component\HttpFoundation\Request;

#[Route('/admin', name: 'admin_')]
final class AdminController extends AbstractController
{
    // route to admin page for development!
    #[Route('/dashboard', name: 'dashboard')]
    public function admin(
        Request $request, 
        AuthRepository $authRepository
    ): Response
    {
        $session = $request->getSession();

        if (!$session->has('user_id')) {
            $this->addFlash('error', 'Please log in to view this page.');
            return $this->redirectToRoute('auth_login');
        }

        // Get user ID from session
        $userId = $session->get('user_id');
        $auth = $authRepository->findOneBy(['user' => $userId]);

        return $this->render('admin/admin.html.twig', [
            'user' => $auth->getUser(),
            'email' => $auth->getEmail(),
            'controller_name' => 'IndexController',
        ]);
    }

    #[Route('/manage_events', name: 'manage_events')]
    public function manage_events(
        Request $request, 
        AuthRepository $authRepository
    ): Response
    {
        $session = $request->getSession();

        if (!$session->has('user_id')) {
            $this->addFlash('error', 'Please log in to view this page.');
            return $this->redirectToRoute('auth_login');
        }

        // Get user ID from session
        $userId = $session->get('user_id');
        $auth = $authRepository->findOneBy(['user' => $userId]);

        return $this->render('admin/manage_events.html.twig', [
            'controller_name' => 'IndexController',
        ]);
    }

    #[Route('/manage_users', name: 'manage_users')]
    public function manage_users(
        EntityManagerInterface $entityManager,
        Request $request, 
        AuthRepository $authRepository
        ): Response
    {
        $session = $request->getSession();

        if (!$session->has('user_id')) {
            $this->addFlash('error', 'Please log in to view this page.');
            return $this->redirectToRoute('auth_login');
        }

        // Get user ID from session
        $userId = $session->get('user_id');
        $auth = $authRepository->findOneBy(['user' => $userId]);

        // Fetch all users
        $users = $entityManager->getRepository(User::class)->findAll();

        return $this->render('admin/manage_users.html.twig', [
            'users' => $users,
        ]);
    }
}