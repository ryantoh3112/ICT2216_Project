<?php

namespace App\Controller;

// use App\Entity\Admin;
use App\Entity\User;
use App\Entity\Event;
use App\Entity\Venue;
use App\Entity\EventCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\UserRepository;
use App\Repository\EventRepository;
use App\Repository\VenueRepository;
use App\Repository\EventCategoryRepository;
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
        EventCategoryRepository $categoryRepo
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

        return $this->render('admin/manage_events.html.twig',[
            'events' => $events,
            'venues' => $venues,
            'categories' => $categories
        ]);
    }

    // Functionality for edit event button
    #[Route('/admin/manage_event/update/{id}', name: 'update_event', methods: ['POST'])]
    public function updateEvent(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        EventRepository $eventRepo
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

        $startDate = new \DateTime($request->request->get('purchase_start_date'));
        $endDate = new \DateTime($request->request->get('purchase_end_date'));

        if ($startDate >= $endDate) {
            $this->addFlash('error', 'Purchase start date must be before the end date.');
            return $this->redirectToRoute('admin_manage_events');
        }   

        // Update fields
        $event->setName($request->request->get('name'));
        $event->setDescription($request->request->get('description'));
        $event->setCapacity((int) $request->request->get('capacity'));
        $event->setPurchaseStartDate($startDate);
        $event->setPurchaseEndDate($endDate);
        $event->setOrganiser($request->request->get('organiser'));

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

        $startDate = new \DateTime($request->request->get('purchase_start_date'));
        $endDate = new \DateTime($request->request->get('purchase_end_date'));

        if ($startDate >= $endDate) {
            $this->addFlash('error', 'Purchase start date must be before the end date.');
            return $this->redirectToRoute('admin_manage_events');
        }

        $event = new Event();

        $event->setName($request->request->get('name'));
        $event->setDescription($request->request->get('description'));
        $event->setCapacity((int)$request->request->get('capacity'));
        $event->setOrganiser($request->request->get('organiser'));
        $event->setImage($request->request->get('image'));
        $event->setPurchaseStartDate($startDate);
        $event->setPurchaseEndDate($endDate);

        // Get related venue and category
        $venue = $venueRepo->find((int) $request->request->get('venue'));
        $category = $categoryRepo->find((int) $request->request->get('category'));

        if ($venue && $category) {
            $event->setVenue($venue);
            $event->setCategory($category);
            $em->persist($event);
            $em->flush();
            $this->addFlash('success', 'Event created successfully.');
        } else {
            $this->addFlash('error', 'Venue or category not found.');
        }

        return $this->redirectToRoute('admin_manage_events');
    }

    // Functionality for delete event button
    #[Route('/admin/manage_event/delete/{id}', name: 'delete_event', methods: ['POST'])]
    public function deleteEvent(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        EventRepository $eventRepo
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