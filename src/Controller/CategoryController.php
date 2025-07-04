<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\EventRepository;
use App\Repository\EventCategoryRepository;
use Symfony\Component\HttpFoundation\Request;

#[Route('/categories', name: 'category_')]
final class CategoryController extends AbstractController
{
    #[Route('', name: 'list')]
    public function index(EventCategoryRepository $categoryRepository): Response
    {
        $categories = $categoryRepository->findAll();

        return $this->render('event/categories.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/{name}', name: 'events_by_name', requirements: ['name' => '.+'])]
    public function showEventsByCategory(
        string $name,
        Request $request,
        EventCategoryRepository $categoryRepository,
        EventRepository $eventRepository
    ): Response {
        $category = $categoryRepository->findOneBy(['name' => $name]);

        if (!$category) {
            throw $this->createNotFoundException('Category not found');
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 6;
        $offset = ($page - 1) * $limit;

        $allEvents = $eventRepository->findBy(['category' => $category]);
        $totalEvents = count($allEvents);
        $totalPages = (int) ceil($totalEvents / $limit);

        $events = array_slice($allEvents, $offset, $limit);

        return $this->render('event/events_by_category.html.twig', [
            'category' => $category,
            'events' => $events,
            'totalPages' => $totalPages,
            'currentPage' => $page,
        ]);
    }
}