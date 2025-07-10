<?php

namespace App\Controller;

use App\Entity\CartItem;
use App\Entity\TicketType;
use App\Entity\Ticket;
use App\Repository\EventRepository;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/events', name: 'event_')]
final class EventController extends AbstractController
{
    // #[Route('', name: 'list')]
    // public function index(Request $request, EventRepository $eventRepository): Response
    // {
    //     //get current page number
    //     $page = max(1, $request->query->getInt('page', 1));
    //     $limit = 9;
    //     $offset = ($page-1) * $limit;

    //     $totalEvents = count($eventRepository->findAll());

    //     $events = $eventRepository->findBy([], null,$limit, $offset);
        
    //     //calculate total pages
    //     $totalPages = ceil($totalEvents/$limit);

    //     return $this->render('event/index.html.twig', [
    //         'events' => $events,
    //         'currentPage' => $page,
    //         'totalPages' => $totalPages
    //     ]);
    // }

    #[Route('', name: 'list')]
    public function index(Request $request, EventRepository $eventRepository): Response
    {
        $page   = max(1, $request->query->getInt('page', 1));
        $limit  = 9;
        $offset = ($page - 1) * $limit;

        $totalEvents = count($eventRepository->findAll());
        $events      = $eventRepository->findBy([], null, $limit, $offset);
        $totalPages  = ceil($totalEvents / $limit);

        //Compute availability for each event
        $soldOut = [];

        foreach($events as $event){
            $availability = 0;
            foreach($event->getTicket() as $ticket){
                if($ticket->getPayment() === null){
                    $availability++;
                }
            }
            $soldOut[$event->getId()] = ($availability === 0);
        }

        return $this->render('event/index.html.twig', [
            'events'      => $events,
            'currentPage' => $page,
            'totalPages'  => $totalPages,
            'soldOut'     => $soldOut,
        ]);
    }

    // #Route for individual events
    // #[Route('/{id}', name: 'show', requirements:['id' => '\d+'])]
    // public function show(int $id, EventRepository $eventRepository): Response
    // {
    //     $event = $eventRepository->find($id);

    //     if(!$event){
    //         throw $this->createNotFoundException('Event not found.');
    //     }

    //     return $this->render('event/show.html.twig', [
    //         'event' => $event,
    //     ]);
    // }

    
    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(int $id, EventRepository $eventRepository): Response
    {
        // 1) Load the event
        $event = $eventRepository->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        // 2) Build unique list of TicketType for this event
        $ticketTypes = [];
        foreach ($event->getTicket() as $ticket) {
            $ticketTypes[$ticket->getTicketType()->getId()] = $ticket->getTicketType();
        }

        return $this->render('event/show.html.twig', [
            'event'       => $event,
            'ticketTypes' => array_values($ticketTypes),
        ]);
    }

    // #Route for puchasing tickets
    // #[Route('/{id}/tickets', name: 'select_ticket', requirements:['id' => '\d+'])]
    // public function selectTickets(Request $request, int $id, EventRepository $eventRepository): Response
    // {
    //     $user = $request->attributes->get('jwt_user');
    //     if (!$user) {
    //         $this->addFlash('error', 'Please log in first.');
    //         $request->getSession()->set('_redirect_after_login', $request->getUri());
    //         return $this->redirectToRoute('auth_login');
    //     }

    //     $event = $eventRepository->find($id);

    //     if(!$event){
    //         throw $this->createNotFoundException('Event not found.');
    //     }

    //     $ticketType = [];
    //     foreach ($event->getTicket() as $ticket) {
    //         $ticketType[] = $ticket->getTicketType();
    //     }
    //     $ticketType = array_unique($ticketType, SORT_REGULAR);

