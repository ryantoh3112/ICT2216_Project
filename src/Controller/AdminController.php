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
use Symfony\Component\HttpFoundation\UploadedFile;

# for input validation
// use Symfony\Component\Validator\Validator\ValidatorInterface;

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
    #[Route('/manage_event/update/{id}', name: 'update_event', methods: ['POST'])]
    public function updateEvent(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        EventRepository $eventRepo,
        VenueRepository $venueRepo,
        EventCategoryRepository $categoryRepo,
        TicketTypeRepository $ticketTypeRepo
    ): Response {
        // 1) Check if event exists 
        $event = $eventRepo->find($id);
        if (!$event) {
            $this->addFlash('error', 'Event not found.');
            return $this->redirectToRoute('admin_manage_events');
        }

        // 2) CSRF
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('update_event_' . $id, $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_manage_events');
        }

        // 2) Basic field validation 
        $name = strip_tags(trim($request->request->get('name')));
        $description = strip_tags(trim($request->request->get('description')));
        $organiser = strip_tags(trim($request->request->get('organiser')));
        $rawEventDate = $request->request->get('event_date');
        $purchaseStartDateRaw = $request->request->get('purchase_start_date');
        $purchaseEndDateRaw = $request->request->get('purchase_end_date');
        $venue = $venueRepo->find((int) $request->request->get('venue'));
        $category = $categoryRepo->find((int) $request->request->get('category'));
        $capacity = (int) $request->request->get('capacity');

        if (!$name) {
            $this->addFlash('error', 'Event name is required.');
            return $this->redirectToRoute('admin_manage_events');
        }

        if (!$description) {
            $this->addFlash('error', 'Event description is required.');
            return $this->redirectToRoute('admin_manage_events');
        }

        if (!$organiser) {
            $this->addFlash('error', 'Organiser is required.');
            return $this->redirectToRoute('admin_manage_events');
        }
        
        if (!$rawEventDate) {
            $this->addFlash('error', 'Organiser is required.');
            return $this->redirectToRoute('admin_manage_events');
        }

        if (!$purchaseStartDateRaw || !$purchaseEndDateRaw ) {
            $this->addFlash('error', 'Purchase start and end dates are required.');
            return $this->redirectToRoute('admin_manage_events');
        }

        // Venue and category validation
        if (!$venue || !$category) {
            $this->addFlash('error', 'Venue or category not found.');
            return $this->redirectToRoute('admin_manage_events');
        }

        try {
            $startDate = new \DateTime($purchaseStartDateRaw);
            $endDate = new \DateTime($purchaseEndDateRaw);
            $eventDateTime = new \DateTime($rawEventDate);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Invalid date format.');
            return $this->redirectToRoute('admin_manage_events');
        }

        // Ticket sales date validation
        if ($startDate >= $endDate) {
            $this->addFlash('error', 'Purchase start date must be before the end date.');
            return $this->redirectToRoute('admin_manage_events');
        }   
        if ($eventDateTime < $endDate) {
            $this->addFlash('error', 'Purchase end cannot be after event date');
            return $this->redirectToRoute('admin_manage_events');
        }

        // Venue capacity validation
        if ($capacity < 1 || $capacity > $venue->getCapacity()) {
            $this->addFlash('error', 'Capacity cannot exceed the venue\'s maximum capacity of '. $venue->getCapacity() .'.');
            return $this->redirectToRoute('admin_manage_events');
        }

        // 3) Update fields
        $event->setName($name);
        $event->setDescription($description);
        $event->setOrganiser($organiser);
        $event->setEventDate($eventDateTime);
        $event->setVenue($venue);
        $event->setCategory($category);
        $event->setCapacity($capacity);
        $event->setPurchaseStartDate($startDate);
        $event->setPurchaseEndDate($endDate);

        // // Handle image file upload
        // $imagefile = $request->files->get('imagefile');
        // if ($imagefile && $imagefile->isValid()) {
        //     $allowedImgTypes = ['image/jpg', 'image/jpeg', 'image/png'];
        //     // if user uploads invalid file type for img, redirect them back to manage events page with error message
        //     if(!in_array($imagefile->getMimeType(), $allowedImgTypes)) {
        //         $this->addFlash('error', 'Invalid image file type. Only JPG, JPEG, and PNG are allowed.');
        //         return $this->redirectToRoute('admin_manage_events');
        //     }
        //     // ensure real img file is uploaded
        //     if(!@getimagesize($imagefile->getPathname())) {
        //         $this->addFlash('error', 'Nice try, but valid image only.');
        //         return $this->redirectToRoute('admin_manage_events');
        //     }
            // // Generate a unique filename
            // $filename = uniqid() . '.' . $imagefile->guessExtension();
            // // Move the file to the uploads directory
            // $imagefile->move($this->getParameter(name: 'event_images_directory'), $filename);
            // // Set the image path in the event entity
            // $event->setImagePath($filename);

        // 4) Image handling: reuse addEvent logic + preserve existing if none uploaded
        $uploaded = $request->files->get('imagefile');
        // dump($uploaded); die;
        // dump($_FILES, $uploaded); die;
        // if ($uploaded instanceof UploadedFile && $uploaded->isValid()) {
        if ($uploaded && $uploaded->isValid()){
            // dump($uploaded->getSize()); die;
            $allowed = ['image/jpg','image/jpeg','image/png'];
            if (!in_array($uploaded->getMimeType(), $allowed)){
                $this->addFlash('error', 'Invalid image file type. Only JPG, JPEG, and PNG are allowed.');
                return $this->redirectToRoute('admin_manage_events');
            }

            if(!@getimagesize($uploaded->getPathname())){
                $this->addFlash('error', 'Nice try, but valid image only.');
                return $this->redirectToRoute('admin_manage_events');
            }

            // generate unique filename
            $filename = uniqid().'.'.$uploaded->guessExtension();
            try {
                $uploaded->move(
                    $this->getParameter('event_images_directory'),
                    $filename
                );
            } catch (\Exception $e) {
                dump($e->getMessage()); die;
            }
            // store relative path
            $event->setImagePath('images/events/'.$filename);
        }

        // 5) adding tickets 
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

        //-- ticket count check if exceed event capacity -- 
        // Count existing tickets
        $existingTickets = $event->getTicket();
        $existingTicketCount = count($existingTickets);

        // Count tickets to be added for existing ticket types
        $totalNewTickets = 0;
        $ticketTypes = $ticketTypeRepo->findAll();

        foreach ($ticketTypes as $ticketType) {
            $inputName = 'ticket_type_' . $ticketType->getId();
            $quantityToAdd = (int) $request->request->get($inputName);
            if ($quantityToAdd > 0) {
                $totalNewTickets += $quantityToAdd;
            }
        }

        // Count tickets to be added for new ticket types
        $newTicketTypes = $request->request->all('new_ticket_types');
        if ($newTicketTypes) {
            foreach ($newTicketTypes as $typeData) {
                $quantity = (int) ($typeData['quantity'] ?? 0);
                if ($quantity > 0) {
                    $totalNewTickets += $quantity;
                }
            }
        }

        $totalTicketsAfterUpdate = $existingTicketCount + $totalNewTickets;

        if ($totalTicketsAfterUpdate > $capacity) {
            $this->addFlash('error', "Total number of tickets after update ($totalTicketsAfterUpdate) exceeds event capacity ($capacity).");
            return $this->redirectToRoute('admin_manage_events');
        }


        $ticketTypes = $ticketTypeRepo->findAll();
        $seatCounter = $maxSeatNum + 1; 

        // Handle adding more tickets to existing ticket types
        foreach ($ticketTypes as $ticketType) {
            $inputName = 'ticket_type_' . $ticketType->getId();
            $quantityToAdd = (int) $request->request->get($inputName);

            if ($quantityToAdd > 0) {
                for ($i = 1; $i <= $quantityToAdd; $i++) {
                    $ticket = new Ticket();
                    $ticket->setEvent($event);
                    $ticket->setTicketType($ticketType);
                    
                    $seatNumber = 'S' . str_pad($seatCounter, 3, '0', STR_PAD_LEFT);
                    $ticket->setSeatNumber($seatNumber);
                    $seatCounter++;

                    $ticket->setPayment(null);
                    $em->persist($ticket);
                }
            }
        }

        // Handle NEW ticket types being added
        $newTicketTypes = $request->request->all('new_ticket_types');
        if ($newTicketTypes) {
            foreach ($newTicketTypes as $typeData) {
                $name = strip_tags(trim($typeData['name']));
                $description = strip_tags(trim($typeData['description']));
                $price = (float) $typeData['price'];
                $quantity = (int) $typeData['quantity'];

                // Skip if any required field is empty or invalid
                if (empty($name) || $price <= 0 || $quantity <= 0) {
                    continue;
                }

                // Create and save the new TicketType
                $ticketType = new TicketType();
                $ticketType->setName($name);
                $ticketType->setDescription($description);
                $ticketType->setPrice($price);
                $em->persist($ticketType);
                $em->flush(); // flush to get ID

                // Add the specified number of tickets for this new type
                for ($i = 0; $i < $quantity; $i++) {
                    $ticket = new Ticket();
                    $ticket->setEvent($event);
                    $ticket->setTicketType($ticketType);
                    $ticket->setSeatNumber("S" . str_pad($seatCounter, 3, '0', STR_PAD_LEFT));
                    $seatCounter++;
                    $ticket->setPayment(null);
                    $em->persist($ticket);
                }
            }
        }

        $em->flush();

        $this->addFlash('success', 'Event updated successfully.');
        return $this->redirectToRoute('admin_manage_events');
    }

    // Functionality for add event button 
    #[Route('/manage_event/add', name: 'add_event', methods: ['POST'])]
    public function addEvent(
        Request $request,
        EntityManagerInterface $em,
        VenueRepository $venueRepo,
        EventCategoryRepository $categoryRepo,
        // ValidatorInterface $validator
    ): Response {
        // 1) CSRF
        if (!$this->isCsrfTokenValid('add_event', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_manage_events');
        }

        // 2) Basic field validation
        $name        = strip_tags(trim($request->request->get('name', '')));
        $description = strip_tags(trim($request->request->get('description', '')));
        $organiser   = strip_tags(trim($request->request->get('organiser', '')));
        $rawDate     = $request->request->get('event_date');
        $rawStart    = $request->request->get('purchase_start_date');
        $rawEnd      = $request->request->get('purchase_end_date');
        $venue       = $venueRepo->find((int)$request->request->get('venue'));
        $category    = $categoryRepo->find((int)$request->request->get('category'));
        $capacity    = (int)$request->request->get('capacity');

        if (
            !$name || !$description || !$organiser ||
            !$rawDate || !$rawStart || !$rawEnd ||
            !$venue   || !$category ||
            $capacity < 1 || $capacity > $venue->getCapacity()
        ) {
            $this->addFlash('error', 'All fields are required and capacity must be between 1 and '.$venue->getCapacity().'.');
            return $this->redirectToRoute('admin_manage_events');
        }

        try {
            $eventDate     = new \DateTime($rawDate);
            $purchaseStart = new \DateTime($rawStart);
            $purchaseEnd   = new \DateTime($rawEnd);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Invalid date format.');
            return $this->redirectToRoute('admin_manage_events');
        }

        if ($purchaseStart >= $purchaseEnd) {
            $this->addFlash('error', 'Purchase start must be before purchase end.');
            return $this->redirectToRoute('admin_manage_events');
        }

        if ($eventDate < $purchaseEnd) {
            $this->addFlash('error', 'Purchase end cannot be after event date');
            return $this->redirectToRoute('admin_manage_events');
        }

        if(empty($organiser)){
            $this->addFlash('error', 'Organiser name is required.');
            return $this->redirectToRoute('admin_manage_events');
        }
        if(strlen($organiser) > 50){
            $this->addFlash('error', 'Organiser name cannot exceed 50 characters.');
            return $this->redirectToRoute('admin_manage_events');
        }
        if(!preg_match('/^[a-zA-Z0-9\s]+$/', $organiser)){
            $this->addFlash('error', 'Organiser name can only contain letters, numbers and spaces.');
            return $this->redirectToRoute('admin_manage_events');
        }

        // 3) Build and persist the Event
        $event = (new Event())
            ->setName($name)
            ->setDescription($description)
            ->setOrganiser($organiser)
            ->setEventDate($eventDate)
            ->setVenue($venue)
            ->setCategory($category)
            ->setCapacity($capacity)
            ->setPurchaseStartDate($purchaseStart)
            ->setPurchaseEndDate($purchaseEnd);
        
        // $violations = $validator->validate($event);
        // if (count($violations) > 0) {
        //     foreach ($violations as $violation) {
        //         $this->addFlash('error', $violation->getMessage());
        //     }
        //     return $this->redirectToRoute('admin_manage_events');
        // }

        // 4) Handle optional image upload
        $imagefile = $request->files->get('imagefile');
        if ($imagefile && $imagefile->isValid()) {
            $allowed = ['image/jpg','image/jpeg','image/png'];
            if (!in_array($imagefile->getMimeType(), $allowed) || !@getimagesize($imagefile->getPathname())) {
                $this->addFlash('error','Invalid image upload.');
                return $this->redirectToRoute('admin_manage_events');
            }

            $filename = uniqid().'.'.$imagefile->guessExtension();
            $imagefile->move(
                $this->getParameter('event_images_directory'),
                $filename
            );
            $event->setImagePath('images/events/'.$filename);
        }

        $em->persist($event);
        $em->flush(); // so $event->getId() exists

        // 5) Basic field validation for tickets
        $ticketTypes = $request->request->all('ticket_types'); // gets array from form
        // Check that the first ticket type exists and is fully filled
        if (empty($ticketTypes[0]['name']) || 
            !isset($ticketTypes[0]['price']) || $ticketTypes[0]['price'] <= 0 || 
            !isset($ticketTypes[0]['quantity']) || $ticketTypes[0]['quantity'] <= 0) {

            $this->addFlash('error', 'You must provide at least one complete ticket type (name, price, and quantity).');
            return $this->redirectToRoute('admin_manage_events');
        }

        $ticketTypesData = $request->request->all('ticket_types');
        $seatCounter     = 1;

        //check that total tickets dont exceed event capacity
        $totalTickets = 0;
        foreach ($ticketTypesData as $typeData) {
            $qty = intval($typeData['quantity'] ?? 0);
            if ($qty > 0) {
                $totalTickets += $qty;
            }
        }

        if ($totalTickets > $capacity) {
            // delete event if ticket count incorrect 
            $em->remove($event);
            $em->flush();

            $this->addFlash('error', 'Total ticket quantity (' . $totalTickets . ') exceeds event capacity (' . $capacity . ').');
            return $this->redirectToRoute('admin_manage_events');
        }
        
        // 6) Process ticket_types[...] from the form
        foreach ($ticketTypesData as $idx => $typeData) {
            $tName     = strip_tags(trim($typeData['name'] ?? ''));
            $tDesc     = strip_tags(trim($typeData['description'] ?? ''));
            $tPrice    = floatval($typeData['price'] ?? 0);
            $tQuantity = intval($typeData['quantity'] ?? 0);

            if (!$tName || $tPrice <= 0 || $tQuantity <= 0) {
                // skip any incomplete/invalid entries
                continue;
            }

            // a) create TicketType
            $tt = (new TicketType())
                ->setName($tName)
                ->setDescription($tDesc)
                ->setPrice($tPrice);

            $em->persist($tt);
            $em->flush(); // so $tt->getId() exists

            // b) create that many Ticket rows
            for ($i = 0; $i < $tQuantity; $i++) {
                $ticket = (new Ticket())
                    ->setEvent($event)
                    ->setTicketType($tt)
                    ->setSeatNumber(sprintf('S%03d', $seatCounter++))
                    ->setPayment(null);

                $em->persist($ticket);
            }
        }

        $em->flush();

        $this->addFlash('success','Event and tickets created successfully.');
        return $this->redirectToRoute('admin_manage_events');
    }

    // Functionality for delete event button
    #[Route('/manage_event/delete/{id}', name: 'delete_event', methods: ['POST'])]
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
    #[Route('/manage_user/update/{id}', name: 'update_user', methods: ['POST'])]
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

        // validate if username is empty
        $name = strip_tags(trim($request->request->get('name')));
        if (!$name) {
            $this->addFlash('error', 'Username is required.');
            return $this->redirectToRoute('admin_manage_users');
        }

        $newName = (strip_tags(trim($request->request->get('name'))));
        $newRole = $request->request->get('role');
        $newStatus = $request->request->get('accountStatus');
        $newOtp = $request->request->get('otpEnabled');

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

        $user->setName($newName)
             ->setUpdatedAt(new \DateTime());
        $user->setRole($newRole);
        $user->setAccountStatus($newStatus);
        $user->setOtpEnabled($newOtp);

        // If status is 'locked', set lockedAt timing
        if ($newStatus === 'locked') {
            $user->setLockedAt(new \DateTime());
        } else {
            $user->setLockedAt(null); // clear if not locked
        }

        $em->flush();

        $this->addFlash('success', 'User updated successfully.');
        return $this->redirectToRoute('admin_manage_users');
    }

    // Functionality for delete user buttion 
    #[Route('/user/delete/{id}', name: 'delete_user', methods: ['POST'])]
    public function deleteUser(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo
    ): Response {
        // CSRF token check for security
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

        // Prevent deleting yourself 
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