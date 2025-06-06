<?php

namespace App\Controller;

use App\Entity\Venue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VenueController extends AbstractController
{
    // #[Route('/venue', name: 'app_venue')]
    // public function index(): Response
    // {
    //     return $this->render('venue/index.html.twig', [
    //         'controller_name' => 'VenueController',
    //     ]);
    // }

    #[Route('/venues', name: 'app_venues')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Fetch all venues
        $venues = $entityManager->getRepository(Venue::class)->findAll();

        // dump($venues); // Add this line to see output in Symfony toolbar
        // dd('Reached the controller'); // Stops execution and dumps message

        // Render the venues in the Twig template
        return $this->render('venue/index.html.twig', [
            'venues' => $venues,
        ]);

        // dd($venues); // See if this dumps the venue list
    }
}
