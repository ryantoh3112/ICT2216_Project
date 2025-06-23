<?php

namespace App\Controller;

// use App\Entity\Admin;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    // route to admin page for development!
    #[Route('/admin', name: 'admin_page')]
    public function admin(): Response
    {
        return $this->render('admin/admin.html.twig', [
            'controller_name' => 'IndexController',
        ]);
    }

    #[Route('/admin/manage_events', name: 'manage_events_page')]
    public function manage_events(): Response
    {
        return $this->render('admin/manage_events.html.twig', [
            'controller_name' => 'IndexController',
        ]);
    }

    #[Route('/admin/manage_users', name: 'manage_users_page')]
    public function manage_users(EntityManagerInterface $entityManager): Response
    {
        // Fetch all users
        $users = $entityManager->getRepository(User::class)->findAll();

        return $this->render('admin/manage_users.html.twig', [
            'users' => $users,
        ]);
    }
}