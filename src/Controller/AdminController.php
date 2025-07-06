<?php

namespace App\Controller;

// use App\Entity\Admin;
use App\Entity\User;
use App\Entity\Event;
use App\Entity\Venue;
use App\Entity\EventCategory;
use App\Entity\Ticket;
use App\Entity\TicketType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UserRepository;
use App\Repository\EventRepository;
use App\Repository\VenueRepository;
use App\Repository\EventCategoryRepository;
use App\Repository\TicketTypeRepository;
use App\Repository\AuthRepository;
use Symfony\Component\HttpFoundation\Request;

#[Route('/admin', name: 'admin_')]
final class AdminController extends AbstractController
{
    // Function to check if user Logged in AND Admin
    private function getAuthenticatedAdmin(Request $request, AuthRepository $authRepository): Response|User
    {
        $user = $request->attributes->get('jwt_user');

        if (!$user) {
            $this->addFlash('error', 'Please log in to view this page.');
            return $this->redirectToRoute('auth_login');
        }

        if ($user->getRole() !== 'ROLE_ADMIN') {
            $this->addFlash('error', 'Access denied. Admins only.');
            return $this->redirectToRoute('app_home'); 
        }

        return $user;
    }

    // Route to admin home page 
    #[Route('/dashboard', name: 'dashboard')]
    public function admin(
        Request $request, 
        AuthRepository $authRepository
    ): Response
    {
        //check if admin
        $result = $this->getAuthenticatedAdmin($request, $authRepository);
        if ($result instanceof Response) {
            return $result;
        }
        $user = $result; // it's a valid User

        // $auth = $authRepository->findOneBy(['user' => $userId]);

        $auth = $authRepository->findOneBy(['user' => $user]);

        return $this->render('admin/admin.html.twig');
    }

    // Page to manage all events 
    #[Route('/manage_events', name: 'manage_events')]
    public function manage_events(
        EntityManagerInterface $entityManager,
        Request $request, 
        AuthRepository $authRepository,
        VenueRepository $venueRepo,
        EventCategoryRepository $categoryRepo,
        TicketTypeRepository $ticketTypeRepo,
    ): Response
    {
        //check if admin
        $result = $this->getAuthenticatedAdmin($request, $authRepository);
        if ($result instanceof Response) {
            return $result;
        }
        $user = $result; // it's a valid User

        $auth = $authRepository->findOneBy(['user' => $user]);

        // Fetch all events 
        $events = $entityManager->getRepository(Event::class)->findAll();
        $venues = $venueRepo->findAll();
        $categories = $categoryRepo->findAll();
        $ticketTypes = $ticketTypeRepo->findAll();

        return $this->render('admin/manage_events.html.twig',[
            'events' => $events,
            'venues' => $venues,
            'categories' => $categories,
            'ticketTypes' => $ticketTypes,
        ]);
    }

