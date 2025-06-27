<?php

namespace App\Controller;

use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/events', name: 'event_')]
final class EventController extends AbstractController
{
    #[Route('', name: 'list')]
    public function index(EventRepository $eventRepository): Response
    {
        $events = $eventRepository->findAll();  

        return $this->render('event/events.html.twig', [
            'events' => $events,
        ]);
    }
}
