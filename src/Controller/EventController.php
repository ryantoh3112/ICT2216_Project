<?php

namespace App\Controller;

use App\Repository\EventRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/events', name: 'event_')]
final class EventController extends AbstractController
{
    #[Route('', name: 'list')]
    public function index(Request $request, EventRepository $eventRepository): Response
    {
        //get current page number
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 9;
        $offset = ($page-1) * $limit;

        $totalEvents = count($eventRepository->findAll());

        $events = $eventRepository->findBy([], null,$limit, $offset);
        
        //calculate total pages
        $totalPages = ceil($totalEvents/$limit);

        return $this->render('event/events.html.twig', [
            'events' => $events,
            'currentPage' => $page,
            'totalPages' => $totalPages
        ]);
    }

    #Route for individual events
    #[Route('/{id}', name: 'show', requirements:['id' => '\d+'])]
    public function show(int $id, EventRepository $eventRepository): Response
    {
        $event = $eventRepository->find($id);

        if(!$event){
            throw $this->createNotFoundException('Event not found.');
        }

        return $this->render('event/show.html.twig', [
            'event' => $event,
        ]);
    }

}