    // Functionality for edit event button
    #[Route('/admin/manage_event/update/{id}', name: 'update_event', methods: ['POST'])]
    public function updateEvent(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        EventRepository $eventRepo,
        VenueRepository $venueRepo,
        EventCategoryRepository $categoryRepo,
        TicketTypeRepository $ticketTypeRepo
    ): Response {
        $event = $eventRepo->find($id);

        if (!$event) {
            $this->addFlash('error', 'Event not found.');
            return $this->redirectToRoute('admin_manage_events');
        }

        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('update_event_' . $id, $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_manage_events');
        }

        // Ticket sales date validation
        $startDate = new \DateTime($request->request->get('purchase_start_date'));
        $endDate = new \DateTime($request->request->get('purchase_end_date'));
        if ($startDate >= $endDate) {
            $this->addFlash('error', 'Purchase start date must be before the end date.');
            return $this->redirectToRoute('admin_manage_events');
        }   

        // Venue and category validation
        $venue = $venueRepo->find((int) $request->request->get('venue'));
        $category = $categoryRepo->find((int) $request->request->get('category'));

        if (!$venue || !$category) {
            $this->addFlash('error', 'Venue or category not found.');
            return $this->redirectToRoute('admin_manage_events');
        }

        // Venue capacity validation
        $capacity = (int) $request->request->get('capacity');
        if ($capacity <= 0) {
            $this->addFlash('error', 'Capacity must be greater than 0.');
            return $this->redirectToRoute('admin_manage_events');
        }
        if ($capacity > $venue->getCapacity()) {
            $this->addFlash('error', 'Capacity cannot exceed the venue\'s maximum capacity of ' . $venue->getCapacity() . '.');
            return $this->redirectToRoute('admin_manage_events');
        }

        // Update fields
        $event->setName($request->request->get('name'));
        $event->setDescription($request->request->get('description'));
        $event->setCapacity((int) $request->request->get('capacity'));
        $event->setPurchaseStartDate($startDate);
        $event->setPurchaseEndDate($endDate);
        $event->setOrganiser($request->request->get('organiser'));
        $event->setVenue($venue);
        $event->setCategory($category);


        // $event->setImagePath($request->request->get('imagepath'));
         //get img file from imagefile input
        $imagefile = $request->files->get('imagefile');
        if ($imagefile && $imagefile->isValid()) {
            $allowedImgTypes = ['image/jpg', 'image/jpeg', 'image/png'];
            // if user uploads invalid file type for img, redirect them back to manage events page with error message
            if(!in_array($imagefile->getMimeType(), $allowedImgTypes)) {
                $this->addFlash('error', 'Invalid image file type. Only JPG, JPEG, and PNG are allowed.');
                return $this->redirectToRoute('admin_manage_events');
            }
            // ensure real img file is uploaded
            if(!@getimagesize($imagefile->getPathname())) {
                $this->addFlash('error', 'Nice try, but valid image only.');
                return $this->redirectToRoute('admin_manage_events');
            }
            // Generate a unique filename
            $filename = uniqid() . '.' . $imagefile->guessExtension();
            // Move the file to the uploads directory
            $imagefile->move($this->getParameter('uploads_directory'), $filename);
            // Set the image path in the event entity
            $event->setImagePath($filename);
        }

        // adding tickets 
        // Get existing tickets for the event
        $existingTickets = $event->getTicket();
        $existingSeatNumbers = [];

        foreach ($existingTickets as $ticket) {
            $existingSeatNumbers[] = $ticket->getSeatNumber();
        }

        // Find max seat number
        $maxSeatNum = 0;
        foreach ($existingSeatNumbers as $seat) {
            if (preg_match('/S(\d{3})/', $seat, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxSeatNum) {
                    $maxSeatNum = $num;
                }
            }
        }

        $ticketTypes = $ticketTypeRepo->findAll();

        // Now for each new ticket, generate sequential seat numbers
        foreach ($ticketTypes as $ticketType) {
            $inputName = 'ticket_type_' . $ticketType->getId();
            $quantityToAdd = (int) $request->request->get($inputName);

            if ($quantityToAdd > 0) {
                for ($i = 1; $i <= $quantityToAdd; $i++) {
                    $ticket = new Ticket();
                    $ticket->setEvent($event);
                    $ticket->setTicketType($ticketType);
                    
                    $maxSeatNum++;
                    $seatNumber = 'S' . str_pad($maxSeatNum, 3, '0', STR_PAD_LEFT);
                    $ticket->setSeatNumber($seatNumber);

                    $ticket->setPayment(null); // Optional: leave null if not needed
                    $em->persist($ticket);
                }
            }
        }

        
        $em->flush();

        $this->addFlash('success', 'Event updated successfully.');
        return $this->redirectToRoute('admin_manage_events');
    }

