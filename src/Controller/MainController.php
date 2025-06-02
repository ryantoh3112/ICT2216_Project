<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MainController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('index/index.html.twig', [
            'controller_name' => 'IndexController',
        ]);
    }

    #[Route('/events', name: 'events_page')]
    public function event(): Response
    {
        return $this->render('events/events.html.twig', [
            'controller_name' => 'IndexController',
        ]);
    }

    #[Route('/login', name: 'login_page')]
    public function login(): Response
    {
        return $this->render('authentication/login.html.twig', [
            'controller_name' => 'IndexController',
        ]);
    }

    #[Route('/register', name: 'register_page')]
    public function register(): Response
    {
        return $this->render('authentication/register.html.twig', [
            'controller_name' => 'IndexController',
        ]);
    }
}