    //     return $this->render('event/select_ticket.html.twig', [
    //         'event' => $event,
    //         'ticketType' => $ticketType,
    //         'user' => $user,
    //     ]);
    // }

//    #[Route(
//         '/{id}/tickets',
//         name: 'select_ticket',
//         requirements: ['id' => '\d+'],
//         methods: ['GET','POST']
//     )]
//     public function selectTickets(
//         Request $request,
//         int $id,
//         EventRepository $eventRepository,
//         EntityManagerInterface $em
//     ): Response {
//         // 1) Ensure user is logged in
//         $user = $request->attributes->get('jwt_user');
//         if (!$user) {
//             $this->addFlash('error', 'Please log in first.');
//             $request->getSession()->set('_redirect_after_login', $request->getUri());
//             return $this->redirectToRoute('auth_login');
//         }

//         // 2) Load the event
//         $event = $eventRepository->find($id);
//         if (!$event) {
//             throw $this->createNotFoundException('Event not found.');
//         }

//         // 3) Build unique list of TicketType
//         $ticketTypes = [];
//         foreach ($event->getTicket() as $ticket) {
//             $ticketTypes[$ticket->getTicketType()->getId()] = $ticket->getTicketType();
//         }

//         // 4) Compute availability for each type
//         $availability = [];
//         foreach ($ticketTypes as $typeId => $tt) {
//             $availability[$typeId] = $em
//                 ->getRepository(Ticket::class)
//                 ->count([
//                     'event'      => $event,
//                     'ticketType' => $tt,
//                     'payment'    => null,
//                 ]);
//         }

//         // 5) Handle POST form submission
//         if ($request->isMethod('POST')) {
//             // CSRF check
//             $token = $request->request->get('_csrf_token', '');
//             if (!$this->isCsrfTokenValid('select_tickets', $token)) {
//                 throw new AccessDeniedException('Invalid CSRF token.');
//             }
//             $all        = $request->request->all();
//             $quantities = [];
//             if (isset($all['quantities']) && is_array($all['quantities'])) {
//                 $quantities = $all['quantities'];
//             }

//             foreach ($quantities as $typeId => $qty) {
//                 $qty = (int) $qty;
//                 if ($qty < 1) {
//                     continue;
//                 }
//                 // don't allow selecting more than what's available
//                 if (!isset($availability[$typeId]) || $qty > $availability[$typeId]) {
//                     continue;
//                 }
//                 /** @var TicketType|null $tt */
//                 $tt = $em->getRepository(TicketType::class)->find($typeId);
//                 if (!$tt) {
//                     continue;
//                 }

//                 $item = new CartItem();
//                 $item->setName($tt->getName());
//                 $item->setPrice($tt->getPrice());
//                 $item->setQuantity($qty);
//                 $item->setUser($user);

//                 $em->persist($item);
//             }

//             $em->flush();

//             return new RedirectResponse($this->generateUrl('checkout_page'));
//         }

//         // 6) Render the ticket-selection form
//         return $this->render('event/select_ticket.html.twig', [
//             'event'        => $event,
//             'ticketTypes'  => array_values($ticketTypes),
//             'availability' => $availability,
//         ]);
//     }
    #[Route(
        '/{id}/tickets',
        name: 'select_ticket',
        requirements: ['id' => '\d+'],
        methods: ['GET','POST']
    )]
    public function selectTickets(
        Request $request,
        int $id,
        EventRepository $eventRepository,
        CartItemRepository $cartRepo,
        EntityManagerInterface $em
    ): Response {
        // 1) Ensure user is logged in
        $user = $request->attributes->get('jwt_user');
        if (!$user) {
            $this->addFlash('error', 'Please log in first.');
            $request->getSession()->set('_redirect_after_login', $request->getUri());
            return $this->redirectToRoute('auth_login');
        }

        // 2) Load the event
        $event = $eventRepository->find($id);
        if (!$event) {
            throw $this->createNotFoundException('Event not found.');
        }

        // ── NEW: if they already had a different event in session, clear their cart ──
        $session   = $request->getSession();
        $oldEvent  = $session->get('event_id');
        if ($oldEvent && $oldEvent !== $id) {
            $oldItems = $cartRepo->findBy(['user' => $user]);
            foreach ($oldItems as $ci) {
                $em->remove($ci);
            }
            $em->flush();
        }

        // ── bind the session to THIS event ──
        $session->set('event_id', $id);

        // 3) Build unique list of TicketType
        $ticketTypes = [];
        foreach ($event->getTicket() as $t) {
            $ticketTypes[$t->getTicketType()->getId()] = $t->getTicketType();
        }

        // 4) Compute raw availability for each type
        $rawAvailability = [];
        foreach ($ticketTypes as $tid => $tt) {
            $rawAvailability[$tid] = $em->getRepository(Ticket::class)
                ->count([
                    'event'      => $event,
                    'ticketType' => $tt,
                    'payment'    => null,
                ]);
        }

        // 5) Count how many of each type the user already has in their cart
        $inCartCounts = [];
        $inCart = $cartRepo->findBy(['user' => $user]);
        foreach ($inCart as $ci) {
            $tid = $ci->getTicketType()->getId();
            $inCartCounts[$tid] = ($inCartCounts[$tid] ?? 0) + $ci->getQuantity();
        }

        // 6) Final availability = raw − in-cart, clamped ≥ 0
        $availability = [];
        foreach ($rawAvailability as $tid => $avail) {
            $availability[$tid] = max(0, $avail - ($inCartCounts[$tid] ?? 0));
        }

        // 7) If POST, process form
  if ($request->isMethod('POST')) {
        // 1) CSRF
        if (! $this->isCsrfTokenValid('select_tickets', $request->request->get('_csrf_token'))) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }

        // 2) Instead of $request->request->get('quantities', []), do:
        $post = $request->request->all();                // grab all POST data
        $quantities = [];
        if (isset($post['quantities']) && is_array($post['quantities'])) {
            $quantities = $post['quantities'];
        }

        // 3) Now loop just like before:
        foreach ($ticketTypes as $tt) {
            $tid = $tt->getId();
            $q   = (int) ($quantities[$tid] ?? 0);
            if ($q < 1) {
                continue;
            }
            $toAdd = min($q, $availability[$tid] ?? 0);
            if ($toAdd < 1) {
                continue;
            }

            $item = new CartItem();
            $item->setUser($user);
            $item->setTicketType($tt);
            $item->setName($tt->getName());
            $item->setPrice($tt->getPrice());
            $item->setQuantity($toAdd);

            $em->persist($item);
        }

        $em->flush();
        return new RedirectResponse($this->generateUrl('checkout_page'));
        }

        // 8) Render
        return $this->render('event/select_ticket.html.twig', [
            'event'        => $event,
            'ticketTypes'  => array_values($ticketTypes),
            'availability' => $availability,
        ]);
    }
}