    // Functionality for add event button 
    #[Route('/admin/manage_event/add', name: 'add_event', methods: ['POST'])]
    public function addEvent(
        Request $request,
        EntityManagerInterface $em, 
        VenueRepository $venueRepo,
        EventCategoryRepository $categoryRepo
    ): Response {

        $submittedToken = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('add_event', $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_manage_events');
        }


        // Ticket sales date validation
        $startDate = new \DateTime($request->request->get('purchase_start_date'));
        $endDate = new \DateTime($request->request->get('purchase_end_date'));

        if ($startDate >= $endDate) {
            $this->addFlash('error', 'Purchase start date must be before the end date.');
            return $this->redirectToRoute('admin_manage_events');
        }

        // Venue and category validation
        $venue = $venueRepo->find((int) $request->request->get('venue'));
        $category = $categoryRepo->find((int) $request->request->get('category'));

        if (!$venue || !$category) {
            $this->addFlash('error', 'Venue or category not found.');
            return $this->redirectToRoute('admin_manage_events');
        }

        // Venue capacity validation
        $capacity = (int) $request->request->get('capacity');
        if ($capacity <= 0) {
            $this->addFlash('error', 'Capacity must be greater than 0.');
            return $this->redirectToRoute('admin_manage_events');
        }
        if ($capacity > $venue->getCapacity()) {
            $this->addFlash('error', 'Capacity cannot exceed the venue\'s maximum capacity of ' . $venue->getCapacity() . '.');
            return $this->redirectToRoute('admin_manage_events');
        }

        $event = new Event();
        $event->setName($request->request->get('name'));
        $event->setDescription($request->request->get('description'));
        $event->setCapacity($capacity);
        $event->setOrganiser($request->request->get('organiser'));

        //get img file from imagefile input
        $imagefile = $request->files->get('imagefile');
        if ($imagefile && $imagefile->isValid()) {
            # setting only valid image file type - jpg, jpeg & png
            $allowedImgTypes = ['image/jpg', 'image/jpeg', 'image/png'];

            // if user uploads invalid file type for img, redirect them back to manage events page with error message
            if(!in_array($imagefile->getMimeType(), $allowedImgTypes)) {
                $this->addFlash('error', 'Invalid image file type. Only JPG, JPEG, and PNG are allowed.');
                return $this->redirectToRoute('admin_manage_events');
            }
            // ensure real img file is uploaded
            if(!@getimagesize($imagefile->getPathname())) {
                $this->addFlash('error', 'Nice try, but valid image only.');
                return $this->redirectToRoute('admin_manage_events');
            }
            // Generate a unique filename
            $filename = uniqid() . '.' . $imagefile->guessExtension();
            // Move the file to the uploads directory
            $imagefile->move($this->getParameter('uploads_directory'), $filename);
            // Set the image path in the event entity
            $event->setImagePath($filename);
        } else {
            // If no file is uploaded, set a default or null value
            $event->setImagePath(null);
        }
        //$event->setImagePath($request->request->get('imagepath'));

        $event->setPurchaseStartDate($startDate);
        $event->setPurchaseEndDate($endDate);
        $event->setVenue($venue);
        $event->setCategory($category);
        $em->persist($event);
        $em->flush(); // flush early to get event ID

        // Process ticket types
        $ticketTypes = $request->request->all('ticket_types'); // gets array from form
        $seatCounter = 1;

        foreach ($ticketTypes as $typeData) {
            $name = trim($typeData['name']);
            $description = $typeData['description'];
            $price = (float) $typeData['price'];
            $quantity = (int) $typeData['quantity'];

            if (!$name || $price <= 0 || $quantity <= 0) {
                continue; // skip invalid entries
            }

            // Create new TicketType
            $ticketType = new TicketType();
            $ticketType->setName($name);
            $ticketType->setDescription($description);
            $ticketType->setPrice($price);
            $em->persist($ticketType);
            $em->flush();

            // Create individual Ticket records
            for ($i = 0; $i < $quantity; $i++) {
                $ticket = new Ticket();
                $ticket->setEvent($event);
                $ticket->setTicketType($ticketType);
                $ticket->setSeatNumber("S" . str_pad($seatCounter++, 3, '0', STR_PAD_LEFT));
                $em->persist($ticket);
            }
        }

        $em->flush();

        $this->addFlash('success', 'Event created successfully.');
        return $this->redirectToRoute('admin_manage_events');
    }

