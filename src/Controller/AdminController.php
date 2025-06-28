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

        $auth = $authRepository->findOneBy(['user' => $user]);

        return $this->render('admin/manage_events.html.twig');
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