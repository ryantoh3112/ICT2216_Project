<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Entity\Auth;
use App\Repository\UserRepository;
use App\Repository\AuthRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

#[Route('/user', name: 'user_')]
final class UserController extends AbstractController
{
    #[Route('/profile', name: 'profile')]
    public function profile(
        Request $request, 
        AuthRepository $authRepository
    ): Response
    {
        $session = $request->getSession();

        if (!$session->has('user_id')) {
            $this->addFlash('error', 'Please log in to view your profile.');
            return $this->redirectToRoute('auth_login');
        }

        // Get user ID from session
        $userId = $session->get('user_id');
        $auth = $authRepository->findOneBy(['user' => $userId]);

        if (!$auth) {
            throw $this->createNotFoundException('User not found.');
        }

        return $this->render('user/profile.html.twig', [
            'user' => $auth->getUser(),
            'email' => $auth->getEmail()
        ]);
    }

    #[Route('/profile/update-username', name: 'update_username', methods: ['POST'])]
    public function updateUsername(
        Request $request, 
        EntityManagerInterface $em, 
        UserRepository $userRepo): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');

        if (!$userId) {
            $this->addFlash('error', 'Please log in.');
            return $this->redirectToRoute('auth_login');
        }

        $user = $userRepo->find($userId);

        if (!$user) {
            throw $this->createNotFoundException('User not found.');
        }

        $newName = trim($request->request->get('username'));

        // Check if the username field is empty
        if (empty($newName)) {
            $this->addFlash('error', 'Username cannot be empty.');
            return $this->redirectToRoute('user_profile');
        }

        // Check if the new username is already taken by another user
        $existingUser = $userRepo->findOneBy(['name' => $newName]);

        if ($existingUser && $existingUser->getId() !== $user->getId()) {
            $this->addFlash('error', 'This username is already taken. Please choose another one.');
            return $this->redirectToRoute('user_profile');
        }

        $user->setName($newName);
        $em->flush();

        $session->set('user_name', $newName); // Update session value for navbar
        $this->addFlash('success', 'Username updated successfully.');
        return $this->redirectToRoute('user_profile');
    }
}