    // Functionality for delete event button
    #[Route('/admin/manage_event/delete/{id}', name: 'delete_event', methods: ['POST'])]
    public function deleteEvent(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        EventRepository $eventRepo,
        TicketTypeRepository $ticketTypeRepo
    ): Response {
        $event = $eventRepo->find($id);

        if (!$event) {
            $this->addFlash('error', 'Event not found.');
            return $this->redirectToRoute('admin_manage_events');
        }

        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_event_' . $id, $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_manage_events');
        }

        // First delete all tickets linked to this event
        foreach ($event->getTicket() as $ticket) {
            $em->remove($ticket);
        }
        // Find and delete TicketTypes related to this event
        $ticketTypes = $ticketTypeRepo->findAll();
        foreach ($ticketTypes as $ticketType) {
            $hasTickets = false;
            foreach ($ticketType->getTicket() as $ticket) {
                if ($ticket->getEvent()->getId() === $event->getId()) {
                    $hasTickets = true;
                    break;
                }
            }
            if ($hasTickets) {
                $em->remove($ticketType);
            }
        }

        // Then delete the event
        $em->remove($event);
        $em->flush();

        $this->addFlash('success', 'Event deleted successfully.');
        return $this->redirectToRoute('admin_manage_events');
    }

    
    // Page to manage all user accounts 
    #[Route('/manage_users', name: 'manage_users')]
    public function manage_users(
        EntityManagerInterface $entityManager,
        Request $request, 
        AuthRepository $authRepository
        ): Response
    {
        //check if admin
        $result = $this->getAuthenticatedAdmin($request, $authRepository);
        if ($result instanceof Response) {
            return $result;
        }
        $user = $result; // it's a valid User

        // Fetch all users
        $users = $entityManager->getRepository(User::class)->findAll();

        return $this->render('admin/manage_users.html.twig', [
            'users' => $users,
        ]);
    }

    // Functionality for Edit User button 
    #[Route('/admin/manage_user/update/{id}', name: 'update_user', methods: ['POST'])]
    public function updateUser(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo
    ): Response {
        $submittedToken = $request->request->get('_token');

        if (!$this->isCsrfTokenValid('update_user_' . $id, $submittedToken)) {
            $this->addFlash('danger', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_manage_users');
        }

        $user = $userRepo->find($id);

        if (!$user) {
            throw $this->createNotFoundException('User not found.');
        }

        $newName = trim($request->request->get('name'));
        $newRole = $request->request->get('role');
        $newStatus = $request->request->get('accountStatus');

        // Check if the username field is empty
        if (empty($newName)) {
            $this->addFlash('error', 'Name cannot be empty.');
            return $this->redirectToRoute('admin_manage_users');
        }

        // Check if the new username is already taken by another user
        $existingUser = $userRepo->findOneBy(['name' => $newName]);
        if ($existingUser && $existingUser->getId() !== $user->getId()) {
            $this->addFlash('error', 'This username is already taken. Please choose another one.');
            return $this->redirectToRoute('admin_manage_users');
        }

        $user->setName($newName);
        $user->setRole($newRole);
        $user->setAccountStatus($newStatus);

        $em->flush();

        $this->addFlash('success', 'User updated successfully.');
        return $this->redirectToRoute('admin_manage_users');
    }

    // Functionality for delete user buttion 
    #[Route('/admin/user/delete/{id}', name: 'delete_user', methods: ['POST'])]
    public function deleteUser(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo
    ): Response {
        // Optional: CSRF token check for security
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_user_' . $id, $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_manage_users');
        }

        $user = $userRepo->find($id);
        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('admin_manage_users');
        }

        // Prevent deleting yourself (optional)
        $currentUser = $request->attributes->get('jwt_user');
        if ($currentUser && $currentUser->getId() === $id) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('admin_manage_users');
        }

        $em->remove($user);
        $em->flush();

        $this->addFlash('success', 'User deleted successfully.');
        return $this->redirectToRoute('admin_manage_users');
    }


}